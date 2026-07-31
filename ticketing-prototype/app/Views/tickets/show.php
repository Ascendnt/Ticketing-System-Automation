<?= $this->extend('templates/layout') ?>

<?= $this->section('content') ?>

<?php
    $f = $ticket['fields'] ?? $ticket;
    $user = current_user();

    function priorityPillClassShow(string $priority): string {
        return match ($priority) {
            'Low' => 'pill-priority-low',
            'High' => 'pill-priority-high',
            'Urgent' => 'pill-priority-urgent',
            default => 'pill-priority-medium',
        };
    }
?>

<a href="/tickets" class="back-link"><?= icon('chevron-left', 14) ?> Back to <?= $user['role'] === 'requester' ? 'my tickets' : 'dashboard' ?></a>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= icon('check', 16) ?> <?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-error"><?= icon('flag', 16) ?> <?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="page-header">
    <div>
        <h1><?= esc($f['Request Title'] ?? $f['Title'] ?? 'Ticket') ?></h1>
        <div class="subtitle">Submitted by <?= esc($f['Requester'] ?? '—') ?> · <?= esc(date('M j, Y g:i A', strtotime($f['CreatedAt'] ?? 'now'))) ?></div>
    </div>
    <div class="table-actions">
        <span class="pill <?= priorityPillClassShow($meta['priority'] ?? 'Medium') ?>"><span class="dot"></span><?= esc($meta['priority'] ?? 'Medium') ?></span>
        <span class="pill pill-source-<?= strtolower($f['AI Source'] ?? 'local') === 'gemini' ? 'gemini' : 'local' ?>"><?= esc($f['AI Source'] ?? '—') ?></span>
    </div>
</div>

<div class="detail-layout">
    <div>
        <div class="detail-grid">
            <div class="detail-field">
                <div class="field-label">Status</div>
                <div class="field-value"><?= esc($f['Status'] ?? 'New') ?></div>
            </div>
            <div class="detail-field">
                <div class="field-label">Category</div>
                <div class="field-value"><?= esc($f['Category'] ?? '—') ?></div>
            </div>
            <div class="detail-field">
                <div class="field-label">Responsible Department</div>
                <div class="field-value"><?= esc($f['Responsible Department'] ?? '—') ?></div>
            </div>
            <div class="detail-field">
                <div class="field-label">Assigned To</div>
                <div class="field-value"><?= $assignedTo ? esc($assignedTo['name']) : '—' ?></div>
            </div>
            <div class="detail-field full">
                <div class="field-label">Description</div>
                <div class="field-value"><?= esc($f['Description'] ?? $f['Request'] ?? '—') ?></div>
            </div>
            <div class="detail-field full">
                <div class="field-label">Expected Deliverable</div>
                <div class="field-value"><?= esc($f['Expected Deliverable'] ?? '—') ?></div>
            </div>
            <div class="detail-field">
                <div class="field-label">Suggested TAT</div>
                <div class="field-value"><?= esc($f['Suggested TAT'] ?? '—') ?></div>
            </div>
            <div class="detail-field">
                <div class="field-label">Due Date</div>
                <div class="field-value"><?= $meta['due_date'] ? esc(date('M j, Y', strtotime($meta['due_date']))) : '—' ?></div>
            </div>
            <div class="detail-field full">
                <div class="field-label">Requirements Needed</div>
                <div class="field-value"><?= esc($f['Requirements Needed'] ?? '—') ?></div>
            </div>
            <div class="detail-field full">
                <div class="field-label">Closure Criteria</div>
                <div class="field-value"><?= esc($f['Closure Criteria'] ?? '—') ?></div>
            </div>
        </div>

        <h3 class="section-title" id="conversation"><?= icon('mail', 16) ?> Conversation</h3>
        <div class="thread">
            <?php if (empty($messages)): ?>
                <div class="thread-empty">No messages yet — start the conversation below.</div>
            <?php endif ?>
            <?php foreach ($messages as $msg): ?>
                <div class="msg role-<?= esc($msg['author_role']) ?>">
                    <div class="msg-avatar"><?= esc(strtoupper(substr($msg['author_name'], 0, 1))) ?></div>
                    <div class="msg-body">
                        <div class="msg-meta">
                            <span class="msg-author"><?= esc($msg['author_name']) ?></span>
                            <span class="badge-outline"><?= esc($msg['author_role']) ?></span>
                            <span class="msg-time"><?= esc(date('M j, g:i A', strtotime($msg['created_at']))) ?></span>
                        </div>
                        <div class="msg-text"><?= esc($msg['body']) ?></div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <form action="/tickets/<?= esc($ticket['id'] ?? '') ?>/messages" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <textarea name="body" rows="3" placeholder="Write a reply…" required></textarea>
            </div>
            <button type="submit" class="btn btn-sm"><?= icon('send', 15) ?> Send message</button>
        </form>
    </div>

    <div>
        <?php if ($canManage): ?>
            <div class="side-panel">
                <h3><?= icon('edit', 15) ?> Update Status</h3>
                <form action="/tickets/<?= esc($ticket['id'] ?? '') ?>/status" method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <select name="status" onchange="this.form.submit()">
                        <?php foreach (['New', 'In Progress', 'Resolved', 'Closed'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($f['Status'] ?? 'New') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach ?>
                    </select>
                </form>
            </div>

            <div class="side-panel">
                <h3><?= icon('flag', 15) ?> Priority &amp; Assignment</h3>
                <form action="/tickets/<?= esc($ticket['id'] ?? '') ?>/meta" method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        <?php foreach (priorities() as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($meta['priority'] ?? 'Medium') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach ?>
                    </select>

                    <label for="due_date">Due date</label>
                    <input type="date" id="due_date" name="due_date" value="<?= esc($meta['due_date'] ?? '') ?>">

                    <label for="assigned_to">Assign to</label>
                    <select id="assigned_to" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id'] ?>" <?= (int) ($meta['assigned_to'] ?? 0) === (int) $agent['id'] ? 'selected' : '' ?>><?= esc($agent['name']) ?></option>
                        <?php endforeach ?>
                    </select>

                    <button type="submit" class="btn btn-secondary"><?= icon('check', 15) ?> Save</button>
                </form>
            </div>
        <?php else: ?>
            <div class="side-panel">
                <h3><?= icon('clock', 15) ?> Timeline</h3>
                <div class="detail-field" style="box-shadow:none;padding:0;border:none;">
                    <div class="field-label">Status</div>
                    <div class="field-value"><?= esc($f['Status'] ?? 'New') ?></div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
