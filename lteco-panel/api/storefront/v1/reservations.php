<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\ReservationService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\EcommerceReservationRepository;

require_once dirname(__DIR__, 3) . '/includes/storefront_api.php';

storefrontApiRun('storefront.reservations.write', static function (): void {
    global $pdo;
    $body = storefrontApiJsonBody();
    $result = (new ReservationService(new EcommerceReservationRepository(new Connection($pdo))))
        ->create($body['payload'], storefrontApiIdempotencyKey(), $body['hash']);
    storefrontApiJson($result, 201);
}, ['POST']);
