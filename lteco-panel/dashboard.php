<?php
$pageTitle = "Dashboard | ERP";

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . '/includes/flash.php';
requiereModulo('dashboard');
require_once __DIR__ . "/includes/helpers.php";

if (esDistribuidor()) {
    redirect(panelBaseUrl('repuestos/index.php'));
}

$usuario = usuarioActual();
$nombreUsuario = $usuario['NombreCompleto'] ?? 'usuario';
$tipoCambio = obtenerTipoCambioUSD($pdo);
$mesActual = date('Y-m');
$puedeVerFinanzas = esAdmin();
$puedeGestionarCatalogoWeb = esSuperadmin();
$auditoriaActiva = dbTieneTabla($pdo, 'auditoria');

$dashboard = (new \Lteco\Application\Dashboard\DashboardService(
    new \Lteco\Infrastructure\Repository\DashboardRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
))->cargar($tipoCambio, $puedeGestionarCatalogoWeb, $mesActual);

$resumen = $dashboard['resumen'];
$inventario = $dashboard['inventario'];
$clientesConDeuda = $dashboard['clientesConDeuda'];
$topClientes = $dashboard['topClientes'];
$ultimasVentas = $dashboard['ultimasVentas'];

require_once __DIR__ . "/includes/header.php";
require_once __DIR__ . "/includes/sidebar.php";
?>

<main class="main dashboard-page">

    <div class="topbar">
        <div>
            <h1>Bienvenido, <?= h($nombreUsuario) ?></h1>
            <p class="subtle">
                <?php
                $topbarResumen = [];
                if ($puedeVerFinanzas) {
                    $topbarResumen[] = 'TC: <strong>USD ' . number_format($tipoCambio, 2, ',', '.') . '</strong>';
                }
                if ($puedeGestionarCatalogoWeb) {
                    $topbarResumen[] = 'Web: <strong>' . (int)$inventario['publicadas_web'] . ' / ' . (int)$inventario['motos_disponibles'] . ' motos</strong>';
                }
                echo implode(' &nbsp;&middot;&nbsp; ', $topbarResumen);
                ?>
            </p>
        </div>
        <div class="actions-row">
            <?php if (esAdmin()): ?>
                <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/crear.php') ?>">+ Veh&iacute;culo</a>
            <?php endif; ?>
            <a class="btn" href="<?= panelBaseUrl('ventas/crear.php') ?>">+ Nueva venta</a>
        </div>
    </div>

    <?php if (esSuperadmin() && !$auditoriaActiva): ?>
        <div class="notice notice--warning">
            Auditoría no está activa. Ejecutá la migración <code>database/migrations/2026_05_28_panel_security_hardening.sql</code> antes de exponer el panel.
        </div>
    <?php endif; ?>

    <div class="cards cards--executive">
        <div class="card metric-card">
            <h3>Ventas activas</h3>
            <strong><?= (int)$resumen['ventas_activas'] ?></strong>
            <span><?= (int)$resumen['ventas_anuladas'] ?> anuladas</span>
        </div>
        <div class="card metric-card">
            <h3>Motos disponibles</h3>
            <strong><?= (int)$inventario['motos_disponibles'] ?></strong>
            <span><?= (int)$inventario['motos_vendidas'] ?> vendidas &middot; <?= (int)$inventario['motos_reservadas'] ?> reservadas</span>
        </div>
        <div class="card metric-card">
            <h3>Repuestos</h3>
            <strong><?= (int)$inventario['repuestos_unidades'] ?></strong>
            <span><?= (int)$inventario['repuestos_sku'] ?> tipos &middot; <?= (int)$inventario['repuestos_stock_bajo'] ?> stock bajo</span>
        </div>
        <?php if ((int)$resumen['ventas_pendientes'] > 0): ?>
        <div class="card metric-card card--border-blue">
            <h3>Ventas pendientes</h3>
            <strong><?= (int)$resumen['ventas_pendientes'] ?></strong>
            <span>Revisar seguimiento</span>
        </div>
        <?php endif; ?>
        <?php if ((int)$clientesConDeuda > 0): ?>
        <div class="card metric-card card--border-danger">
            <h3>Clientes con deuda</h3>
            <strong><?= (int)$clientesConDeuda ?></strong>
            <span><?= formatearMonto($resumen['saldo_pendiente_uyu'], 'UYU') ?> pendiente</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="two-column-grid">
        <div class="section-box dashboard-table-card">
            <h2 class="section-title">&#218;ltimas ventas</h2>
            <div class="table-wrap dashboard-table-wrap">
<table class="table">
                <thead><tr><th>Venta</th><th>Fecha</th><th>Total</th></tr></thead>
                <tbody>
                <?php if ($ultimasVentas): ?>
                    <?php foreach ($ultimasVentas as $venta): ?>
                        <tr>
                            <td><a href="<?= panelBaseUrl('ventas/detalle.php?id=' . (int)$venta['IdVenta']) ?>">#<?= h($venta['IdVenta']) ?></a></td>
                            <td><?= h(formatearFechaCorta($venta['FechaVenta'] ?? '')) ?></td>
                            <td><?= formatearMonto((float)($venta['Total'] ?? 0), $venta['Moneda'] ?? 'UYU') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Todav&iacute;a no hay ventas registradas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
</div>
        </div>
        <div class="section-box dashboard-table-card">
            <h2 class="section-title">Clientes m&aacute;s activos</h2>
            <div class="table-wrap dashboard-table-wrap">
<table class="table">
                <thead><tr><th>Cliente</th><th>Compras</th><?php if ($puedeVerFinanzas): ?><th>Total UYU</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if ($topClientes): ?>
                    <?php foreach ($topClientes as $cliente): ?>
                        <tr>
                            <td><?= h($cliente['NombreApellido']) ?><br><small><?= h($cliente['Telefono']) ?></small></td>
                            <td><?= (int)$cliente['Compras'] ?></td>
                            <?php if ($puedeVerFinanzas): ?>
                            <td><?= formatearMonto((float)$cliente['TotalGastadoUYU'], 'UYU') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?= $puedeVerFinanzas ? 3 : 2 ?>">Todav&iacute;a no hay clientes con compras.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
</div>
        </div>
    </div>

</main>



<?php require_once __DIR__ . "/includes/footer.php"; ?>
