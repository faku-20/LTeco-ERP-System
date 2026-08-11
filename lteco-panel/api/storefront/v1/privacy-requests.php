<?php
declare(strict_types=1);
use Lteco\Application\Ecommerce\StorefrontPrivacyRequestService;use Lteco\Infrastructure\Db\Connection;
require_once dirname(__DIR__,3).'/includes/storefront_api.php';
storefrontApiRun('storefront.privacy.write',static function():void{global$pdo;$body=storefrontApiJsonBody();storefrontApiJson((new StorefrontPrivacyRequestService(new Connection($pdo)))->create($body['payload']),201);},['POST']);
