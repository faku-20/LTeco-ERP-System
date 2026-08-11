<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'error' => [
        'code' => 'legacy_mercadopago_webhook_retired',
        'message' => 'El webhook MercadoPago legacy fue retirado. El ecommerce oficial opera por Storefront y Panel API.',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
