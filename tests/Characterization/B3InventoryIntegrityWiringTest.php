<?php

declare(strict_types=1);

final class B3InventoryIntegrityWiringTest
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        $ventaDistribuidor = (string) @file_get_contents($root . '/src/Application/Distribuidor/DistribuidorVentaService.php');
        $anulacion = (string) @file_get_contents($root . '/src/Application/Venta/VentaAnulacionService.php');
        $repoAnulacion = (string) @file_get_contents($root . '/src/Infrastructure/Repository/VentaAnulacionRepository.php');
        $estado = (string) @file_get_contents($root . '/src/Application/Vehiculo/VehiculoEstadoService.php');
        $crear = (string) @file_get_contents($root . '/src/Application/Vehiculo/VehiculoCrearService.php');
        $editar = (string) @file_get_contents($root . '/src/Application/Vehiculo/VehiculoEditarService.php');
        $repuestoEditar = (string) @file_get_contents($root . '/lteco-panel/repuestos/editar.php');
        $reconciliador = (string) @file_get_contents($root . '/src/Application/Inventario/InventarioReconciliadorService.php');
        $script = (string) @file_get_contents($root . '/lteco-panel/scripts/inventory_reconcile.php');

        Assert::isTrue('B3 wiring inventario', 'DistribuidorVentaService legible', $ventaDistribuidor !== '');
        Assert::same(
            'B3 wiring inventario',
            'venta distribuidor no descuenta stock global de repuesto',
            0,
            substr_count($ventaDistribuidor, 'descontarStockGlobalRepuesto(')
        );
        Assert::same(
            'B3 wiring inventario',
            'venta distribuidor descuenta solo una vez distribuidor_stock',
            1,
            substr_count($ventaDistribuidor, 'descontarStockDistribuidor(')
        );

        Assert::isTrue(
            'B3 wiring inventario',
            'anulación conoce venta de distribuidor',
            strpos($anulacion, '$idDistribuidor = (int) ($venta') !== false
        );
        Assert::isTrue(
            'B3 wiring inventario',
            'anulación repuesto distribuidor no restaura central',
            strpos($anulacion, "} elseif (\$idDistribuidor === 0)") !== false
        );
        Assert::isTrue(
            'B3 wiring inventario',
            'anulación vehículo distribuidor usa restauración separada',
            strpos($anulacion, 'restaurarVehiculoDistribuidor(') !== false
        );
        Assert::isTrue(
            'B3 wiring inventario',
            'repo anulación preserva stock central 0 para vehículo devuelto al distribuidor',
            strpos($repoAnulacion, "SET Stock = 0,\n                Estado = 'Sin stock'") !== false
        );

        foreach ([
            'VehiculoEstadoService' => $estado,
            'VehiculoCrearService' => $crear,
            'VehiculoEditarService' => $editar,
        ] as $nombre => $source) {
            Assert::isTrue('B3 wiring vehículo', $nombre . ' bloquea Vendido manual', strpos($source, "estado Vendido sólo puede originarse") !== false);
        }

        Assert::isTrue(
            'B3 wiring ajuste stock',
            'editar repuesto exige motivo si cambia stock',
            strpos($repuestoEditar, '$stock !== $stockAnterior') !== false
                && strpos($repuestoEditar, 'motivo_stock') !== false
                && strpos($repuestoEditar, 'Indicá el motivo del ajuste de stock.') !== false
        );
        Assert::isTrue(
            'B3 wiring ajuste stock',
            'auditoría registra stock anterior/nuevo/motivo',
            strpos($repuestoEditar, "'stock_anterior'") !== false
                && strpos($repuestoEditar, "'stock_nuevo'") !== false
                && strpos($repuestoEditar, "'motivo_stock'") !== false
        );

        Assert::isTrue('B3 reconciliador', 'servicio reconciliador existe', $reconciliador !== '');
        Assert::same('B3 reconciliador', 'servicio no contiene INSERT', 0, preg_match('/\bINSERT\b/i', $reconciliador));
        Assert::same('B3 reconciliador', 'servicio no contiene UPDATE', 0, preg_match('/\bUPDATE\b/i', $reconciliador));
        Assert::same('B3 reconciliador', 'servicio no contiene DELETE', 0, preg_match('/\bDELETE\b/i', $reconciliador));
        Assert::isTrue('B3 reconciliador', 'script CLI existe', $script !== '');
        Assert::isTrue('B3 reconciliador', 'script usa InventarioReconciliadorService', strpos($script, 'InventarioReconciliadorService') !== false);
    }
}
