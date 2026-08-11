<?php

declare(strict_types=1);

/**
 * C1.3: congela que lteco-panel/distribuidores/pedidos_admin.php delegue el
 * NÚCLEO DE ESCRITURA de la resolución admin de pedidos (lock del producto,
 * validación de stock, movimiento de stock interno→distribuidor, remito y el
 * cambio de Estado del pedido) en
 * Lteco\Application\Distribuidor\DistribuidorPedidoService::resolverPedido(), y
 * ya NO contenga ese SQL inline.
 *
 * Alcance acotado: SOLO pedidos_admin.php. asignar_stock.php (C1.1) y
 * nuevo_pedido.php (C1.2) NO se tocan en este bloque. El servicio reutiliza el
 * lock/validación de DistribuidorPedidoRepository, DistribuidorStockService::
 * asignarStock() (C1.1) y DistribuidorPedidoRepository::crearRemitoPendiente()
 * (C1.2).
 *
 * El handler conserva sus concerns: permisos (requiereAdmin), CSRF, parsing del
 * request, apertura/cierre de la transacción, auditoría, WhatsApp, flash y
 * redirect. Las lecturas del pedido y del listado también delegan al servicio.
 */
final class DistribuidorPedidoAdminWiringTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/pedidos_admin.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring pedidos admin', 'pedidos_admin.php legible', $source !== '');

        // --- Delega el núcleo en el servicio -----------------------------------
        Assert::isTrue(
            'Wiring pedidos admin',
            'usa DistribuidorPedidoService',
            strpos($source, 'DistribuidorPedidoService') !== false
        );
        Assert::same(
            'Wiring pedidos admin',
            'delega en ->resolverPedido(',
            1,
            substr_count($source, '->resolverPedido(')
        );

        // --- SQL de escritura / lock que debe haber salido del handler ---------
        Assert::same(
            'Wiring pedidos admin',
            'sin UPDATE distribuidor_pedido inline',
            0,
            substr_count($source, 'UPDATE distribuidor_pedido')
        );
        Assert::same(
            'Wiring pedidos admin',
            'sin INSERT INTO remito inline',
            0,
            substr_count($source, 'INSERT INTO remito')
        );
        Assert::same(
            'Wiring pedidos admin',
            'sin UPDATE producto inline',
            0,
            substr_count($source, 'UPDATE producto')
        );
        Assert::same(
            'Wiring pedidos admin',
            'sin lock FOR UPDATE inline',
            0,
            substr_count($source, 'FOR UPDATE')
        );
        Assert::same(
            'Wiring pedidos admin',
            'no llama distribuidorUpsertStock inline',
            0,
            substr_count($source, 'distribuidorUpsertStock(')
        );

        // --- Concerns que NO deben moverse al servicio -------------------------
        Assert::isTrue(
            'Wiring pedidos admin',
            'conserva guard requiereAdmin',
            strpos($source, 'requiereAdmin()') !== false
        );
        Assert::isTrue(
            'Wiring pedidos admin',
            'conserva CSRF',
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring pedidos admin',
            'conserva auditoría PEDIDO_DISTRIBUIDOR_',
            strpos($source, "'PEDIDO_DISTRIBUIDOR_' . strtoupper(\$accion)") !== false
        );
        Assert::isTrue(
            'Wiring pedidos admin',
            'conserva apertura de transacción en el handler',
            substr_count($source, '$pdo->beginTransaction()') >= 1
        );
        // La decisión dbTieneTabla('remito') se conserva en el handler (se pasa
        // como flag al servicio).
        Assert::isTrue(
            'Wiring pedidos admin',
            'conserva guard dbTieneTabla(remito) en el handler',
            strpos($source, "dbTieneTabla(\$pdo, 'remito')") !== false
        );
        Assert::same(
            'Wiring pedidos admin',
            'delega lectura del pedido pendiente',
            1,
            substr_count($source, '$pedidoService->buscarPendiente($idPedido)')
        );
        Assert::same(
            'Wiring pedidos admin',
            'delega listado de pedidos pendientes',
            1,
            substr_count($source, '$pedidoService->listarPendientes()')
        );
        Assert::same(
            'Wiring pedidos admin',
            'sin SELECT inline',
            0,
            preg_match('/["\']\s*SELECT\b/i', $source)
        );
        Assert::same(
            'Wiring pedidos admin',
            'sin query builder legacy',
            0,
            substr_count($source, 'distribuidorPedidoQueryBase(')
        );
        Assert::same(
            'Wiring pedidos admin',
            'modal sin getElementById corrupto',
            0,
            substr_count($source, "document.getElementById(' document.getElementById")
        );
        // Preserva la notificación WhatsApp y el flash de resolución.
        Assert::isTrue(
            'Wiring pedidos admin',
            'conserva notificación WhatsApp',
            strpos($source, 'distribuidorWhatsappNotificar(') !== false
        );
    }
}
