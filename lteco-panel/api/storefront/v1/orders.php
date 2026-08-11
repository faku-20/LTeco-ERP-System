<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\StorefrontOrderService;
use Lteco\Infrastructure\Db\Connection;

require_once dirname(__DIR__, 3) . '/includes/storefront_api.php';

storefrontApiRun('storefront.orders.write', static function (): void {
    global $pdo;
    $body = storefrontApiJsonBody();
    $result = (new StorefrontOrderService(new Connection($pdo)))
        ->create($body['payload'], storefrontApiIdempotencyKey(), $body['hash']);
    storefrontApiJson($result, 201);
}, ['POST']);
