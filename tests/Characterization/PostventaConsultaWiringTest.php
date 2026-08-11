<?php

declare(strict_types=1);

/**
 * Congela el cableado de las VISTAS de lectura de postventa hacia
 * Lteco\Application\Postventa\PostventaConsultaService, cerrando la Ola B
 * (Vehículos + Postventa) en su parte de lecturas.
 *
 * Igual que VentaListadoWiringTest / PostventaServiceWiringTest: el servicio y el
 * repositorio aislados pueden estar verdes, pero esto congela que cada página
 * DELEGUE la consulta en el servicio y ya NO contenga el SQL de lectura inline
 * (el SELECT grande con GROUP_CONCAT del listado, los $pdo->query de métricas y
 * recordatorios, ni los $pdo->prepare del detalle). Cubre:
 *   - postventa/index.php   -> $consulta->listado()
 *   - postventa/detalle.php -> $consulta->detalle()
 */
final class PostventaConsultaWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/';

        // --- index.php: listado + métricas + recordatorios delegados ---------
        $index = self::leer($panel . 'postventa/index.php');
        Assert::same(
            'Wiring postventa lectura',
            'index.php delega en $consulta->listado(',
            1,
            substr_count($index, '$consulta->listado(')
        );
        Assert::same(
            'Wiring postventa lectura',
            'index.php sin GROUP_CONCAT inline (SELECT del listado migrado)',
            0,
            substr_count($index, 'GROUP_CONCAT')
        );
        Assert::same(
            'Wiring postventa lectura',
            'index.php sin $pdo->prepare inline',
            0,
            substr_count($index, '$pdo->prepare')
        );
        Assert::same(
            'Wiring postventa lectura',
            'index.php sin $pdo->query inline',
            0,
            substr_count($index, '$pdo->query')
        );

        // --- detalle.php: vehículo + services + historiales delegados --------
        $detalle = self::leer($panel . 'postventa/detalle.php');
        Assert::same(
            'Wiring postventa lectura',
            'detalle.php delega en $consulta->detalle(',
            1,
            substr_count($detalle, '$consulta->detalle(')
        );
        Assert::same(
            'Wiring postventa lectura',
            'detalle.php sin $pdo->prepare inline',
            0,
            substr_count($detalle, '$pdo->prepare')
        );
        Assert::same(
            'Wiring postventa lectura',
            'detalle.php sin $pdo->query inline',
            0,
            substr_count($detalle, '$pdo->query')
        );
        Assert::same(
            'Wiring postventa lectura',
            'detalle.php sin dbTieneTabla inline (movido al repositorio)',
            0,
            substr_count($detalle, 'dbTieneTabla')
        );
    }

    private static function leer(string $ruta): string
    {
        $source = @file_get_contents($ruta);
        Assert::isTrue('Wiring postventa lectura', $ruta . ' legible', is_string($source) && $source !== '');

        return is_string($source) ? $source : '';
    }
}
