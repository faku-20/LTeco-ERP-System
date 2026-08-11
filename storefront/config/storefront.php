<?php

declare(strict_types=1);

return [
    'payment_simulator' => [
        'enabled' => env('STOREFRONT_PAYMENT_SIMULATOR_ENABLED', false),
    ],

    'whatsapp_number' => env(
        'STOREFRONT_WHATSAPP_NUMBER',
        ''
    ),

    'models' => [
        'q8-500' => [
            'name' => 'Q8-500W',
            'aliases' => [
                'q8 500',
                'q8-500',
                'q8 500w',
                'q8-500w',
            ],
            'description' => (
                'Modelo urbano compacto con motor de 500W, '
                . 'respaldo para acompañante y opciones de batería de 12Ah o 20Ah.'
            ),
            'fallback_price' => 63000,
            'battery_options' => [12 => 63000, 20 => 67000],
            'images' => [
                'images/motos/q8-500/q8-500-hero.webp',
                'images/motos/q8-500/q8-500-02.webp',
                'images/motos/q8-500/q8-500-03.webp',
            ],
        ],

        'q8-350' => [
            'name' => 'Q8-350W',
            'aliases' => [
                'q8 350',
                'q8-350',
                'q8 350w',
                'q8-350w',
            ],
            'description' => (
                'Modelo urbano de 350W, práctico para traslados cotidianos.'
            ),
            'fallback_price' => 57600,
            'images' => [
                'images/motos/q8-350/q8-350-hero.webp',
                'images/motos/q8-350/q8-350-02.webp',
                'images/motos/q8-350/q8-350-03.webp',
                'images/motos/q8-350/q8-350-04.webp',
            ],
        ],

        'sl-500' => [
            'name' => 'SL-500W',
            'aliases' => [
                'sl 500',
                'sl-500',
                'sl 500w',
                'sl-500w',
            ],
            'description' => (
                'Modelo urbano de 500W y 20Ah. El canasto delantero '
                . 'se ofrecerá como accesorio opcional.'
            ),
            'fallback_price' => 65000,
            'battery_options' => [20 => 65000],
            'images' => [
                'images/motos/sl-500/sl-500-hero.webp',
                'images/motos/sl-500/sl-500-02.webp',
                'images/motos/sl-500/sl-500-03.webp',
            ],
        ],

    ],
];
