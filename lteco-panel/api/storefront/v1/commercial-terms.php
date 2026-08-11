<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\CommercialTermsService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\EcommerceCommercialTermsRepository;

require_once dirname(__DIR__, 3) . '/includes/storefront_api.php';

storefrontApiRun('storefront.catalog.read', static function (): void {
    global $pdo;
    $current = (new EcommerceCommercialTermsRepository(new Connection($pdo)))->current(defaultTasaIVA());
    $result = (new CommercialTermsService())->terms(
        $current['config'],
        cuotasTarjetaPorMarcaSistema(),
        $current['effective_at'],
    );
    storefrontApiJson($result, 200, (string) $result['data']['version']);
});
