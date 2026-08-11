<?php

declare(strict_types=1);

/**
 * C1.1: congela que lteco-panel/distribuidores/asignar_stock.php delegue el
 * NÚCLEO DE ESCRITURA de asignación de stock (upsert distribuidor_stock +
 * descuento de producto + paso a 'Sin stock') en
 * Lteco\Application\Distribuidor\DistribuidorStockService y ya NO contenga ese
 * SQL/efecto inline.
 *
 * El helper legacy distribuidorUpsertStock() ya no tiene consumidores después
 * de C1 y se elimina en la limpieza final C7.
 *
 * El handler conserva sus concerns: guard (requiereAdmin), CSRF, validación del
 * request, lookup del producto, resolución de precios, apertura/cierre de la
 * transacción (el servicio es transaction-agnostic), auditoría y redirect.
 */
final class DistribuidorStockWiringTest
{
    public static function run(): void
    {
        $rutaHandler = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/asignar_stock.php';
        $rutaCommon  = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/_common.php';
        $handler = (string) @file_get_contents($rutaHandler);
        $common  = (string) @file_get_contents($rutaCommon);

        Assert::isTrue('Wiring asignar stock', 'asignar_stock.php legible', $handler !== '');
        Assert::isTrue('Wiring asignar stock', '_common.php legible', $common !== '');

        // --- Handler: delega el núcleo en el servicio --------------------------
        Assert::isTrue(
            'Wiring asignar stock',
            'usa DistribuidorStockService',
            strpos($handler, 'DistribuidorStockService') !== false
        );
        Assert::same(
            'Wiring asignar stock',
            'delega en ->asignarStock(',
            1,
            substr_count($handler, '->asignarStock(')
        );

        // El SQL de escritura que debe haber salido del handler.
        Assert::same(
            'Wiring asignar stock',
            'sin UPDATE producto inline',
            0,
            substr_count($handler, 'UPDATE producto')
        );
        // Ya no llama directamente al helper de upsert (lo hace el servicio).
        Assert::same(
            'Wiring asignar stock',
            'no llama distribuidorUpsertStock inline',
            0,
            substr_count($handler, 'distribuidorUpsertStock(')
        );
        Assert::same(
            'Wiring asignar stock',
            'sin prepare/query inline',
            0,
            preg_match('/\$pdo->(?:prepare|query)\s*\(/', $handler)
        );
        Assert::same(
            'Wiring asignar stock',
            'delega datos del formulario',
            1,
            substr_count($handler, '$stockService->datosFormularioAsignacion()')
        );
        Assert::same(
            'Wiring asignar stock',
            'delega lookup del item',
            1,
            substr_count($handler, '$stockService->itemDisponible(')
        );

        // --- Handler: concerns que NO deben moverse al servicio ----------------
        Assert::isTrue(
            'Wiring asignar stock',
            'conserva guard requiereAdmin',
            strpos($handler, 'requiereAdmin()') !== false
        );
        Assert::isTrue(
            'Wiring asignar stock',
            'conserva CSRF',
            strpos($handler, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring asignar stock',
            'conserva auditoría ASIGNAR_STOCK_DISTRIBUIDOR',
            strpos($handler, "'ASIGNAR_STOCK_DISTRIBUIDOR'") !== false
        );
        Assert::isTrue(
            'Wiring asignar stock',
            'conserva apertura de transacción en el handler',
            substr_count($handler, '$pdo->beginTransaction()') >= 1
        );

        // --- _common.php: no conserva persistencia legacy ---------------------
        Assert::same(
            'Wiring asignar stock',
            '_common elimina distribuidorUpsertStock sin uso',
            0,
            substr_count($common, 'function distribuidorUpsertStock(')
        );
        Assert::same(
            'Wiring asignar stock',
            '_common sin INSERT INTO distribuidor_stock inline',
            0,
            substr_count($common, 'INSERT INTO distribuidor_stock')
        );
        Assert::same(
            'Wiring asignar stock',
            '_common sin UPDATE distribuidor_stock SET Cantidad inline',
            0,
            substr_count($common, 'UPDATE distribuidor_stock SET Cantidad')
        );
    }
}
