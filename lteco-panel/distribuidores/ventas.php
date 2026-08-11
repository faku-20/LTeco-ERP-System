<?php
$pageTitle = "Ventas distribuidor | Lteco";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

$idDistribuidor = esDistribuidor() ? requiereDistribuidorPanel() : 0;
if (!esDistribuidor()) {
    requiereAdmin();
    $idDistribuidor = (int)($_GET['id'] ?? 0);
}

$consulta = new \Lteco\Application\Distribuidor\DistribuidorConsultaService(
    new \Lteco\Infrastructure\Repository\DistribuidorConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$listado = $consulta->ventas($idDistribuidor);
$ventas = $listado['ventas'];
$total = $listado['total'];
$ventasActivas = $listado['ventasActivas'];
$ventasAnuladas = $listado['ventasAnuladas'];

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Ventas distribuidor</h1>
            <p class="subtle"><?= (int)$ventasActivas ?> activas · <?= (int)$ventasAnuladas ?> anuladas · <?= formatearMonto($total, 'UYU') ?></p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
            <?php if (esDistribuidor()): ?>
                <a class="btn" href="<?= panelBaseUrl('distribuidores/nueva_venta.php') ?>">+ Nueva venta</a>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (esAdmin()): ?><th>Distribuidor</th><?php endif; ?>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Método</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th>Ganancia real</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventas as $venta): ?>
                    <?php $estadoVenta = (string)($venta['EstadoVenta'] ?? 'Confirmada'); ?>
                    <tr class="<?= $estadoVenta === 'Anulada' ? 'anulada-row' : '' ?>">
                        <td>#<?= (int)$venta['IdVenta'] ?></td>
                        <?php if (esAdmin()): ?><td><?= h($venta['DistribuidorNombre'] ?? '-') ?></td><?php endif; ?>
                        <td><?= h($venta['NombreApellido'] ?? 'Sin cliente') ?></td>
                        <td><?= h($venta['FechaVenta'] ?? '-') ?></td>
                        <td><?= h($venta['MetodoPago'] ?? '-') ?></td>
                        <td><?= h($estadoVenta) ?></td>
                        <td><?= formatearMonto((float)$venta['Total'], $venta['Moneda'] ?? 'UYU') ?></td>
                        <td><?= formatearMonto((float)$venta['GananciaEstimada'], $venta['Moneda'] ?? 'UYU') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$ventas): ?>
                    <tr><td colspan="<?= esAdmin() ? 8 : 7 ?>" class="help-text">No hay ventas registradas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
