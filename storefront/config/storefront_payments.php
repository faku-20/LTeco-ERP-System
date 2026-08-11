<?php

declare(strict_types=1);

return [
    // `none` mantiene el pago online cerrado hasta aprobar proveedor y contrato.
    'provider' => (string) env('STOREFRONT_PAYMENT_PROVIDER', 'none'),
    'online_enabled' => (bool) env('STOREFRONT_ONLINE_PAYMENTS_ENABLED', false),
    'getnet' => [
        'environment' => (string) env('GETNET_ENVIRONMENT', 'sandbox'),
        'merchant_id' => (string) env('GETNET_MERCHANT_ID', ''),
        'terminal_id' => (string) env('GETNET_TERMINAL_ID', ''),
        'client_id' => (string) env('GETNET_CLIENT_ID', ''),
        'client_secret' => (string) env('GETNET_CLIENT_SECRET', ''),
        'webhook_secret' => (string) env('GETNET_WEBHOOK_SECRET', ''),
    ],
];
