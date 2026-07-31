<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketMessageModel extends Model
{
    protected $table         = 'ticket_messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['ticket_id', 'user_id', 'author_name', 'author_role', 'body', 'created_at'];

    public function forTicket(string $ticketId): array
    {
        return $this->where('ticket_id', $ticketId)->orderBy('created_at', 'ASC')->findAll();
    }

    public function post(string $ticketId, ?int $userId, string $authorName, string $authorRole, string $body): int|string|false
    {
        return $this->insert([
            'ticket_id'   => $ticketId,
            'user_id'     => $userId,
            'author_name' => $authorName,
            'author_role' => $authorRole,
            'body'        => $body,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
