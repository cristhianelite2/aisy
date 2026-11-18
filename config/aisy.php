<?php

return [
    'odoo' => [
        'url' => env('ODOO_URL', 'http://127.0.0.1:8069'),
        'db' => env('ODOO_DB', 'aisy_erp'),
        'user' => env('ODOO_USER', 'admin'),
        'password' => env('ODOO_PASSWORD', 'admin'),
        'timeout' => (int) env('ODOO_TIMEOUT', 6),
    ],
    'wa_gateway' => [
        'url' => env('WA_GATEWAY_URL', 'http://127.0.0.1:8085'),
        'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 6),
    ],
    'miia' => [
        'url' => rtrim((string) env('MIIA_BASE_URL', 'https://ia.ceballosleon.com'), '/'),
        'api_key' => env('MIIA_API_KEY', ''),
        'timeout' => (int) env('MIIA_TIMEOUT', 20),
    ],
    'seller_central' => [
        'url' => rtrim((string) env('SC_BASE_URL', 'https://sc.ceballosleon.com'), '/'),
        'master_key' => env('SC_MASTER_KEY', ''),
        'timeout' => (int) env('SC_TIMEOUT', 15),
    ],
];
