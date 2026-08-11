<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\PedidoPanelService;
use Lteco\Application\Ecommerce\StorefrontOrderStatusService;
use Lteco\Application\Ecommerce\StorefrontApiException;
use Lteco\Infrastructure\Db\Connection;

require_once dirname(__DIR__, 3) . '/includes/storefront_api.php';

storefrontApiRun('storefront.payments.write', static function (): void {
    global $pdo;
    if (!storefrontPaymentSimulatorEnabled()) {
        throw new StorefrontApiException(403, 'payment_simulator_disabled', 'La operación no está disponible.');
    }
    $orderUuid = (string) ($_GET['uuid'] ?? '');
    (new PedidoPanelService(new Connection($pdo)))->confirmarPagoTarjetaSimulada($orderUuid);
    storefrontApiJson((new StorefrontOrderStatusService(new Connection($pdo)))->find($orderUuid));
}, ['POST']);
