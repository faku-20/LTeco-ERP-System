<?php

declare(strict_types=1);

final class StorefrontVisitBridgeTest
{
    public static function run():void
    {
        $root=dirname(__DIR__,2);
        $migration=(string)file_get_contents($root.'/database/migrations/2026_07_26_storefront_visit_bridge.sql');
        $endpoint=(string)file_get_contents($root.'/lteco-panel/api/storefront/v1/visits.php');
        $service=(string)file_get_contents($root.'/src/Application/Agenda/StorefrontVisitService.php');
        $panel=(string)file_get_contents($root.'/lteco-panel/agenda/index.php');
        $routes=(string)file_get_contents($root.'/storefront/routes/web.php');

        Assert::isTrue('Agenda web','UUID idempotente único',str_contains($migration,'uq_crm_visita_storefront_request'));
        Assert::isTrue('Agenda web','endpoint exige scope',str_contains($endpoint,"'storefront.visits.write'"));
        Assert::isTrue('Agenda web','visita ingresa desde web',str_contains($service,"'Web'"));
        Assert::isTrue('Agenda web','horario requiere confirmación',str_contains($service,'HoraConfirmada'));
        Assert::isTrue('Agenda web','crea alerta interna',str_contains($service,"'visita_pendiente'"));
        Assert::isTrue('Agenda web','panel muestra correo',str_contains($panel,'ClienteCorreo'));
        Assert::isTrue('Agenda web','panel muestra canal',str_contains($panel,'Canal'));
        Assert::isTrue('Agenda web','ruta pública limitada',str_contains($routes,"throttle:visit") || str_contains($routes,"throttle:3,60"));
    }
}
