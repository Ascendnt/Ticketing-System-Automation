<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTicketMeta extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'ticket_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'priority' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'Medium',
            ],
            'due_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'assigned_to' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('ticket_id');
        $this->forge->createTable('ticket_meta');
    }

    public function down()
    {
        $this->forge->dropTable('ticket_meta', true);
    }
}
