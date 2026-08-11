<?php

declare(strict_types=1);

$whatsappNumber = preg_replace(
    '/\D+/',
    '',
    (string) env('STOREFRONT_WHATSAPP_NUMBER', ''),
) ?: '';

return [
    'brand' => (string) env('STOREFRONT_BRAND_NAME', env('APP_NAME', 'ERP')),
    'tagline' => (string) env('STOREFRONT_BRAND_TAGLINE', 'Sistema de Gestión Empresarial'),
    'business_category' => (string) env('STOREFRONT_BUSINESS_CATEGORY', 'productos y servicios'),

    'contact' => [
        'whatsapp_number' => $whatsappNumber,
        'whatsapp_display' => (string) env('STOREFRONT_WHATSAPP_DISPLAY', ''),
        'whatsapp_url' => $whatsappNumber !== '' ? 'https://wa.me/'.$whatsappNumber : '#',
        'instagram_label' => (string) env('STOREFRONT_INSTAGRAM_LABEL', '@tuempresa'),
        'instagram_url' => (string) env('STOREFRONT_INSTAGRAM_URL', '#'),
        'email' => (string) env('STOREFRONT_CONTACT_EMAIL', 'contacto@example.com'),
        'location' => (string) env('STOREFRONT_CONTACT_LOCATION', 'Tu zona de atención'),
        'hours' => (string) env('STOREFRONT_CONTACT_HOURS', 'Horario a coordinar'),
        'map_url' => (string) env('STOREFRONT_CONTACT_MAP_URL', ''),
    ],

    'savings' => [
        // Mantener configurable: no se presenta como tarifa oficial vigente.
        'default_ticket_price' => (float) env('STOREFRONT_DEFAULT_TICKET_PRICE', 0),
    ],

    // Contenido editorial de respaldo. Nunca limita los modelos del panel.
    'models' => [
        [
            'slug' => 'q8-500',
            'name' => 'Q8-500W',
            'label' => '500W · 12Ah o 20Ah',
            'image' => 'images/motos/q8-500/q8-500-hero.webp',
            'description' => (
                'Modelo urbano compacto con motor de 500W, '
                .'respaldo para acompañante y distintas opciones '
                .'de batería.'
            ),
        ],
        [
            'slug' => 'q8-350',
            'name' => 'Q8-350W',
            'label' => '350W',
            'image' => 'images/motos/q8-350/q8-350-hero.webp',
            'description' => (
                'Una alternativa práctica para traslados urbanos, '
                .'con diseño compacto y bajo costo de uso.'
            ),
        ],
        [
            'slug' => 'sl-500',
            'name' => 'SL-500W',
            'label' => '500W · 20Ah',
            'image' => 'images/motos/sl-500/sl-500-hero.webp',
            'description' => (
                'Modelo urbano con doble faro delantero y opción '
                .'de agregar canasto frontal.'
            ),
        ],
    ],
];
