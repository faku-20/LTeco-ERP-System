<?php
$pageTitle = "Detalle de venta | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';

requiereModulo("ventas");

require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/whatsapp.php";

$waCfgDetalle = whatsappObtenerConfig($pdo);

$tipoCambio = obtenerTipoCambioUSD($pdo);

if (!isset($_GET['id']) || trim((string)$_GET['id']) === '') {
    redirectWithFlash(panelBaseUrl('ventas/index.php'), 'error', 'ID de venta no recibido.');
}

$id = (int) trim((string) $_GET['id']);

if ($id <= 0) {
    redirectWithFlash(panelBaseUrl('ventas/index.php'), 'error', 'ID de venta inválido.');
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

$numeroComprobante = \Lteco\Support\VentaView::numeroComprobante($venta);

// Garantía y services del vehículo vendido (si aplica)
$postventa = $ventaQuery->garantiaYServices($id, $detalles);
$garantiaFin = $postventa['garantiaFin'];
$serviceDates = $postventa['serviceDates'];
$mensajeWhatsapp = \Lteco\Support\VentaView::mensajePostventa($venta, $garantiaFin, $serviceDates);

$linkWhatsappComprobante = linkWhatsappPanel($venta['Telefono'] ?? null, $mensajeWhatsapp);
$mensajeClientePostVenta = $mensajeWhatsapp;

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <h1>Detalle de venta #<?= htmlspecialchars((string)$venta['IdVenta']) ?></h1>

        <div class="u-flex-center-wrap">
            <a href="<?= panelBaseUrl('ventas/comprobante.php?id=' . urlencode((string)$venta['IdVenta'])) ?>" target="_blank" rel="noopener"
               class="btn-blue-flat">
               📄 Comprobante
            </a>

            <?php if ($linkWhatsappComprobante && ($venta['EstadoVenta'] ?? 'Confirmada') !== 'Anulada'): ?>
                <a href="<?= htmlspecialchars($linkWhatsappComprobante) ?>" target="_blank" rel="noopener" class="btn">
                    Enviar WhatsApp
                </a>
            <?php endif; ?>

            <?php if ($waCfgDetalle['enabled'] && !empty($waCfgDetalle['tpl_venta']) && !empty($venta['Telefono']) && ($venta['EstadoVenta'] ?? 'Confirmada') !== 'Anulada'): ?>
                <form method="POST" action="<?= panelBaseUrl('ventas/whatsapp_reenviar.php') ?>" style="display:inline;">
                    <?= csrfInput() ?>
                    <input type="hidden" name="id_venta" value="<?= (int)$venta['IdVenta'] ?>">
                    <button type="submit" class="btn-secondary">Reenviar WhatsApp</button>
                </form>
            <?php endif; ?>

            <a class="btn-secondary" href="<?= panelBaseUrl('ventas/crear.php') ?>">Nueva venta</a>

            <?php if (esAdmin() && ($venta['EstadoVenta'] ?? 'Confirmada') !== 'Anulada'): ?>
                <button type="button"
                    id="btn-abrir-anular"
                    class="btn-danger">
                    Anular venta
                </button>
            <?php endif; ?>

            <a class="btn-secondary" href="<?= panelBaseUrl('ventas/index.php') ?>">Volver</a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <?php if (isset($_GET['venta_creada'])): ?>
        <div class="section-box post-sale-box">
            <h2>Venta creada</h2>
            <p>La venta ya quedó registrada. Las acciones más usadas son abrir el comprobante, enviarlo al cliente o cargar una nueva venta.</p>
            <div class="notice notice--info">
                <strong>Mensaje sugerido al cliente</strong><br>
                <textarea id="mensaje-cliente-postventa" class="copy-textarea" readonly><?= h($mensajeClientePostVenta, '') ?></textarea>
            </div>
            <div class="form-actions">
                <a class="btn-blue-flat" href="<?= panelBaseUrl('ventas/comprobante.php?id=' . urlencode((string)$venta['IdVenta'])) ?>" target="_blank" rel="noopener">Abrir comprobante</a>
                <?php if ($linkWhatsappComprobante && ($venta['EstadoVenta'] ?? 'Confirmada') !== 'Anulada'): ?>
                    <a class="btn" href="<?= htmlspecialchars($linkWhatsappComprobante) ?>" target="_blank" rel="noopener">Enviar por WhatsApp</a>
                <?php endif; ?>
                <button type="button" class="btn-secondary" data-copy-target="mensaje-cliente-postventa">Copiar mensaje</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('ventas/crear.php') ?>">Nueva venta</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-box">
        <div class="summary-grid-3">
            <div>
                <strong>Tipo de cliente</strong><br>
                <?= htmlspecialchars((string)($venta['TipoFiscal'] ?? 'Consumidor final')) ?>
            </div>
            <div>
                <strong>Cliente</strong><br>
                <?= htmlspecialchars((string)($venta['NombreApellido'] ?? 'Sin cliente')) ?>
            </div>
            <div>
                <strong>Teléfono</strong><br>
                <?= htmlspecialchars((string)($venta['Telefono'] ?? '-')) ?>
            </div>
            <div>
                <strong>Correo</strong><br>
                <?= htmlspecialchars((string)($venta['Correo'] ?? '-')) ?>
            </div>
            <div>
                <strong>Cédula</strong><br>
                <?= htmlspecialchars((string)($venta['Cedula'] ?? '-')) ?>
            </div>
            <div>
                <strong>RUT</strong><br>
                <?= htmlspecialchars((string)($venta['RUT'] ?? '-')) ?>
            </div>
            <div>
                <strong>Dirección</strong><br>
                <?= htmlspecialchars((string)($venta['Direccion'] ?? '-')) ?>
            </div>

            <div>
                <strong>Fecha</strong><br>
                <?= htmlspecialchars((string)($venta['FechaVenta'] ?? '-')) ?>
            </div>
            <div>
                <strong>Nº comprobante</strong><br>
                <?= htmlspecialchars((string)($venta['NumeroFactura'] ?? 'Pendiente')) ?>
            </div>
            <div>
                <strong>Moneda</strong><br>
                <?= htmlspecialchars((string)($venta['Moneda'] ?? 'UYU')) ?>
            </div>
            <div>
                <strong>Método de pago</strong><br>
                <?= htmlspecialchars((string)($venta['MetodoPago'] ?? 'Efectivo')) ?>
            </div>

            <?php if (($venta['MetodoPago'] ?? '') === 'Tarjeta'): ?>
                <div>
                    <strong>Tarjeta</strong><br>
                    <?= htmlspecialchars(trim(implode(' ', array_filter([
                        $venta['TipoTarjeta'] ?? '',
                        $venta['MarcaTarjeta'] ?? '',
                        !empty($venta['CuotasTarjeta']) ? '(' . $venta['CuotasTarjeta'] . ' cuota' . ((int)$venta['CuotasTarjeta'] === 1 ? '' : 's') . ')' : '',
                    ]))) ?: '-') ?>
                </div>
            <?php endif; ?>

            <div>
                <strong>Estado</strong><br>
                <?= htmlspecialchars((string)($venta['EstadoVenta'] ?? 'Confirmada')) ?>
            </div>
            <div>
                <strong>Total</strong><br>
                <?= formatearMonto((float)($venta['Total'] ?? 0), $venta['Moneda'] ?? 'UYU') ?>
            </div>
            <div>
                <strong>Total ref. UYU</strong><br>
                <?= formatearMonto(convertirMontoVentaAUyu((float)($venta['Total'] ?? 0), $venta['Moneda'] ?? 'UYU', $venta, $tipoCambio), 'UYU') ?>
            </div>

            <div>
                <strong>Monto pagado</strong><br>
                <?= formatearMonto((float)($venta['MontoPagado'] ?? 0), $venta['Moneda'] ?? 'UYU') ?>
            </div>
            <div>
                <strong>Saldo pendiente</strong><br>
                <?= formatearMonto((float)($venta['SaldoPendiente'] ?? 0), $venta['Moneda'] ?? 'UYU') ?>
            </div>
            <?php if (esAdmin()): ?>
                <div>
                    <strong>Ganancia real</strong><br>
                    <?= formatearMonto((float)($venta['GananciaEstimada'] ?? 0), $venta['Moneda'] ?? 'UYU') ?>
                </div>
                <div>
                    <strong>Comisión vendedor</strong><br>
                    <?= formatearMonto((float)($venta['ComisionVendedor'] ?? 0), $venta['Moneda'] ?? 'UYU') ?>
                </div>
            <?php endif; ?>

            <div class="u-full-grid">
                <strong>Observaciones</strong><br>
                <?= nl2br(htmlspecialchars((string)($venta['Observaciones'] ?? ''))) ?>
            </div>

            <?php if (($venta['EstadoVenta'] ?? '') === 'Anulada'): ?>
                <div class="alert-box alert-box--danger">
                    <strong class="text-danger-dark">Venta anulada</strong><br><br>
                    <strong>Motivo:</strong><br>
                    <?= nl2br(htmlspecialchars((string)($venta['MotivoAnulacion'] ?? '-'))) ?><br><br>

                    <strong>Fecha de anulación:</strong><br>
                    <?= htmlspecialchars((string)($venta['FechaAnulacion'] ?? '-')) ?><br><br>

                    <strong>Usuario que anuló:</strong><br>
                    <?= htmlspecialchars((string)($venta['UsuarioAnulacion'] ?? $venta['AnuladaPorUsuarioId'] ?? '-')) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="section-box">
        <h2 class="section-title">Productos vendidos</h2>

        <div class="table-wrap">


        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($detalles): ?>
                    <?php foreach ($detalles as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($d['Nombre'] ?? '-')) ?></td>
                            <td><?= (int)($d['Cantidad'] ?? 0) ?></td>
                            <td><?= formatearMonto((float)($d['PrecioUnitario'] ?? 0), $d['Moneda'] ?? 'UYU') ?></td>
                            <td><?= formatearMonto((float)($d['Subtotal'] ?? 0), $d['Moneda'] ?? 'UYU') ?></td>
                            <td>
                                <?php if (($d['TipoProducto'] ?? '') === 'Moto'): ?>
                                    Modelo: <?= htmlspecialchars((string)($d['Modelo'] ?? '-')) ?><br>
                                    Motor: <?= htmlspecialchars((string)($d['NumeroMotor'] ?? '-')) ?><br>
                                    Color: <?= htmlspecialchars((string)($d['Color'] ?? '-')) ?><br>
                                    Vehículo: <?= htmlspecialchars((string)($d['IdVehiculo'] ?? '-')) ?>
                                <?php else: ?>
                                    Repuesto
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No hay detalle de productos.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


        </div>
    </div>

    <?php if (esAdmin() && ($venta['EstadoVenta'] ?? 'Confirmada') !== 'Anulada'): ?>
        <div id="modal-anular" class="modal-overlay is-hidden">
            <div class="modal-box">
                <h2 class="modal-title">Anular venta #<?= htmlspecialchars((string)$venta['IdVenta']) ?></h2>

                <p class="modal-copy">
                    ¿Seguro que querés anular esta venta?
                </p>

                <p class="modal-warning">
                    Esta acción revertirá stock o disponibilidad, y quedará registrada con motivo, fecha y usuario.
                </p>

                <form method="POST" action="<?= panelBaseUrl('ventas/anular.php') ?>">

                    <input type="hidden" name="id" value="<?= (int)$venta['IdVenta'] ?>">
                    <?= csrfInput() ?>
                    <?= panelIdempotencyInput() ?>

                    <div class="form-group">
                        <label for="motivo-anulacion"><strong>Motivo de anulación</strong></label>
                        <textarea
                            id="motivo-anulacion"
                            name="motivo"
                            required
                            placeholder="Explicá por qué se anula la venta..."></textarea>
                    </div>

                    <div class="u-actions-end">
                        <button type="button"
                                id="btn-cancelar-anular"
                                class="btn-secondary">
                            Cancelar
                        </button>

                        <button type="submit" class="btn-danger">
                            Confirmar anulación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-copy-target]');
    if (!button) return;

    const target = document.getElementById(button.dataset.copyTarget || '');
    if (!target) return;

    const texto = target.value || target.textContent || '';
    try {
        await navigator.clipboard.writeText(texto);
        const original = button.textContent;
        button.textContent = 'Copiado';
        window.setTimeout(function () {
            button.textContent = original;
        }, 1600);
    } catch (error) {
        target.focus();
        if (target.select) target.select();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const modalAnular = document.getElementById('modal-anular');
    const btnAbrirAnular = document.getElementById('btn-abrir-anular');
    const btnCancelarAnular = document.getElementById('btn-cancelar-anular');

    btnAbrirAnular?.addEventListener('click', function () {
        modalAnular?.classList.remove('is-hidden');
    });

    btnCancelarAnular?.addEventListener('click', function () {
        modalAnular?.classList.add('is-hidden');
    });
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
