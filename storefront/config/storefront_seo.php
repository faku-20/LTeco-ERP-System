<?php

declare(strict_types=1);

return [
    'indexable' => (bool) env('STOREFRONT_INDEXABLE', false),
    'site_verification' => (string) env('GOOGLE_SITE_VERIFICATION', ''),
    'default_image' => 'images/editorial/hero-principal.webp',
    'production_url' => rtrim((string) env('STOREFRONT_PRODUCTION_URL', 'https://example.com'), '/'),
];
