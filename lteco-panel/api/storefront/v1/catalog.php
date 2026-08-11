<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\CatalogService;
use Lteco\Application\Ecommerce\PublicMediaUrl;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\EcommerceCatalogRepository;

require_once dirname(__DIR__, 3) . '/includes/storefront_api.php';

storefrontApiRun('storefront.catalog.read', static function (): void {
    global $pdo;
    $origin = rtrim((string) configEnv('LTECO_MEDIA_PUBLIC_URL', requestBaseUrl() . panelBaseUrl()), '/');
    $result = (new CatalogService(
        new EcommerceCatalogRepository(new Connection($pdo)),
        new PublicMediaUrl($origin),
    ))->catalog(gmdate('Y-m-d\TH:i:s\Z'));
    storefrontApiJson($result, 200, (string) $result['meta']['version']);
});
