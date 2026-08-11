<?php

declare(strict_types=1);

/**
 * C2 completo: congela que nueva_venta.php delegue preparación, persistencia,
 * descuento de stock, efectos de vehículo y facturación de remito en
 * DistribuidorVentaService.
 *
 * El handler conserva transacción, auditoría, redirect y UI.
 */
final class VentaDistribuidorWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/nueva_venta.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring venta distribuidor C2', 'nueva_venta.php legible', $source !== '');

        Assert::isTrue(
            'Wiring venta distribuidor C2',
            'usa DistribuidorVentaService',
            strpos($source, 'DistribuidorVentaService') !== false
        );
        Assert::same(
            'Wiring venta distribuidor C2',
            'delega preparación con lock',
            1,
            substr_count($source, '->prepararVenta(')
        );
        Assert::same(
            'Wiring venta distribuidor C2',
            'delega registro de venta',
            1,
            substr_count($source, '->registrarVenta(')
        );
        Assert::same(
            'Wiring venta distribuidor C2',
            'delega facturación de remito',
            1,
            substr_count($source, '->facturarRemito(')
        );

        foreach ([
            'FOR UPDATE',
            'INSERT INTO venta ',
            'INSERT INTO venta_detalle',
            'UPDATE distribuidor_stock SET Cantidad',
            'UPDATE producto SET Stock = GREATEST',
            'UPDATE remito',
        ] as $sqlMigrado) {
            Assert::same(
                'Wiring venta distribuidor C2',
                'sin SQL inline: ' . $sqlMigrado,
                0,
                substr_count($source, $sqlMigrado)
            );
        }

        Assert::isTrue(
            'Wiring venta distribuidor C2',
            'cliente final delega en ClienteCrudService',
            strpos($source, 'ClienteCrudService') !== false
                && strpos($source, '->listarParaSelector(') !== false
                && strpos($source, '$clienteService->crear(') !== false
                && strpos($source, '$clienteService->obtener(') !== false
        );
        Assert::isTrue(
            'Wiring venta distribuidor C2',
            'configuración comercial delegada',
            strpos($source, 'VentaCommercialService') !== false
                && strpos($source, '->usuarioInternoDistribuidor(') !== false
        );
        Assert::same(
            'Wiring venta distribuidor C2',
            'delega comisiones una vez',
            1,
            substr_count($source, '->registrarComisiones(')
        );
        Assert::same('Wiring venta distribuidor C2', 'sin SQL cliente inline', 0, substr_count($source, 'INSERT INTO cliente'));
        Assert::same('Wiring venta distribuidor C2', 'sin SQL gasto inline', 0, substr_count($source, 'INSERT INTO gasto'));
        Assert::isTrue(
            'Wiring venta distribuidor C2',
            'handler conserva transacción',
            strpos($source, '$pdo->beginTransaction()') !== false
                && strpos($source, '$pdo->commit()') !== false
                && strpos($source, '$pdo->rollBack()') !== false
        );
        Assert::isTrue(
            'Wiring venta distribuidor C2',
            'handler conserva auditoría',
            strpos($source, "'VENTA_DISTRIBUIDOR'") !== false
        );
    }
}
