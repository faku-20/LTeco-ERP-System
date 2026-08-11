<?php

declare(strict_types=1);

return [
    'cookie_name' => env('STOREFRONT_CART_COOKIE', 'commerceops_guest_cart'),
    'lifetime_minutes' => (int) env('STOREFRONT_CART_LIFETIME_MINUTES', 43200),
    'max_quantity' => (int) env('STOREFRONT_CART_MAX_QUANTITY', 10),
    'max_units_per_order' => (int) env('STOREFRONT_CART_MAX_UNITS_PER_ORDER', 10),
    'secure_cookie' => (bool) env('STOREFRONT_CART_SECURE_COOKIE', true),
];
