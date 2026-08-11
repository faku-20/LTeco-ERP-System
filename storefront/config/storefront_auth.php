<?php

declare(strict_types=1);

return [
    /*
     * La interfaz permanece oculta hasta completar
     * correo productivo y validación manual.
     */
    'accounts_enabled' => (bool) env(
        'STOREFRONT_ACCOUNTS_ENABLED',
        false,
    ),

    /*
     * El registro no debe habilitarse mientras el
     * mailer continúe usando el transporte "log".
     */
    'registration_enabled' => (bool) env(
        'STOREFRONT_REGISTRATION_ENABLED',
        false,
    ),
];
