<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class N8n extends BaseConfig
{
    public string $webhookUrl;

    public function __construct()
    {
        parent::__construct();

        $this->webhookUrl = env('N8N_WEBHOOK_URL');
    }
}