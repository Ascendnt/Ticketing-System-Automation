<?php

namespace App\Models;

use Config\N8n;

class N8nService
{
    protected N8n $config;
    protected \CodeIgniter\HTTP\CURLRequest $client;

    public function __construct()
    {
        $this->config = new N8n();
        $this->client = \Config\Services::curlrequest();
    }

    /**
     * Submit a new ticket request to n8n for AI classification + routing.
     * Returns the created ticket data once n8n finishes processing.
     *
     * @param array $data Expected keys: Requester Name, Requester Email, Submitting Department, Request
     */
    public function submitTicket(array $data): ?array
    {
        $response = $this->client->post($this->config->webhookUrl, [
            'json' => $data,
            'timeout' => 120, // Ollama/Gemini processing can take up to ~120s
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = json_decode($response->getBody(), true);

        // n8n returns an array with one ticket object inside
        return $body[0] ?? null;
    }
}