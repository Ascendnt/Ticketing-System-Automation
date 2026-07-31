<?php

namespace App\Controllers;

use App\Models\N8nService;
use App\Models\NocoDbTicketModel;
use App\Models\TicketMetaModel;
use App\Models\TicketMessageModel;
use App\Models\UserModel;

class TicketController extends BaseController
{
    protected N8nService $n8nService;
    protected NocoDbTicketModel $ticketModel;
    protected TicketMetaModel $metaModel;
    protected TicketMessageModel $messageModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->n8nService   = new N8nService();
        $this->ticketModel  = new NocoDbTicketModel();
        $this->metaModel    = new TicketMetaModel();
        $this->messageModel = new TicketMessageModel();
        $this->userModel    = new UserModel();
    }

    /**
     * Whether the current session user may view/act on this ticket.
     */
    private function canAccess(array $fields, array $user): bool
    {
        return match ($user['role']) {
            UserModel::ROLE_SUPERADMIN => true,
            UserModel::ROLE_AGENT      => strcasecmp(resolve_base_department(ticket_department($fields)), (string) $user['department']) === 0,
            UserModel::ROLE_REQUESTER  => strcasecmp($fields['Requester Email'] ?? '', $user['email']) === 0,
            default                    => false,
        };
    }

    private function scopedTickets(array $tickets, array $user): array
    {
        return array_values(array_filter($tickets, fn ($t) => $this->canAccess($t['fields'] ?? [], $user)));
    }

    /**
     * Role-scoped tickets, each annotated with ['department'] and ['meta']
     * (priority, due_date, assigned agent id/name). Shared by the dashboard
     * and the export endpoint so scoping/enrichment never drifts apart.
     */
    private function enrichedScopedTickets(array $user): array
    {
        $tickets = $this->scopedTickets($this->ticketModel->getAllTickets(), $user);

        $ticketIds = array_map(fn ($t) => (string) ($t['id'] ?? ''), $tickets);
        $metaById  = $this->metaModel->forTickets($ticketIds);

        $assignedIds = array_filter(array_column($metaById, 'assigned_to'));
        $agentNames  = [];
        if (! empty($assignedIds)) {
            foreach ($this->userModel->whereIn('id', array_unique($assignedIds))->findAll() as $agent) {
                $agentNames[$agent['id']] = $agent['name'];
            }
        }

        foreach ($tickets as &$t) {
            $id   = (string) ($t['id'] ?? '');
            $meta = $metaById[$id] ?? ['priority' => 'Medium', 'due_date' => null, 'assigned_to' => null];

            $t['department'] = ticket_department($t['fields'] ?? []);
            $t['meta'] = [
                'priority'      => $meta['priority'] ?? 'Medium',
                'due_date'      => $meta['due_date'] ?? null,
                'assigned_to'   => $meta['assigned_to'] ?? null,
                'assigned_name' => isset($meta['assigned_to']) ? ($agentNames[$meta['assigned_to']] ?? null) : null,
            ];
        }
        unset($t);

        return $tickets;
    }

    /**
     * Applies the dashboard's search/status/category/priority/department
     * query-string filters to an already-scoped ticket list.
     */
    private function applyFilters(array $tickets, array $user, array $filters): array
    {
        [$q, $status, $category, $priority, $department] = [
            $filters['q'], $filters['status'], $filters['category'], $filters['priority'], $filters['department'],
        ];

        if ($q !== '') {
            $needle  = mb_strtolower($q);
            $tickets = array_values(array_filter($tickets, function ($t) use ($needle) {
                $f = $t['fields'] ?? [];
                $haystack = mb_strtolower(($f['Request Title'] ?? $f['Title'] ?? '') . ' ' . ($f['Requester'] ?? '') . ' ' . ($f['Requester Email'] ?? ''));
                return str_contains($haystack, $needle);
            }));
        }
        if ($status !== '') {
            $tickets = array_values(array_filter($tickets, fn ($t) => ($t['fields']['Status'] ?? 'New') === $status));
        }
        if ($category !== '') {
            $tickets = array_values(array_filter($tickets, fn ($t) => ($t['fields']['Category'] ?? '') === $category));
        }
        if ($priority !== '') {
            $tickets = array_values(array_filter($tickets, fn ($t) => ($t['meta']['priority'] ?? 'Medium') === $priority));
        }
        if ($department !== '' && $user['role'] === UserModel::ROLE_SUPERADMIN) {
            $tickets = array_values(array_filter($tickets, fn ($t) => strcasecmp(resolve_base_department($t['department'] ?? ''), $department) === 0));
        }

        return $tickets;
    }

    private function requestFilters(): array
    {
        return [
            'q'          => trim((string) ($this->request->getGet('q') ?? '')),
            'status'     => (string) ($this->request->getGet('status') ?? ''),
            'category'   => (string) ($this->request->getGet('category') ?? ''),
            'priority'   => (string) ($this->request->getGet('priority') ?? ''),
            'department' => (string) ($this->request->getGet('department') ?? ''),
        ];
    }

    /**
     * GET /tickets
     */
    public function index()
    {
        $user      = current_user();
        $allScoped = $this->enrichedScopedTickets($user);
        $filters   = $this->requestFilters();
        $tickets   = $this->applyFilters($allScoped, $user, $filters);

        // --- sort ---
        $sort = (string) ($this->request->getGet('sort') ?? 'created_desc');
        $sorters = [
            'created_desc' => fn ($a, $b) => strtotime($b['fields']['CreatedAt'] ?? 'now') <=> strtotime($a['fields']['CreatedAt'] ?? 'now'),
            'created_asc'  => fn ($a, $b) => strtotime($a['fields']['CreatedAt'] ?? 'now') <=> strtotime($b['fields']['CreatedAt'] ?? 'now'),
            'title_asc'    => fn ($a, $b) => strcasecmp($a['fields']['Request Title'] ?? $a['fields']['Title'] ?? '', $b['fields']['Request Title'] ?? $b['fields']['Title'] ?? ''),
            'priority_desc' => function ($a, $b) {
                $order = array_flip(priorities());
                return ($order[$b['meta']['priority'] ?? 'Medium'] ?? 0) <=> ($order[$a['meta']['priority'] ?? 'Medium'] ?? 0);
            },
        ];
        usort($tickets, $sorters[$sort] ?? $sorters['created_desc']);

        // --- pagination ---
        $perPage     = 8;
        $page        = max(1, (int) ($this->request->getGet('page') ?? 1));
        $totalFound  = count($tickets);
        $totalPages  = max(1, (int) ceil($totalFound / $perPage));
        $page        = min($page, $totalPages);
        $pageTickets = array_slice($tickets, ($page - 1) * $perPage, $perPage);

        // --- stats (unfiltered, but still role-scoped) ---
        $statusCounts = ['New' => 0, 'In Progress' => 0, 'Resolved' => 0, 'Closed' => 0];
        foreach ($allScoped as $t) {
            $s = $t['fields']['Status'] ?? 'New';
            if (isset($statusCounts[$s])) {
                $statusCounts[$s]++;
            }
        }

        $categories = array_values(array_unique(array_filter(array_map(
            fn ($t) => $t['fields']['Category'] ?? null,
            $allScoped
        ))));
        sort($categories);

        return view('tickets/index', array_merge($filters, [
            'tickets'       => $pageTickets,
            'total'         => count($allScoped),
            'categories'    => $categories,
            'statusCounts'  => $statusCounts,
            'sort'          => $sort,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'totalFound'    => $totalFound,
        ]));
    }

    /**
     * GET /tickets/export?format=csv|json|xls — respects the same
     * role-scoping and filters as the dashboard, but ignores pagination.
     */
    public function export()
    {
        $user    = current_user();
        $tickets = $this->applyFilters($this->enrichedScopedTickets($user), $user, $this->requestFilters());

        $rows = array_map(function ($t) {
            $f = $t['fields'] ?? [];

            return [
                'ID'                  => $t['id'] ?? '',
                'Title'               => $f['Request Title'] ?? $f['Title'] ?? '',
                'Department'          => $t['department'] ?? '',
                'Category'            => $f['Category'] ?? '',
                'Requester'           => $f['Requester'] ?? '',
                'Requester Email'     => $f['Requester Email'] ?? '',
                'Status'              => $f['Status'] ?? 'New',
                'Priority'            => $t['meta']['priority'] ?? 'Medium',
                'Due Date'            => $t['meta']['due_date'] ?? '',
                'Assigned To'         => $t['meta']['assigned_name'] ?? '',
                'AI Source'           => $f['AI Source'] ?? '',
                'Suggested TAT'       => $f['Suggested TAT'] ?? '',
                'Created At'          => $f['CreatedAt'] ?? '',
            ];
        }, $tickets);

        $format   = strtolower((string) ($this->request->getGet('format') ?? 'csv'));
        $filename = 'tickets_export_' . date('Y-m-d_His');

        return match ($format) {
            'json' => $this->response
                ->setContentType('application/json')
                ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}.json\"")
                ->setBody(json_encode($rows, JSON_PRETTY_PRINT)),
            'xls' => $this->exportXls($rows, $filename),
            default => $this->exportCsv($rows, $filename),
        };
    }

    private function exportCsv(array $rows, string $filename)
    {
        $out = fopen('php://temp', 'w+');

        if (! empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setContentType('text/csv')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}.csv\"")
            ->setBody($csv);
    }

    /**
     * A dependency-free "Excel" export: an HTML table served with the Excel
     * MIME type, which Excel/Sheets/LibreOffice all open as a worksheet.
     */
    private function exportXls(array $rows, string $filename)
    {
        $columns = empty($rows) ? [] : array_keys($rows[0]);

        $html = '<html><head><meta charset="UTF-8"></head><body><table border="1">';
        $html .= '<tr>' . implode('', array_map(fn ($c) => '<th>' . esc($c) . '</th>', $columns)) . '</tr>';
        foreach ($rows as $row) {
            $html .= '<tr>' . implode('', array_map(fn ($v) => '<td>' . esc((string) $v) . '</td>', $row)) . '</tr>';
        }
        $html .= '</table></body></html>';

        return $this->response
            ->setContentType('application/vnd.ms-excel')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}.xls\"")
            ->setBody($html);
    }

    /**
     * GET /tickets/new
     */
    public function create()
    {
        return view('tickets/create', ['user' => current_user()]);
    }

    /**
     * POST /tickets
     */
    public function store()
    {
        $user = current_user();

        $data = [
            'Requester Name'         => $user['name'],
            'Requester Email'        => $user['email'],
            'Submitting Department'  => $this->request->getPost('submitting_department'),
            'Request'                => $this->request->getPost('request_description'),
        ];

        $ticket = $this->n8nService->submitTicket($data);

        if ($ticket === null) {
            return redirect()->back()->withInput()->with('error', 'Something went wrong submitting your ticket. Please try again.');
        }

        return redirect()->to('/tickets')->with('success', 'Ticket submitted successfully!');
    }

    /**
     * GET /tickets/{id}
     */
    public function show(string $id)
    {
        $ticket = $this->ticketModel->getTicket($id);

        if ($ticket === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $user   = current_user();
        $fields = $ticket['fields'] ?? $ticket;

        if (! $this->canAccess($fields, $user)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $meta = $this->metaModel->firstOrCreate($id);

        $department  = ticket_department($fields);
        $agents      = $department !== '' ? $this->userModel->agentsInDepartment(resolve_base_department($department)) : [];
        $assignedTo  = null;
        if (! empty($meta['assigned_to'])) {
            $assignedTo = $this->userModel->find($meta['assigned_to']);
        }

        return view('tickets/show', [
            'ticket'     => $ticket,
            'meta'       => $meta,
            'agents'     => $agents,
            'assignedTo' => $assignedTo,
            'messages'   => $this->messageModel->forTicket($id),
            'canManage'  => in_array($user['role'], [UserModel::ROLE_SUPERADMIN, UserModel::ROLE_AGENT], true),
        ]);
    }

    private function requireManageAccess(string $id): array
    {
        $ticket = $this->ticketModel->getTicket($id);
        if ($ticket === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $user   = current_user();
        $fields = $ticket['fields'] ?? $ticket;

        if (! $this->canAccess($fields, $user) || $user['role'] === UserModel::ROLE_REQUESTER) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $fields;
    }

    /**
     * POST /tickets/{id}/status
     */
    public function updateStatus(string $id)
    {
        $this->requireManageAccess($id);

        $status  = $this->request->getPost('status');
        $allowed = ['New', 'In Progress', 'Resolved', 'Closed'];
        if (! in_array($status, $allowed, true)) {
            return redirect()->back()->with('error', 'Invalid status.');
        }

        $this->ticketModel->updateStatus($id, $status);

        return redirect()->to("/tickets/{$id}")->with('success', 'Status updated.');
    }

    /**
     * POST /tickets/{id}/meta
     */
    public function updateMeta(string $id)
    {
        $this->requireManageAccess($id);

        $priority   = (string) $this->request->getPost('priority');
        $dueDate    = (string) $this->request->getPost('due_date');
        $assignedTo = $this->request->getPost('assigned_to');

        if (! in_array($priority, priorities(), true)) {
            return redirect()->back()->with('error', 'Invalid priority.');
        }

        $this->metaModel->updateForTicket($id, [
            'priority'    => $priority,
            'due_date'    => $dueDate !== '' ? $dueDate : null,
            'assigned_to' => $assignedTo !== '' ? (int) $assignedTo : null,
        ]);

        return redirect()->to("/tickets/{$id}")->with('success', 'Ticket details updated.');
    }

    /**
     * POST /tickets/{id}/messages
     */
    public function postMessage(string $id)
    {
        $ticket = $this->ticketModel->getTicket($id);
        if ($ticket === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $user   = current_user();
        $fields = $ticket['fields'] ?? $ticket;

        if (! $this->canAccess($fields, $user)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $body = trim((string) $this->request->getPost('body'));
        if ($body === '') {
            return redirect()->back()->with('error', 'Message cannot be empty.');
        }

        $this->messageModel->post($id, $user['id'], $user['name'], $user['role'], $body);

        return redirect()->to("/tickets/{$id}#conversation")->with('success', 'Message sent.');
    }
}
