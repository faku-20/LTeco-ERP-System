<?php

declare(strict_types=1);

use Lteco\Application\Agenda\StorefrontVisitService;
use Lteco\Infrastructure\Db\Connection;

require_once dirname(__DIR__,3).'/includes/storefront_api.php';

storefrontApiRun('storefront.visits.write',static function():void{
    global$pdo;
    $body=storefrontApiJsonBody();
    $result=(new StorefrontVisitService(new Connection($pdo)))->create($body['payload'],storefrontApiIdempotencyKey(),$body['hash']);
    storefrontApiJson($result,201);
},['POST']);

