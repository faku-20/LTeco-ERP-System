<?php

declare(strict_types=1);

/**
 * C1.2: congela que lteco-panel/distribuidores/nuevo_pedido.php delegue el
 * NÚCLEO DE ESCRITURA del pedido auto-aprobado (lock del producto, INSERT
 * distribuidor_pedido, movimiento de stock interno→distribuidor y remito) en
 * Lteco\Application\Distribuidor\DistribuidorPedidoService, y ya NO contenga ese
 * SQL inline.
 *
 * Alcance acotado: SOLO nuevo_pedido.php. asignar_stock.php (C1.1) y
 * pedidos_admin.php NO se tocan en este bloque. El servicio reutiliza
 * DistribuidorStockService::asignarStock() de C1.1 para mover el stock.
 *
 * El handler conserva permisos, CSRF, parsing, transacción, auditoría y redirect.
 * Las lecturas del formulario y las validaciones contra DB también delegan.
 */
final class DistribuidorPedidoWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/nuevo_pedido.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring nuevo pedido', 'nuevo_pedido.php legible', $source !== '');

        // --- Delega el núcleo en el servicio -----------------------------------
        Assert::isTrue(
            'Wiring nuevo pedido',
            'usa DistribuidorPedidoService',
            strpos($source, 'DistribuidorPedidoService') !== false
        );
        Assert::same(
            'Wiring nuevo pedido',
            'delega en ->registrarPedidoAprobado(',
            1,
            substr_count($source, '->registrarPedidoAprobado(')
        );

        // --- SQL de escritura / lock que debe haber salido del handler ---------
        Assert::same(
            'Wiring nuevo pedido',
            'sin INSERT INTO distribuidor_pedido inline',
            0,
            substr_count($source, 'INSERT INTO distribuidor_pedido')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'sin INSERT INTO remito inline',
            0,
            substr_count($source, 'INSERT INTO remito')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'sin UPDATE producto inline',
            0,
            substr_count($source, 'UPDATE producto')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'sin lock FOR UPDATE inline',
            0,
            substr_count($source, 'FOR UPDATE')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'no llama distribuidorUpsertStock inline',
            0,
            substr_count($source, 'distribuidorUpsertStock(')
        );

        // --- Concerns que NO deben moverse al servicio -------------------------
        Assert::isTrue(
            'Wiring nuevo pedido',
            'conserva CSRF',
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring nuevo pedido',
            'conserva auditoría PEDIDO_DISTRIBUIDOR_APROBADO',
            strpos($source, "'PEDIDO_DISTRIBUIDOR_APROBADO'") !== false
        );
        Assert::isTrue(
            'Wiring nuevo pedido',
            'conserva apertura de transacción en el handler',
            substr_count($source, '$pdo->beginTransaction()') >= 1
        );
        // La decisión dbTieneTabla('remito') se conserva en el handler (se pasa
        // como flag al servicio).
        Assert::isTrue(
            'Wiring nuevo pedido',
            'conserva guard dbTieneTabla(remito) en el handler',
            strpos($source, "dbTieneTabla(\$pdo, 'remito')") !== false
        );
        Assert::same(
            'Wiring nuevo pedido',
            'delega chequeo de pedido duplicado',
            1,
            substr_count($source, '$pedidoService->vehiculoYaSolicitado(')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'delega datos del formulario',
            1,
            substr_count($source, '$pedidoService->datosFormularioPedido(')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'delega lookup del item',
            1,
            substr_count($source, '$pedidoService->itemSolicitable(')
        );
        Assert::same(
            'Wiring nuevo pedido',
            'sin prepare/query inline',
            0,
            preg_match('/\$pdo->(?:prepare|query)\s*\(/', $source)
        );
    }
}
