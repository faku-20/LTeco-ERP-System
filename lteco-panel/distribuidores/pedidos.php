<?php
$pageTitle = "Pedidos distribuidor | Lteco";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

requiereLogin();

if (esDistribuidor()) {
    $idDistribuidor = requiereDistribuidorPanel();
} else {
    requiereAdmin();
    $idDistribuidor = null;
}

$consulta = new \Lteco\Application\Distribuidor\DistribuidorConsultaService(
    new \Lteco\Infrastructure\Repository\DistribuidorConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$pedidos = $consulta->pedidos($idDistribuidor);

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Pedidos</h1>
            <p class="subtle"><?= esDistribuidor() ? 'Historial de solicitudes de stock.' : 'Solicitudes de stock de distribuidores.' ?></p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
            <a class="btn" href="<?= panelBaseUrl('distribuidores/nuevo_pedido.php') ?>">+ Nuevo pedido</a>
        </div>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (esAdmin()): ?><th>Distribuidor</th><?php endif; ?>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <?php
                    $estadoClase = match ((string)$p['Estado']) {
                        'Aprobado' => 'sale-state--confirmada',
                        'Rechazado' => 'sale-state--anulada',
                        default => 'sale-state--pendiente',
                    };
                    ?>
                    <tr>
                        <td>#<?= (int)$p['IdPedido'] ?></td>
                        <?php if (esAdmin()): ?><td><?= h($p['DistribuidorNombre'], '') ?></td><?php endif; ?>
                        <td><strong><?= h(distribuidorItemLabel($p), '') ?></strong></td>
                        <td><?= h($p['TipoItem'], '') ?></td>
                        <td><?= (int)$p['Cantidad'] ?></td>
                        <td><span class="sale-state <?= $estadoClase ?>"><?= h($p['Estado'], '') ?></span></td>
                        <td><?= h($p['FechaPedido'], '') ?></td>
                        <td><?= h($p['Observaciones'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pedidos): ?>
                    <tr><td colspan="<?= esAdmin() ? 8 : 7 ?>" class="help-text">No hay pedidos registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
