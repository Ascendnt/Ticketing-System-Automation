<?php

namespace App\Models;

use Config\NocoDb;

class NocoDbTicketModel
{
    protected NocoDb $config;
    protected \CodeIgniter\HTTP\CURLRequest $client;

    public function __construct()
    {
        $this->config = new NocoDb();
        $this->client = \Config\Services::curlrequest();
    }

    /**
     * Fetch all tickets from NocoDB.
     */
    public function getAllTickets(): array
    {
        $url = "{$this->config->baseUrl}/api/v3/data/{$this->config->baseId}/{$this->config->tableId}/records";

        $response = $this->client->get($url, [
            'headers' => [
                'xc-token' => $this->config->apiToken,
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        return $body['records'] ?? [];
    }

    /**
     * Fetch a single ticket by its NocoDB record ID.
     */
    public function getTicket(string $id): ?array
    {

    
        $url = "{$this->config->baseUrl}/api/v3/data/{$this->config->baseId}/{$this->config->tableId}/records/{$id}";

        $response = $this->client->get($url, [
            'headers' => [
                'xc-token' => $this->config->apiToken,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return json_decode($response->getBody(), true);

        
    }
    /**
 * Update a ticket's status in NocoDB.
 */
public function updateStatus(string $id, string $status): bool
{
    $url = "{$this->config->baseUrl}/api/v3/data/{$this->config->baseId}/{$this->config->tableId}/records";

    $response = $this->client->request('PATCH', $url, [
        'headers' => [
            'xc-token'     => $this->config->apiToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            [
                'id'     => $id,
                'fields' => ['Status' => $status],
            ],
        ],
    ]);

    return in_array($response->getStatusCode(), [200, 201]);
}
}