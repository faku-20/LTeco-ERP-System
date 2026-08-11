<?php
$pageTitle = "Detalle de cliente | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
requiereModulo("clientes");
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Cliente\ClienteConsultaService(
    new \Lteco\Infrastructure\Repository\ClienteConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

if (!isset($_GET['id']) || trim((string)$_GET['id']) === '') {
    redirectWithFlash(panelBaseUrl('clientes/index.php'), 'error', 'ID de cliente no recibido.');
}

$idCliente = (int)trim((string)$_GET['id']);
if ($idCliente <= 0) {
    redirectWithFlash(panelBaseUrl('clientes/index.php'), 'error', 'ID de cliente inválido.');
}
requierePuedeVerRegistro('cliente', $idCliente);

$cliente = $service->cliente($idCliente);

if (!$cliente) {
    redirectWithFlash(panelBaseUrl('clientes/index.php'), 'error', 'Cliente no encontrado.');
}

$tipoCambio = obtenerTipoCambioUSD($pdo);
$idUsuarioVendedor = esVendedor() ? (int)(usuarioActual()['IdUsuario'] ?? 0) : 0;

$ventas = $service->ventas($idCliente, $idUsuarioVendedor);
$detallesPorVenta = $service->detallesPorVenta($idCliente, $idUsuarioVendedor);

$comprasActivas = 0;
$totalGastadoUYU = 0.0;
$saldoPendienteUYU = 0.0;
foreach ($ventas as $venta) {
    if (($venta['EstadoVenta'] ?? 'Confirmada') === 'Anulada') {
        continue;
    }
    $comprasActivas++;
    $moneda = (string)($venta['Moneda'] ?? 'UYU');
    $totalGastadoUYU += convertirMontoVentaAUyu((float)($venta['Total'] ?? 0), $moneda, $venta, $tipoCambio);
    $saldoPendienteUYU += convertirMontoVentaAUyu((float)($venta['SaldoPendiente'] ?? 0), $moneda, $venta, $tipoCambio);
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Detalle de cliente #<?= h($cliente['IdCliente'], '') ?></h1>
            <p class="subtle">Historial construido desde ventas y venta_detalle.</p>
        </div>
        <div class="u-flex-gap-10">
            <!-- LTECO:CLIENTE_EDITAR_SOLO_ADMIN_DETALLE -->

            <?php if (esAdmin()): ?>

                <a class="btn" href="<?= panelBaseUrl('clientes/editar.php?id=' . urlencode((string)$cliente['IdCliente'])) ?>">Editar cliente</a>

            <?php endif; ?>
            <a class="btn-secondary" href="<?= panelBaseUrl('clientes/index.php') ?>">Volver</a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <div class="cards cards--compact">
        <div class="card"><h3>Compras activas</h3><strong><?= (int)$comprasActivas ?></strong><span><?= count($ventas) ?> ventas totales</span></div>
        <div class="card"><h3>Total gastado</h3><strong><?= formatearMonto($totalGastadoUYU, 'UYU') ?></strong><br><span>Convertido a pesos</span></div>
        <div class="card <?= $saldoPendienteUYU > 0 ? 'card--attention' : '' ?>"><h3>Saldo pendiente</h3><strong><?= formatearMonto($saldoPendienteUYU, 'UYU') ?></strong><br><span>Deuda actual</span></div>
    </div>

    <div class="section-box">
        <div class="summary-grid-3">
            <div><strong>Tipo de cliente</strong><br><?= h($cliente['TipoFiscal'] ?? (($cliente['RUT'] ?? '') !== '' ? 'Empresa/RUT' : 'Consumidor final'), '-') ?></div>
            <div><strong>Nombre</strong><br><?= h($cliente['NombreApellido'] ?? '-', '-') ?></div>
            <div><strong>Teléfono</strong><br><?= h($cliente['Telefono'] ?? '-', '-') ?></div>
            <div><strong>Correo</strong><br><?= h($cliente['Correo'] ?? '-', '-') ?></div>
            <div><strong>Cédula</strong><br><?= h($cliente['Cedula'] ?? '-', '-') ?></div>
            <div><strong>RUT</strong><br><?= h($cliente['RUT'] ?? '-', '-') ?></div>
            <div><strong>Dirección</strong><br><?= h($cliente['Direccion'] ?? '-', '-') ?></div>
        </div>
    </div>

    <div class="section-box">
        <div class="section-head">
            <div>
                <h2 class="section-title">Historial de ventas</h2>
                <p class="subtle">Cada compra lista los productos vendidos y su estado de pago.</p>
            </div>
            <a class="btn" href="<?= panelBaseUrl('ventas/crear.php') ?>">+ Nueva venta</a>
        </div>

        <?php if (!$ventas): ?>
            <p>Este cliente todavía no tiene ventas registradas.</p>
        <?php else: ?>
                <div class="table-wrap">

                <table class="table">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Total</th>
                            <th>Pagado</th>
                            <th>Saldo</th>
                            <th>Productos</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <?php
                            $idVenta = (int)$venta['IdVenta'];
                            $estado = (string)($venta['EstadoVenta'] ?? 'Confirmada');
                            $ventaAnulada = $estado === 'Anulada';
                            $saldo = $ventaAnulada ? 0.0 : (float)($venta['SaldoPendiente'] ?? 0);
                            ?>
                            <tr class="<?= $ventaAnulada ? 'anulada-row' : '' ?>">
                                <td>#<?= $idVenta ?></td>
                                <td><?= h(formatearFechaCorta($venta['FechaVenta'] ?? ''), '') ?></td>
                                <td><span class="badge <?= claseBadgeEstado($estado) ?>"><?= h($estado, '') ?></span></td>
                                <td><?= h($venta['MetodoPago'] ?? '-', '-') ?></td>
                                <td><?= formatearMonto((float)($venta['Total'] ?? 0), $venta['Moneda'] ?? 'UYU') ?></td>
                                <td><?= formatearMonto((float)($venta['MontoPagado'] ?? 0), $venta['Moneda'] ?? 'UYU') ?></td>
                                <td>
                                    <?php if ($ventaAnulada): ?>
                                        <span class="text-muted">No aplica</span>
                                    <?php elseif ($saldo > 0): ?>
                                        <span class="text-danger text-bold-strong"><?= formatearMonto($saldo, $venta['Moneda'] ?? 'UYU') ?></span>
                                    <?php else: ?>
                                        <?= formatearMonto(0, $venta['Moneda'] ?? 'UYU') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($detallesPorVenta[$idVenta])): ?>
                                        <ul class="compact-list">
                                            <?php foreach ($detallesPorVenta[$idVenta] as $detalle): ?>
                                                <li>
                                                    <?= h($detalle['TipoProducto'] === 'Moto' ? ($detalle['Modelo'] ?: $detalle['Nombre']) : $detalle['Nombre'], '-') ?>
                                                    × <?= (int)($detalle['Cantidad'] ?? 1) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><a class="btn-secondary btn-small" href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$idVenta)) ?>">Ver venta</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
