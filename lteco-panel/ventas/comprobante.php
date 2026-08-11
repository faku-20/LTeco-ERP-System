<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereModulo("ventas");
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/shared/comprobante_verificacion.php';

$configNegocio = obtenerConfiguracionNegocio($pdo);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    redirectWithFlash(panelBaseUrl('ventas/index.php'), 'error', 'Venta inválida.');
}
requierePuedeVerRegistro('venta', $id);

$ventaQuery = new \Lteco\Application\Venta\VentaQueryService(
    new \Lteco\Infrastructure\Repository\VentaReadRepository(
        \Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);
$venta = $ventaQuery->ventaConCliente($id);

if (!$venta) {
    redirectWithFlash(panelBaseUrl('ventas/index.php'), 'error', 'Venta no encontrada.');
}

$detalles = $ventaQuery->detalles($id);

$nombreEmpresa = $configNegocio['NombreEmpresa'] ?? appName();
$direccionEmpresa = $configNegocio['Direccion'] ?? '';
$logoEmpresa = $configNegocio['Logo'] ?? '';
$textoComprobante = trim((string)($configNegocio['TextoComprobante'] ?? ''));
$whatsappEmpresa = preg_replace('/\D+/', '', (string)($configNegocio['Whatsapp'] ?? ''));
$emailEmpresa = trim((string)($configNegocio['Correo'] ?? ''));
$telefonoEmpresa = trim((string)($configNegocio['Telefono'] ?? ''));

$estado = (string)($venta['EstadoVenta'] ?? 'Confirmada');
$estadoClase = 'estado-' . strtolower($estado);
$monedaVenta = (string)($venta['Moneda'] ?? 'UYU');
$numeroComprobante = \Lteco\Support\VentaView::numeroComprobante($venta);
$tipoCambioAplicado = (float)($venta['TipoCambioAplicado'] ?? 0);
$metodoPagoTexto = \Lteco\Support\VentaView::metodoPagoComprobante($venta);
$saldoPendiente = (float)($venta['SaldoPendiente'] ?? 0);
$totalVenta = \Lteco\Support\VentaView::montoComprobante($venta['Total'] ?? 0, $monedaVenta);
$montoPagado = \Lteco\Support\VentaView::montoComprobante($venta['MontoPagado'] ?? 0, $monedaVenta);
$saldoTexto = \Lteco\Support\VentaView::montoComprobante($saldoPendiente, $monedaVenta);
$enlaceVenta = comprobanteVerificacionUrl($venta);

$mensajeWhatsapp = \Lteco\Support\VentaView::mensajeComprobante(
    $venta,
    $detalles,
    $nombreEmpresa,
    formatearFechaCorta($venta['FechaVenta'] ?? ''),
    $enlaceVenta
);

$linkWhatsapp = linkWhatsappPanel($venta['Telefono'] ?? null, $mensajeWhatsapp)
    ?? linkWhatsappPanel($whatsappEmpresa, $mensajeWhatsapp)
    ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante <?= h($numeroComprobante) ?></title>
    <link rel="stylesheet" href="<?= panelBaseUrl('assets/css/lteco.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/lteco.css') ?>">
</head>
<body class="comprobante-page">
    <div class="comprobante">
        <header class="comprobante-header">
            <div class="comprobante-brand">
                <?php if (!empty($logoEmpresa)): ?>
                    <img src="<?= h($logoEmpresa) ?>" alt="Logo" class="logo">
                <?php endif; ?>
                <div>
                    <span class="eyebrow">Comprobante interno</span>
                    <h1><?= h($nombreEmpresa) ?></h1>
                    <?php if ($direccionEmpresa !== ''): ?><p><?= h($direccionEmpresa) ?></p><?php endif; ?>
                    <?php if ($telefonoEmpresa !== '' || $emailEmpresa !== ''): ?>
                        <p><?= h(trim($telefonoEmpresa . ' ' . $emailEmpresa)) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="comprobante-meta-card">
                <strong>Comprobante <?= h($numeroComprobante) ?></strong>
                <span>Fecha: <?= h(formatearFechaCorta($venta['FechaVenta'] ?? '')) ?></span>
                <span>Moneda: <?= h($monedaVenta) ?></span>
                <span class="estado <?= h($estadoClase) ?>"><?= h($estado) ?></span>
            </div>
        </header>

        <?php if ($estado === 'Anulada'): ?>
            <div class="alerta-anulada">⚠️ ESTA VENTA FUE ANULADA</div>
        <?php endif; ?>

        <?php if ($saldoPendiente > 0): ?>
            <div class="alerta-saldo">⚠️ Esta venta tiene saldo pendiente: <?= h($saldoTexto) ?></div>
        <?php endif; ?>

        <section class="comprobante-grid-info">
            <div class="bloque">
                <h3>Cliente</h3>
                <p>
                    <strong><?= h($venta['NombreApellido'] ?? 'Sin cliente') ?></strong><br>
                    Tipo: <?= h($venta['TipoFiscal'] ?? 'Consumidor final') ?><br>
                    Teléfono: <?= h($venta['Telefono'] ?? '-') ?><br>
                    Correo: <?= h($venta['Correo'] ?? '-') ?><br>
                    Cédula: <?= h($venta['Cedula'] ?? '-') ?><br>
                    RUT: <?= h($venta['RUT'] ?? '-') ?><br>
                    Dirección: <?= h($venta['Direccion'] ?? '-') ?>
                </p>
            </div>
            <div class="bloque">
                <h3>Datos de venta</h3>
                <p>
                    Método de pago: <strong><?= h($metodoPagoTexto) ?></strong><br>
                    Tipo de cliente: <strong><?= h($venta['TipoCliente'] ?? '-') ?></strong><br>
                    <?php if ($tipoCambioAplicado > 0): ?>
                        Tipo de cambio aplicado: <strong>1 USD = <?= number_format($tipoCambioAplicado, 2, ',', '.') ?> UYU</strong>
                    <?php else: ?>
                        Tipo de cambio aplicado: <strong>No registrado</strong>
                    <?php endif; ?>
                </p>
            </div>
        </section>

        <section class="bloque">
            <h3>Detalle de productos</h3>
            <div class="table-responsive comprobante-table-wrap">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Detalle</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($detalles): ?>
                            <?php foreach ($detalles as $d): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($d['Nombre']) ?></strong><br>
                                        <small><?= h($d['TipoProducto']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (($d['TipoProducto'] ?? '') === 'Moto'): ?>
                                            Modelo: <?= h($d['Modelo'] ?? '-') ?><br>
                                            Motor: <?= h($d['NumeroMotor'] ?? '-') ?><br>
                                            Color: <?= h($d['Color'] ?? '-') ?><br>
                                            ID vehículo: <?= h($d['IdVehiculo'] ?? '-') ?>
                                        <?php else: ?>
                                            Repuesto
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int)($d['Cantidad'] ?? 0) ?></td>
                                    <td><?= \Lteco\Support\VentaView::montoComprobante($d['PrecioUnitario'] ?? 0, $d['Moneda'] ?? $monedaVenta) ?></td>
                                    <td><?= \Lteco\Support\VentaView::montoComprobante($d['Subtotal'] ?? 0, $d['Moneda'] ?? $monedaVenta) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No hay detalle cargado para esta venta.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="comprobante-totales">
            <div class="dato dato--principal">
                <strong>Total</strong>
                <span><?= h($totalVenta) ?></span>
            </div>
            <div class="dato">
                <strong>Monto pagado</strong>
                <span><?= h($montoPagado) ?></span>
            </div>
            <div class="dato">
                <strong>Saldo pendiente</strong>
                <span><?= h($saldoTexto) ?></span>
            </div>
            <div class="dato">
                <strong>Estado</strong>
                <span><?= h($estado) ?></span>
            </div>
        </section>

        <?php if (!empty($venta['Observaciones'])): ?>
            <section class="footer-texto comprobante-observaciones">
                <strong>Observaciones:</strong> <?= h($venta['Observaciones']) ?>
            </section>
        <?php endif; ?>

        <?php if ($textoComprobante !== ''): ?>
            <section class="footer-texto"><?= h($textoComprobante) ?></section>
        <?php endif; ?>

        <div class="acciones comprobante-acciones">
            <a href="<?= panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$venta['IdVenta'])) ?>" class="btn-secundario">⬅ Volver a la venta</a>
            <a href="<?= panelBaseUrl('dashboard.php') ?>" class="btn-secundario">Dashboard</a>
            <?php if ($linkWhatsapp !== '' && $estado !== 'Anulada'): ?>
                <a href="<?= h($linkWhatsapp) ?>" target="_blank" rel="noopener" class="btn-whatsapp">📲 Enviar por WhatsApp</a>
            <?php endif; ?>
            <button type="button" id="btnImprimirComprobante" class="btn-imprimir">🖨 Imprimir</button>
        </div>
    </div>
    <script nonce="<?= cspNonce() ?>">
    document.getElementById('btnImprimirComprobante')?.addEventListener('click', function () {
        window.print();
    });
    </script>
</body>
</html>
