<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\StorefrontOrderStatusService;
use Lteco\Infrastructure\Db\Connection;

require_once dirname(__DIR__,3).'/includes/storefront_api.php';

storefrontApiRun('storefront.orders.read',static function():void{
    global$pdo;
    storefrontApiJson((new StorefrontOrderStatusService(new Connection($pdo)))->find((string)($_GET['uuid']??'')));
});
