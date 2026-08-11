<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\ReservationService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\EcommerceReservationRepository;

require_once dirname(__DIR__, 3) . '/includes/storefront_api.php';

storefrontApiRun('storefront.reservations.write', static function (): void {
    global $pdo;
    $id = (string) ($_GET['id'] ?? '');
    $hash = hash('sha256', 'DELETE\n' . strtolower($id));
    $result = (new ReservationService(new EcommerceReservationRepository(new Connection($pdo))))
        ->release($id, storefrontApiIdempotencyKey(), $hash);
    storefrontApiJson($result);
}, ['DELETE']);
