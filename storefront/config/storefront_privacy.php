<?php

declare(strict_types=1);

return [
    // Debe permanecer estable aunque APP_KEY rote. En local se permite el
    // fallback para facilitar el desarrollo; producción debe configurarla.
    'blind_index_key' => env('CUSTOMER_BLIND_INDEX_KEY', env('APP_KEY')),
    'audit_hash_key' => env('AUDIT_HASH_KEY', env('CUSTOMER_BLIND_INDEX_KEY', env('APP_KEY'))),
    'request_due_business_days' => 5,
    'audit_retention_days' => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 730),
    'maintenance_enabled' => (bool) env('PRIVACY_MAINTENANCE_ENABLED', false),
];
