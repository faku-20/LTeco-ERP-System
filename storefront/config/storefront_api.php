<?php

declare(strict_types=1);

return [
    'panel' => [
        'base_url' => env('PANEL_API_BASE_URL', 'http://panel/lteco-panel/api/storefront/v1'),
        'key_id' => env('PANEL_API_KEY_ID', 'storefront-current'),
        'secret' => env('PANEL_API_SECRET'),
        'forwarded_proto' => env('PANEL_API_FORWARDED_PROTO'),
        'allow_insecure' => env('PANEL_API_ALLOW_INSECURE', false),
    ],
    'incoming' => [
        'key_id' => env('STOREFRONT_INTERNAL_KEY_ID', 'panel-current'),
        'secret' => env('STOREFRONT_INTERNAL_SECRET'),
        'previous_key_id' => env('STOREFRONT_INTERNAL_PREVIOUS_KEY_ID'),
        'previous_secret' => env('STOREFRONT_INTERNAL_PREVIOUS_SECRET'),
    ],
    'timestamp_tolerance_seconds' => 300,
    'nonce_ttl_seconds' => 600,
    'max_body_bytes' => 262144,
    'connect_timeout_seconds' => 2,
    'timeout_seconds' => 5,
];
