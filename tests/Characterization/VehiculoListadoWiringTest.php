<?php

declare(strict_types=1);

/**
 * Congela el cableado de la VISTA de listado de vehículos hacia
 * Lteco\Application\Vehiculo\VehiculoListadoService, completando las lecturas de
 * la Ola B (Vehículos + Postventa).
 *
 * Igual que VentaListadoWiringTest / PostventaConsultaWiringTest: el servicio y el
 * repositorio aislados pueden estar verdes, pero esto congela que la página
 * DELEGUE la consulta del listado y ya NO contenga el SELECT inline de vehiculo
 * (con su WHERE dinámico y la subquery de ImagenPrincipal).
 */
final class VehiculoListadoWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/';

        $source = @file_get_contents($panel . 'vehiculos/index.php');
        Assert::isTrue('Wiring vehiculos listado', 'index.php legible', is_string($source) && $source !== '');
        if (!is_string($source)) {
            return;
        }

        Assert::same(
            'Wiring vehiculos listado',
            'index.php delega en $listado->listar(',
            1,
            substr_count($source, '$listado->listar(')
        );
        Assert::same(
            'Wiring vehiculos listado',
            'index.php sin SELECT inline (FROM vehiculo v migrado)',
            0,
            substr_count($source, 'FROM vehiculo v')
        );
        Assert::same(
            'Wiring vehiculos listado',
            'index.php sin $pdo->prepare inline',
            0,
            substr_count($source, '$pdo->prepare')
        );
        Assert::same(
            'Wiring vehiculos listado',
            'index.php sin subquery ImagenPrincipal inline',
            0,
            substr_count($source, 'AS ImagenPrincipal')
        );
    }
}
