<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class NocoDb extends BaseConfig
{
    public string $baseUrl;
    public string $apiToken;
    public string $baseId;
    public string $tableId;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl  = env('NOCODB_BASE_URL');
        $this->apiToken = env('NOCODB_API_TOKEN');
        $this->baseId   = env('NOCODB_BASE_ID');
        $this->tableId  = env('NOCODB_TABLE_ID');
    }
}