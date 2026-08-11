<?php
$pageTitle = "Nueva venta | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";

requiereModulo("ventas");
require_once __DIR__ . "/../includes/helpers.php";

$tipoCambio = obtenerTipoCambioUSD($pdo);

$connection = new \Lteco\Infrastructure\Db\Connection($pdo);
$ventaCommercial = new \Lteco\Application\Venta\VentaCommercialService(
    new \Lteco\Infrastructure\Repository\VentaCommercialRepository($connection)
);
$ventaCreateForm = new \Lteco\Application\Venta\VentaCreateFormService(
    new \Lteco\Infrastructure\Repository\VentaCreateFormRepository($connection),
    $ventaCommercial
);
$idUsuarioVendedor = (int)(usuarioActual()['IdUsuario'] ?? 0);
$formData = $ventaCreateForm->cargar($idUsuarioVendedor, esVendedor(), defaultTasaIVA());

$distribuidoresActivos = $formData['distribuidoresActivos'];
$configComercial = $formData['configComercial'];
$descuentoContado = (float)$configComercial['DescuentoContado'];
$recargoTarjeta = (float)$configComercial['RecargoTarjeta'];
$comisionDistribuidor = (float)$configComercial['ComisionDistribuidor'];
$comisionVendedor = (float)$configComercial['ComisionVendedor'];
$tasaIVA = (float)$configComercial['TasaIVA'];
$clientes = $formData['clientes'];
$vehiculos = $formData['vehiculos'];
$repuestos = $formData['repuestos'];
$repuestosJson = array_map(static function (array $r) use ($tipoCambio): array {
    $idRepuesto = (int)$r['IdRepuesto'];
    $idProducto = (int)$r['IdProducto'];
    $codigo = 'REP-' . $idRepuesto;
    $sku = 'SKU-' . $idProducto;
    $tc = (float)($r['TipoCambioImportacion'] ?: $tipoCambio);

    return [
        'id' => $idRepuesto,
        'idProducto' => $idProducto,
        'nombre' => (string)$r['Nombre'],
        'codigo' => $codigo,
        'sku' => $sku,
        'precio' => (float)$r['PrecioVenta'],
        'precioDistribuidor' => $r['PrecioDistribuidor'] !== null ? (float)$r['PrecioDistribuidor'] : null,
        'gasto' => (float)$r['GastoTotal'],
        'moneda' => (string)$r['Moneda'],
        'stock' => (int)$r['Stock'],
        'tc' => $tc,
        'precioTexto' => formatearEnPesos((float)$r['PrecioVenta'], (string)$r['Moneda'], $tc),
        'search' => mb_strtolower(trim((string)$r['Nombre'] . ' ' . $codigo . ' ' . $sku . ' ' . $idRepuesto . ' ' . $idProducto), 'UTF-8'),
    ];
}, $repuestos);

$error = trim($_GET['error'] ?? '');

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Nueva venta</h1>
            <p class="subtle">Flujo simple: cliente, productos, pago y confirmación.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('ventas/index.php') ?>">Volver</a>
    </div>

    <?php if ($error): ?>
        <div class="section-box section-box--danger" role="alert">
            <strong>No se pudo guardar la venta.</strong><br>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= panelBaseUrl('ventas/guardar.php') ?>" id="ventaForm" class="sale-clean-form">
        <?= csrfInput() ?>
        <?= panelIdempotencyInput() ?>

        <div class="sale-layout">
            <div class="sale-main-column">
                <section class="section-box sale-step">
                    <div class="sale-step-header">
                        <span class="sale-step-number">1</span>
                        <div>
                            <h2>Cliente</h2>
                            <p>Seleccioná un cliente existente o cargá uno rápido si todavía no está registrado.</p>
                        </div>
                    </div>

                    <div class="form-grid sale-client-grid">
                        <div class="form-group full">
                            <label for="cliente_id">Cliente existente</label>
                            <select name="cliente_id" id="cliente_id">
                                <option value="">-- Seleccionar cliente --</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option
                                        value="<?= htmlspecialchars($c['IdCliente']) ?>"
                                        data-telefono="<?= htmlspecialchars($c['Telefono'] ?? '') ?>"
                                        data-correo="<?= h($c['Correo'] ?? '', '') ?>"
                                        data-tipo-fiscal="<?= h($c['TipoFiscal'] ?? 'Consumidor final', '') ?>"
                                        data-cedula="<?= h($c['Cedula'] ?? '', '') ?>"
                                        data-direccion="<?= h($c['Direccion'] ?? '', '') ?>"
                                        data-rut="<?= h($c['RUT'] ?? '', '') ?>"
                                    ><?= htmlspecialchars($c['NombreApellido']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full" id="clienteExistenteResumen" hidden>
                            <div class="notice notice--info sale-client-summary">
                                <strong id="clienteExistenteTitulo">Cliente seleccionado</strong><br>
                                <span id="clienteExistenteDatos"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tipo_cliente">Tipo de cliente</label>
                            <select name="tipo_cliente" id="tipo_cliente">
                                <?php foreach (tiposClienteSistema() as $tipoOpt): ?>
                                    <?php if ($tipoOpt === 'Distribuidor' && !esAdmin()) continue; ?>
                                    <option value="<?= htmlspecialchars($tipoOpt) ?>"><?= htmlspecialchars($tipoOpt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (esAdmin()): ?>
                        <div class="form-group" id="distribuidorField" style="display:none;">
                            <label for="distribuidor_id">Distribuidor</label>
                            <select name="distribuidor_id" id="distribuidor_id">
                                <option value="">-- Seleccionar distribuidor --</option>
                                <?php foreach ($distribuidoresActivos as $dist): ?>
                                    <option value="<?= htmlspecialchars((string)$dist['IdDistribuidor']) ?>"
                                            data-comision="<?= htmlspecialchars((string)$dist['ComisionPct']) ?>">
                                        <?= htmlspecialchars($dist['Nombre']) ?> (<?= number_format((float)$dist['ComisionPct'], 2) ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help">La comisión se calcula sobre el total con IVA incluido.</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <details class="sale-details-panel" id="clienteRapidoPanel">
                        <summary>+ Crear cliente rápido</summary>
                        <p class="help-text">Usá estos campos solo si el cliente todavía no existe. Si elegís un cliente existente, estos datos no se usan.</p>

                        <div id="cliente-duplicado-aviso" style="display:none;padding:8px 12px;background:#fef9c3;border-radius:8px;border-left:3px solid #f59e0b;font-size:.9rem;margin-bottom:8px;"></div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nuevo_tipo_fiscal">Tipo de cliente</label>
                                <select name="nuevo_tipo_fiscal" id="nuevo_tipo_fiscal">
                                    <?php foreach (tiposClienteFiscalSistema() as $tipoFiscal): ?>
                                        <option value="<?= h($tipoFiscal, '') ?>"><?= h($tipoFiscal, '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="nuevo_nombre" id="nuevo_nombre_label">Nombre</label>
                                <input type="text" name="nuevo_nombre" id="nuevo_nombre">
                            </div>
                            <div class="form-group">
                                <label for="nuevo_apellido" id="nuevo_apellido_label">Apellido</label>
                                <input type="text" name="nuevo_apellido" id="nuevo_apellido">
                            </div>
                            <div class="form-group">
                                <label for="nuevo_telefono">Teléfono</label>
                                <input type="text" name="nuevo_telefono" id="nuevo_telefono">
                            </div>
                            <div class="form-group">
                                <label for="nuevo_correo">Correo</label>
                                <input type="email" name="nuevo_correo" id="nuevo_correo">
                            </div>
                            <div class="form-group">
                                <label for="nuevo_cedula">Cédula</label>
                                <input type="text" name="nuevo_cedula" id="nuevo_cedula" maxlength="40">
                            </div>
                            <div class="form-group" id="nuevo_rut_field">
                                <label for="nuevo_rut">RUT</label>
                                <input type="text" name="nuevo_rut" id="nuevo_rut" maxlength="40">
                            </div>
                            <div class="form-group full">
                                <label for="nuevo_direccion">Dirección</label>
                                <input type="text" name="nuevo_direccion" id="nuevo_direccion" maxlength="255">
                            </div>
                        </div>
                    </details>
                </section>

                <section class="section-box sale-step">
                    <div class="sale-step-header">
                        <span class="sale-step-number">2</span>
                        <div>
                            <h2>Productos</h2>
                            <p>Marcá las motos o indicá cantidades de repuestos. El total se calcula automáticamente.</p>
                        </div>
                    </div>

                    <div class="sale-scan-box">
                        <div>
                            <strong>Escanear vehículo por QR / motor</strong>
                            <p>Escaneá el QR interno de la moto o escribí el número de motor para seleccionarla automáticamente.</p>
                        </div>
                        <div class="sale-scan-control">
                            <label for="vehiculo_scan_input">Motor o QR</label>
                            <input type="text" id="vehiculo_scan_input" placeholder="Ej: MOTOR-0002" autocomplete="off">
                            <button type="button" class="btn-secondary" id="vehiculo_scan_btn">Seleccionar</button>
                            <button type="button" class="btn-secondary" id="vehiculo_scan_camera_btn">
                                📷 Escanear QR
                            </button></div>
                        <div id="vehiculo_scan_feedback" class="sale-scan-feedback" hidden></div>

                        <div id="vehiculo_qr_modal" class="qr-modal" hidden>
                            <div class="qr-modal-content">
                                <div class="qr-modal-header">
                                    <strong>Escanear QR del vehículo</strong>
                                    <button type="button" id="vehiculo_qr_close" aria-label="Cerrar lector QR">✕</button>
                                </div>

                                <div id="vehiculo_qr_reader"></div>

                                <p class="help-text">
                                    Apuntá la cámara al QR interno de la moto.
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php /* Checkboxes ocultos — el JS los necesita para calcular */ ?>
                    <div style="display:none;" id="vehiculos-hidden-checks">
                        <?php foreach ($vehiculos as $v): ?>
                            <input
                                type="checkbox"
                                name="vehiculos[]"
                                value="<?= htmlspecialchars($v['IdVehiculo']) ?>"
                                class="vehiculo-check"
                                data-nombre="<?= htmlspecialchars($v['Modelo']) ?>"
                                data-precio="<?= htmlspecialchars($v['PrecioVenta']) ?>"
                                data-precio-distribuidor="<?= htmlspecialchars($v['PrecioDistribuidor'] ?? '') ?>"
                                data-gasto="<?= htmlspecialchars($v['GastoTotal']) ?>"
                                data-moneda="<?= htmlspecialchars($v['Moneda']) ?>"
                                data-estado="<?= htmlspecialchars($v['Estado']) ?>"
                                data-reserva-cliente="<?= htmlspecialchars($v['ClienteReservaId'] ?? '') ?>"
                                data-motor="<?= htmlspecialchars($v['NumeroMotor']) ?>"
                                data-tc="<?= (float)($v['TipoCambioImportacion'] ?: $tipoCambio) ?>"
                            >
                        <?php endforeach; ?>
                    </div>
                    <div id="motos-seleccionadas" style="margin-top:8px;"></div>

                    <details class="sale-product-panel">
                        <summary>Repuestos con stock <span><?= count($repuestos) ?></span></summary>
                        <div class="sale-repuesto-search">
                            <label for="repuesto_buscar">Buscar repuesto</label>
                            <input type="search" id="repuesto_buscar" placeholder="Nombre, código o SKU..." autocomplete="off">
                            <small>Filtrá la lista y luego indicá la cantidad a vender.</small>
                        </div>

                        <?php if ($repuestos): ?>
                            <div class="sale-repuestos-selected" id="repuestos-seleccionados">
                                <strong>Repuestos seleccionados</strong>
                                <p class="sale-repuestos-selected__empty" id="repuestos-seleccionados-empty">No hay repuestos seleccionados.</p>
                                <div class="sale-repuestos-selected__list" id="repuestos-seleccionados-lista"></div>
                            </div>

                            <div id="repuestos-hidden-inputs"></div>
                            <div class="sale-repuestos-compact-list" id="repuestos-lista" data-empty-text="Abrí o buscá para cargar los repuestos disponibles."></div>
                            <p class="help-text" id="repuestos-empty" hidden>No se encontraron repuestos.</p>
                        <?php else: ?>
                            <p class="help-text">No hay repuestos con stock.</p>
                        <?php endif; ?>
                    </details>
                </section>

                <section class="section-box sale-step">
                    <div class="sale-step-header">
                        <span class="sale-step-number">3</span>
                        <div>
                            <h2>Pago</h2>
                            <p>Transferencia queda disponible. Crédito usa cuotas; débito registra 2% de comisión interna.</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="numero_factura_manual">N&uacute;mero de factura <small class="field-help">(opcional)</small></label>
                            <input type="text" name="numero_factura_manual" id="numero_factura_manual" placeholder="Ej: 000123 &mdash; vac&iacute;o genera autom&aacute;tico" maxlength="40">
                        </div>
                        <div class="form-group">
                            <label for="metodo_pago">M&eacute;todo de pago</label>
                            <select name="metodo_pago" id="metodo_pago" class="input u-w-100" required>
                                <?php foreach (metodosPagoVentaSistema() as $metodo): ?>
                                    <option value="<?= htmlspecialchars($metodo) ?>"><?= htmlspecialchars($metodo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group payment-card-field" id="tipo-tarjeta-field">
                            <label for="tipo_tarjeta">Tipo de tarjeta</label>
                            <select name="tipo_tarjeta" id="tipo_tarjeta" class="input u-w-100">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach (tiposTarjetaSistema() as $tipoTarjeta): ?>
                                    <option value="<?= h($tipoTarjeta, '') ?>"><?= h($tipoTarjeta, '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group payment-card-field" id="marca-tarjeta-field">
                            <label for="marca_tarjeta">Marca de tarjeta</label>
                            <select name="marca_tarjeta" id="marca_tarjeta" class="input u-w-100">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach (marcasTarjetaSistema() as $marcaTarjeta): ?>
                                    <option value="<?= htmlspecialchars($marcaTarjeta) ?>"><?= htmlspecialchars($marcaTarjeta) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display:none;"></div>
                        <div class="form-group payment-credit-field" id="cuotas-field">
                            <label for="cuotas_tarjeta">Cuotas</label>
                            <select name="cuotas_tarjeta" id="cuotas_tarjeta" class="input u-w-100">
                                <option value="">-- No aplica --</option>
                            </select>
                        </div>
                        <input type="hidden" name="moneda_venta" id="moneda_venta" value="UYU">
                        <input type="hidden" name="tipo_cambio" id="tipo_cambio" value="<?= h($tipoCambio, '') ?>">
                    </div>
                </section>
            </div>

            <aside class="section-box sale-summary-sticky">
                <h2>Confirmar venta</h2>

                <div class="sale-summary-grid sale-summary-grid--compact">
                    <div>
                        <strong>Cliente</strong>
                        <span id="resumenCliente">Sin cliente seleccionado</span>
                    </div>
                    <div>
                        <strong>Método</strong>
                        <span id="resumenMetodo">Efectivo</span>
                    </div>
                </div>

                <div class="sale-summary-products sale-summary-products--compact">
                    <strong>Productos</strong>
                    <ul id="resumenProductos">
                        <li>No hay productos seleccionados.</li>
                    </ul>
                </div>

                <div class="sale-totals-strip">
                    <div><span>Total</span><strong id="totalGeneral">$0.00</strong></div>
                </div>

                <span id="resumenTipoCliente" hidden>Final</span>
                <span id="resumenTotal" hidden>$0.00</span>
                <span id="resumenPagado" hidden>$0.00</span>
                <span id="resumenSaldo" hidden>$0.00</span>
                <span id="resumenGanancia" hidden>$0.00</span>
                <span id="subtotalMotos" hidden>$0.00</span>
                <span id="subtotalRepuestos" hidden>$0.00</span>
                <span id="subtotalBrutoPreview" hidden>$0.00</span>
                <span id="descuentoPreview" hidden>$0.00</span>
                <span id="recargoPreview" hidden>$0.00</span>
                <span id="ivaPreview" hidden>$0.00</span>
                <span id="totalSinIvaPreview" hidden>$0.00</span>
                <span id="saldoPendientePreview" hidden>$0.00</span>
                <span id="gananciaEstimada" hidden>$0.00</span>

                <div id="venta_error_box" class="notice notice--error" role="alert" hidden></div>
                <div class="form-actions form-actions--sale">
                    <button type="submit" class="btn">Confirmar venta</button>
                </div>
            </aside>
        </div>
    </form>
</main>

<script type="application/json" id="venta-repuestos-data"><?= json_encode($repuestosJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script nonce="<?= cspNonce() ?>">
document.addEventListener("DOMContentLoaded", () => {
    const ventaForm = document.getElementById("ventaForm");
    const clienteSelect = document.getElementById("cliente_id");
    const tipoClienteSelect = document.getElementById("tipo_cliente");
    const nuevoTipoFiscalSelect = document.getElementById("nuevo_tipo_fiscal");
    const nuevoNombreInput = document.getElementById("nuevo_nombre");
    const nuevoApellidoInput = document.getElementById("nuevo_apellido");
    const nuevoRutInput = document.getElementById("nuevo_rut");
    const monedaVentaSelect = document.getElementById("moneda_venta");
    const tipoCambioInput = document.getElementById("tipo_cambio");
    const metodoPagoSelect = document.getElementById("metodo_pago");
    const montoPagadoInput = document.getElementById("monto_pagado");
    const tipoTarjetaSelect = document.getElementById("tipo_tarjeta");
    const marcaTarjetaSelect = document.getElementById("marca_tarjeta");
    const cuotasTarjetaSelect = document.getElementById("cuotas_tarjeta");
    const cardFields = document.querySelectorAll(".payment-card-field");
    const creditFields = document.querySelectorAll(".payment-credit-field");
    const vehiculoChecks = document.querySelectorAll(".vehiculo-check");
    const vehiculoScanInput = document.getElementById("vehiculo_scan_input");
    const vehiculoScanBtn = document.getElementById("vehiculo_scan_btn");
    const vehiculoScanFeedback = document.getElementById("vehiculo_scan_feedback");
    const repuestoBuscarInput = document.getElementById("repuesto_buscar");
    const repuestosDataNode = document.getElementById("venta-repuestos-data");
    const repuestosLista = document.getElementById("repuestos-lista");
    const repuestosHiddenInputs = document.getElementById("repuestos-hidden-inputs");
    const clienteExistenteResumen = document.getElementById("clienteExistenteResumen");
    const clienteExistenteTitulo = document.getElementById("clienteExistenteTitulo");
    const clienteExistenteDatos = document.getElementById("clienteExistenteDatos");

    const subtotalMotosEl = document.getElementById("subtotalMotos");
    const subtotalRepuestosEl = document.getElementById("subtotalRepuestos");
    const subtotalBrutoPreviewEl = document.getElementById("subtotalBrutoPreview");
    const descuentoPreviewEl = document.getElementById("descuentoPreview");
    const recargoPreviewEl = document.getElementById("recargoPreview");
    const ivaPreviewEl = document.getElementById("ivaPreview");
    const totalSinIvaPreviewEl = document.getElementById("totalSinIvaPreview");
    const totalGeneralEl = document.getElementById("totalGeneral");
    const gananciaEstimadaEl = document.getElementById("gananciaEstimada");
    const resumenClienteEl = document.getElementById("resumenCliente");
    const resumenTipoClienteEl = document.getElementById("resumenTipoCliente");
    const resumenMetodoEl = document.getElementById("resumenMetodo");
    const resumenTotalEl = document.getElementById("resumenTotal");
    const resumenPagadoEl = document.getElementById("resumenPagado");
    const resumenSaldoEl = document.getElementById("resumenSaldo");
    const saldoPendientePreviewEl = document.getElementById("saldoPendientePreview");
    const resumenGananciaEl = document.getElementById("resumenGanancia");
    const resumenProductosEl = document.getElementById("resumenProductos");

    const descuentoContadoPct = <?= json_encode($descuentoContado) ?>;
    const recargoTarjetaPct = <?= json_encode($recargoTarjeta) ?>;
    let comisionDistribuidorPct = <?= json_encode($comisionDistribuidor) ?>;
    const comisionVendedorPct = <?= json_encode($comisionVendedor) ?>;
    const tasaIVAPct = <?= json_encode($tasaIVA) ?>;
    const cuotasPorMarca = <?= json_encode(cuotasTarjetaPorMarcaSistema(), JSON_UNESCAPED_UNICODE) ?>;
    const metodosContado = new Set(["Efectivo", "Transferencia"]);
    const html5QrcodeSrc = <?= json_encode(panelBaseUrl('assets/vendor/html5-qrcode/html5-qrcode.min.js')) ?>;
    const repuestosData = repuestosDataNode ? JSON.parse(repuestosDataNode.textContent || "[]") : [];
    const repuestosCantidad = new Map();
    let repuestosRenderizados = false;
    let html5QrcodeLoader = null;

    const distribuidorField = document.getElementById('distribuidorField');
    const distribuidorSelect = document.getElementById('distribuidor_id');

    function actualizarDistribuidor() {
        const esDistribuidor = tipoClienteSelect && tipoClienteSelect.value === 'Distribuidor';
        if (distribuidorField) distribuidorField.style.display = esDistribuidor ? '' : 'none';
        if (!esDistribuidor) {
            comisionDistribuidorPct = 0;
            if (distribuidorSelect) distribuidorSelect.value = '';
        } else {
            const opt = distribuidorSelect ? distribuidorSelect.options[distribuidorSelect.selectedIndex] : null;
            comisionDistribuidorPct = opt && opt.dataset.comision ? parseFloat(opt.dataset.comision) : <?= json_encode($comisionDistribuidor) ?>;
        }
        recalcular();
    }

    function cantidadRepuestoPorId(id) {
        return Math.max(0, parseInt(repuestosCantidad.get(String(id)) || 0, 10) || 0);
    }

    function sincronizarHiddenRepuesto(id, cantidad) {
        if (!repuestosHiddenInputs) return;
        const name = "rep_" + id;
        let hidden = repuestosHiddenInputs.querySelector('input[name="' + name + '"]');

        if (cantidad <= 0) {
            if (hidden) hidden.remove();
            return;
        }

        if (!hidden) {
            hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = name;
            repuestosHiddenInputs.appendChild(hidden);
        }

        hidden.value = String(cantidad);
    }

    function setCantidadRepuesto(id, cantidad) {
        const key = String(id);
        const qty = Math.max(0, parseInt(cantidad || 0, 10) || 0);
        if (qty > 0) {
            repuestosCantidad.set(key, qty);
        } else {
            repuestosCantidad.delete(key);
        }

        sincronizarHiddenRepuesto(key, qty);

        const visible = document.querySelector('.repuesto-cantidad[data-repuesto-id="' + key + '"]');
        if (visible && visible.value !== String(qty)) {
            visible.value = String(qty);
        }
        const row = visible ? visible.closest(".sale-repuesto-row") : null;
        if (row) row.classList.toggle("is-selected", qty > 0);

        actualizarRepuestosSeleccionados();
        recalcular();
    }

    function crearFilaRepuesto(repuesto) {
        const row = document.createElement("div");
        row.className = "sale-repuesto-row sale-repuesto-row--compact";
        row.dataset.search = repuesto.search || "";

        const info = document.createElement("div");
        const nombre = document.createElement("strong");
        const codigos = document.createElement("small");
        const detalle = document.createElement("span");

        nombre.textContent = repuesto.nombre || "Repuesto";
        codigos.textContent = (repuesto.codigo || "") + " · " + (repuesto.sku || "");
        detalle.textContent = (repuesto.precioTexto || "") + " · Stock: " + (repuesto.stock || 0);

        info.appendChild(nombre);
        info.appendChild(codigos);
        info.appendChild(detalle);

        const control = document.createElement("div");
        const label = document.createElement("label");
        const input = document.createElement("input");

        label.className = "small-label";
        label.textContent = "Cantidad";
        input.type = "number";
        input.min = "0";
        input.max = String(repuesto.stock || 0);
        input.value = String(cantidadRepuestoPorId(repuesto.id));
        input.className = "repuesto-cantidad";
        input.dataset.repuestoId = String(repuesto.id);
        input.dataset.nombre = repuesto.nombre || "";
        input.dataset.precio = String(repuesto.precio || 0);
        input.dataset.precioDistribuidor = repuesto.precioDistribuidor === null ? "" : String(repuesto.precioDistribuidor);
        input.dataset.gasto = String(repuesto.gasto || 0);
        input.dataset.moneda = repuesto.moneda || "UYU";
        input.dataset.tc = String(repuesto.tc || 0);

        input.addEventListener("input", function () {
            setCantidadRepuesto(repuesto.id, input.value);
        });

        row.classList.toggle("is-selected", cantidadRepuestoPorId(repuesto.id) > 0);
        control.appendChild(label);
        control.appendChild(input);
        row.appendChild(info);
        row.appendChild(control);

        return row;
    }

    function renderRepuestos(q = "") {
        if (!repuestosLista) return;
        const empty = document.getElementById('repuestos-empty');
        const term = q.toLowerCase().trim();
        let visibles = 0;

        repuestosLista.innerHTML = "";

        repuestosData.forEach(function(repuesto) {
            const search = repuesto.search || "";
            if (term !== "" && !search.includes(term)) {
                return;
            }

            repuestosLista.appendChild(crearFilaRepuesto(repuesto));
            visibles++;
        });

        repuestosRenderizados = true;
        if (empty) empty.hidden = visibles > 0;
    }

    repuestoBuscarInput?.addEventListener('input', function () {
        renderRepuestos(this.value);
    });

    repuestoBuscarInput?.addEventListener('focus', function () {
        if (!repuestosRenderizados) renderRepuestos(this.value);
    });

    document.querySelector(".sale-product-panel")?.addEventListener("toggle", function () {
        if (this.open && !repuestosRenderizados) renderRepuestos(repuestoBuscarInput?.value || "");
    });

    const repuestosSeleccionadosBox = document.getElementById("repuestos-seleccionados");
    const repuestosSeleccionadosLista = document.getElementById("repuestos-seleccionados-lista");
    const repuestosSeleccionadosEmpty = document.getElementById("repuestos-seleccionados-empty");

    function actualizarRepuestosSeleccionados() {
        if (!repuestosSeleccionadosBox || !repuestosSeleccionadosLista) return;

        const seleccionados = repuestosData.filter(function(repuesto) {
            return cantidadRepuestoPorId(repuesto.id) > 0;
        });

        const sinSeleccion = seleccionados.length === 0;
        repuestosSeleccionadosBox.hidden = false;
        if (repuestosSeleccionadosEmpty) repuestosSeleccionadosEmpty.hidden = !sinSeleccion;
        repuestosSeleccionadosLista.hidden = sinSeleccion;
        repuestosSeleccionadosLista.innerHTML = "";

        seleccionados.forEach(function(repuesto) {
            const item = document.createElement("div");
            item.className = "sale-repuestos-selected__item";

            const nombre = document.createElement("span");
            nombre.className = "sale-repuestos-selected__name";
            nombre.textContent = repuesto.nombre || "Repuesto";

            const cantidad = document.createElement("input");
            cantidad.type = "number";
            cantidad.min = "0";
            cantidad.max = String(repuesto.stock || "");
            cantidad.value = String(cantidadRepuestoPorId(repuesto.id));
            cantidad.className = "sale-repuestos-selected__qty";
            cantidad.setAttribute("aria-label", "Cantidad de " + nombre.textContent);

            const quitar = document.createElement("button");
            quitar.type = "button";
            quitar.className = "sale-repuestos-selected__remove";
            quitar.textContent = "Quitar";

            cantidad.addEventListener("input", function() {
                setCantidadRepuesto(repuesto.id, cantidad.value);
            });

            quitar.addEventListener("click", function() {
                setCantidadRepuesto(repuesto.id, 0);
            });

            item.appendChild(nombre);
            item.appendChild(cantidad);
            item.appendChild(quitar);
            repuestosSeleccionadosLista.appendChild(item);
        });
    }
    actualizarRepuestosSeleccionados();

    function actualizarMotoSeleccionada(check) {
        const motosSeleccionadas = document.getElementById('motos-seleccionadas');
        if (!motosSeleccionadas) return;

        const tarjetaExistente = motosSeleccionadas.querySelector('[data-id="' + check.value + '"]');
        if (!check.checked) {
            if (tarjetaExistente) tarjetaExistente.remove();
            return;
        }

        if (tarjetaExistente) return;

        const tarjeta = document.createElement('div');
        tarjeta.dataset.id = check.value;
        tarjeta.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--surface);border-radius:8px;margin-bottom:6px;border:1px solid var(--line);';

        const info = document.createElement('div');
        const nombreEl = document.createElement('strong');
        const motorEl = document.createElement('small');
        const quitarBtn = document.createElement('button');

        nombreEl.textContent = check.dataset.nombre || check.value;
        motorEl.textContent = 'Motor: ' + (check.dataset.motor || '');
        motorEl.style.cssText = 'color:var(--ink-3);margin-left:8px;';

        quitarBtn.type = 'button';
        quitarBtn.textContent = 'x';
        quitarBtn.style.cssText = 'background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.1rem;';
        quitarBtn.addEventListener('click', function() {
            deseleccionarMoto(check.value);
        });

        info.appendChild(nombreEl);
        info.appendChild(motorEl);
        tarjeta.appendChild(info);
        tarjeta.appendChild(quitarBtn);
        motosSeleccionadas.appendChild(tarjeta);
    }

    function deseleccionarMoto(id) {
        const check = document.querySelector('.vehiculo-check[value="' + id + '"]');
        if (check) {
            check.checked = false;
            check.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    vehiculoChecks.forEach(function(check) {
        check.addEventListener('change', function () {
            actualizarMotoSeleccionada(check);
            recalcular();
        });
    });

    if (metodoPagoSelect) metodoPagoSelect.addEventListener('change', function() {
        actualizarCamposTarjeta();
        recalcular();
    });

    if (tipoTarjetaSelect) tipoTarjetaSelect.addEventListener('change', function() {
        actualizarCamposTarjeta();
        recalcular();
    });

    if (marcaTarjetaSelect) marcaTarjetaSelect.addEventListener('change', function() {
        actualizarCuotasDisponibles();
        actualizarResumen();
    });

    if (cuotasTarjetaSelect) cuotasTarjetaSelect.addEventListener('change', function() {
        actualizarResumen();
    });

    if (tipoClienteSelect) tipoClienteSelect.addEventListener('change', actualizarDistribuidor);

    function actualizarClienteExistenteResumen() {
        if (!clienteSelect || !clienteExistenteResumen || !clienteExistenteTitulo || !clienteExistenteDatos) return;

        const opt = clienteSelect.options[clienteSelect.selectedIndex];
        if (!clienteSelect.value || !opt) {
            clienteExistenteResumen.hidden = true;
            clienteExistenteTitulo.textContent = 'Cliente seleccionado';
            clienteExistenteDatos.textContent = '';
            return;
        }

        const tipoFiscal = opt.dataset.tipoFiscal || 'Consumidor final';
        const partes = [];
        if (opt.dataset.telefono) partes.push('Tel: ' + opt.dataset.telefono);
        if (opt.dataset.correo) partes.push('Correo: ' + opt.dataset.correo);
        if (opt.dataset.cedula) partes.push('Cédula: ' + opt.dataset.cedula);
        if (tipoFiscal === 'Empresa/RUT' && opt.dataset.rut) partes.push('RUT: ' + opt.dataset.rut);
        if (opt.dataset.direccion) partes.push('Dirección: ' + opt.dataset.direccion);

        clienteExistenteTitulo.textContent = opt.text + ' · ' + tipoFiscal;
        clienteExistenteDatos.textContent = partes.length ? partes.join(' · ') : 'Sin datos fiscales adicionales.';
        clienteExistenteResumen.hidden = false;
    }

    if (clienteSelect) clienteSelect.addEventListener('change', actualizarClienteExistenteResumen);

    // Detectar cliente existente por telefono
    const nuevoTelefonoInput = document.getElementById('nuevo_telefono');
    const clienteDuplicadoAviso = document.getElementById('cliente-duplicado-aviso');
    if (nuevoTelefonoInput) {
        nuevoTelefonoInput.addEventListener('blur', function() {
            const tel = this.value.trim();
            if (!tel) return;
            const opciones = clienteSelect ? Array.from(clienteSelect.options) : [];
            const encontrado = opciones.find(opt => opt.dataset.telefono && opt.dataset.telefono.trim() === tel);
            if (encontrado) {
                clienteSelect.value = encontrado.value;
                actualizarClienteExistenteResumen();
                actualizarResumen();
                if (clienteDuplicadoAviso) {
                    clienteDuplicadoAviso.textContent = 'Ya existe ' + encontrado.text + ' con ese teléfono — lo seleccionamos automáticamente.';
                    clienteDuplicadoAviso.style.display = '';
                }
            } else {
                if (clienteDuplicadoAviso) clienteDuplicadoAviso.style.display = 'none';
            }
        });
    }
    if (distribuidorSelect) distribuidorSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        comisionDistribuidorPct = opt && opt.dataset.comision ? parseFloat(opt.dataset.comision) : 0;
        recalcular();
    });

    function actualizarClienteRapidoFiscal() {
        const esEmpresa = nuevoTipoFiscalSelect && nuevoTipoFiscalSelect.value === 'Empresa/RUT';
        const nombreLabel = document.getElementById('nuevo_nombre_label');
        const apellidoLabel = document.getElementById('nuevo_apellido_label');
        const rutField = document.getElementById('nuevo_rut_field');
        if (nombreLabel) nombreLabel.textContent = esEmpresa ? 'Razón social' : 'Nombre';
        if (apellidoLabel) apellidoLabel.textContent = esEmpresa ? 'Contacto' : 'Apellido';
        if (rutField) rutField.hidden = !esEmpresa;
        if (nuevoRutInput) {
            nuevoRutInput.required = !!esEmpresa;
            nuevoRutInput.disabled = !esEmpresa;
            if (!esEmpresa) nuevoRutInput.value = '';
        }
    }

    if (nuevoTipoFiscalSelect) nuevoTipoFiscalSelect.addEventListener('change', actualizarClienteRapidoFiscal);

    let ultimoTotal = 0;

    function convertirMoneda(monto, monedaOrigen, monedaDestino, tipoCambio) {
        if (monedaOrigen === monedaDestino) return monto;
        if (monedaOrigen === "USD" && monedaDestino === "UYU") return monto * tipoCambio;
        if (monedaOrigen === "UYU" && monedaDestino === "USD") return tipoCambio > 0 ? monto / tipoCambio : 0;
        return monto;
    }

    function simbolo(moneda) {
        return moneda === "USD" ? "USD " : "$ ";
    }

    function formatear(valor, moneda) {
        return simbolo(moneda) + Number(valor || 0).toFixed(2);
    }

    function redondear(valor) {
        return Math.round((Number(valor || 0) + Number.EPSILON) * 100) / 100;
    }

    function calcularIVAIncluido(totalConIVA) {
        if (totalConIVA <= 0 || tasaIVAPct <= 0) return 0;
        return redondear(totalConIVA * tasaIVAPct / (100 + tasaIVAPct));
    }

    function precioComercial(elemento) {
        /*
         * LTECO:VENTA_EXCEL_DISTRIBUIDOR_PRECIO_VENTA_JS_V3
         *
         * En V3 el distribuidor no cambia el precio base del producto.
         * La comisión distribuidor se calcula aparte como costo interno.
         */
        const precioVenta = parseFloat(elemento.dataset.precio || 0);
        return precioVenta;
    }

    function textoCliente() {
        if (clienteSelect && clienteSelect.value !== "") {
            return clienteSelect.options[clienteSelect.selectedIndex]?.text || "Cliente seleccionado";
        }

        const nombre = (nuevoNombreInput?.value || "").trim();
        const apellido = (nuevoApellidoInput?.value || "").trim();
        const completo = `${nombre} ${apellido}`.trim();
        return completo !== "" ? completo + " (nuevo)" : "Sin cliente seleccionado";
    }

    function textoMetodoPago() {
        const metodo = metodoPagoSelect?.value || "Efectivo";
        if (metodo !== "Tarjeta") {
            return metodo;
        }

        const partes = [metodo, tipoTarjetaSelect?.value || "", marcaTarjetaSelect?.value || ""];
        if (tipoTarjetaSelect?.value === "Crédito" && cuotasTarjetaSelect?.value) {
            const cuotas = cuotasTarjetaSelect.value;
            partes.push(`${cuotas} cuota${cuotas === "1" ? "" : "s"}`);
        }

        return partes.filter(Boolean).join(" · ");
    }

    function actualizarCuotasDisponibles() {
        if (!cuotasTarjetaSelect) return;
        const marca = marcaTarjetaSelect ? marcaTarjetaSelect.value : "";
        const valorActual = cuotasTarjetaSelect.value;
        const permitidas = cuotasPorMarca[marca] || [];

        cuotasTarjetaSelect.innerHTML = '<option value="">-- No aplica --</option>';
        permitidas.forEach(function(c) {
            const opt = document.createElement('option');
            opt.value = String(c);
            opt.textContent = c + ' cuota' + (c === 1 ? '' : 's');
            cuotasTarjetaSelect.appendChild(opt);
        });

        if (permitidas.length === 1) {
            cuotasTarjetaSelect.value = String(permitidas[0]);
        } else if (permitidas.includes(parseInt(valorActual))) {
            cuotasTarjetaSelect.value = valorActual;
        } else {
            cuotasTarjetaSelect.value = "";
        }
    }
    // LTECO:VENTA_EXCEL_CUOTAS_EXACTAS_JS_V3

    function actualizarCamposTarjeta() {
        const esTarjeta = metodoPagoSelect && metodoPagoSelect.value === "Tarjeta";
        const esCredito = esTarjeta && (!tipoTarjetaSelect || tipoTarjetaSelect.value === "Crédito");

        const tipoTarjetaField = document.getElementById('tipo-tarjeta-field');
        const marcaTarjetaField = document.getElementById('marca-tarjeta-field');
        const cuotasField = document.getElementById('cuotas-field');
        if (tipoTarjetaField) {
            tipoTarjetaField.style.opacity = esTarjeta ? '1' : '0.4';
            tipoTarjetaField.style.pointerEvents = esTarjeta ? '' : 'none';
        }
        if (marcaTarjetaField) {
            marcaTarjetaField.style.opacity = esTarjeta ? '1' : '0.4';
            marcaTarjetaField.style.pointerEvents = esTarjeta ? '' : 'none';
        }
        if (cuotasField) {
            cuotasField.style.opacity = esCredito ? '1' : '0.4';
            cuotasField.style.pointerEvents = esCredito ? '' : 'none';
        }

        if (tipoTarjetaSelect) {
            tipoTarjetaSelect.disabled = !esTarjeta;
            if (!esTarjeta) tipoTarjetaSelect.value = "";
        }

        if (marcaTarjetaSelect) {
            marcaTarjetaSelect.disabled = !esTarjeta;
            if (!esTarjeta) marcaTarjetaSelect.value = "";
        }

        if (cuotasTarjetaSelect) {
            cuotasTarjetaSelect.disabled = !esCredito;
            if (!esCredito) cuotasTarjetaSelect.value = "";
        }

        actualizarCuotasDisponibles();
        actualizarResumen();
    }

    function productosSeleccionados() {
        const productos = [];

        vehiculoChecks.forEach(check => {
            if (check.checked) {
                productos.push("Moto: " + (check.dataset.nombre || check.value));
            }
        });

        repuestosData.forEach(repuesto => {
            const cantidad = cantidadRepuestoPorId(repuesto.id);
            if (cantidad > 0) {
                productos.push("Repuesto: " + (repuesto.nombre || ("REP-" + repuesto.id)) + " x" + cantidad);
            }
        });

        return productos;
    }

    function actualizarResumen() {
        const monedaVenta = monedaVentaSelect.value;
        resumenClienteEl.textContent = textoCliente();
        if (resumenTipoClienteEl) resumenTipoClienteEl.textContent = tipoClienteSelect?.value || "Final";
        resumenMetodoEl.textContent = textoMetodoPago();
        resumenTotalEl.textContent = formatear(ultimoTotal, monedaVenta);
        const pagadoManual = montoPagadoInput && montoPagadoInput.value !== "" ? Math.max(0, parseFloat(montoPagadoInput.value || 0)) : ultimoTotal;
        const pagado = Math.min(pagadoManual, ultimoTotal);
        const saldo = Math.max(0, ultimoTotal - pagado);
        if (resumenPagadoEl) resumenPagadoEl.textContent = formatear(pagado, monedaVenta);
        if (resumenSaldoEl) resumenSaldoEl.textContent = formatear(saldo, monedaVenta);
        if (saldoPendientePreviewEl) saldoPendientePreviewEl.textContent = formatear(saldo, monedaVenta);

        if (resumenGananciaEl && gananciaEstimadaEl) {
            resumenGananciaEl.textContent = gananciaEstimadaEl.textContent;
        }

        const productos = productosSeleccionados();
        resumenProductosEl.innerHTML = "";

        if (productos.length === 0) {
            const li = document.createElement("li");
            li.textContent = "No hay productos seleccionados.";
            resumenProductosEl.appendChild(li);
            return;
        }

        productos.forEach(producto => {
            const li = document.createElement("li");
            li.textContent = producto;
            resumenProductosEl.appendChild(li);
        });
    }

    function normalizarScanVehiculo(valor) {
        let texto = String(valor || "").trim();

        if (!texto) return "";

        try {
            const url = new URL(texto);
            texto = url.searchParams.get("motor") || url.searchParams.get("m") || texto;
        } catch (e) {
            // No era URL, seguimos con texto plano.
        }

        if (texto.includes("|")) {
            const partes = texto.split("|").map(p => p.trim()).filter(Boolean);
            texto = partes[partes.length - 1] || texto;
        }

        return texto.trim().toUpperCase();
    }

    function mostrarFeedbackScan(mensaje, tipo = "info") {
        if (!vehiculoScanFeedback) return;

        vehiculoScanFeedback.hidden = false;
        vehiculoScanFeedback.textContent = mensaje;
        vehiculoScanFeedback.classList.remove("is-ok", "is-error", "is-info");
        vehiculoScanFeedback.classList.add(`is-${tipo}`);

        window.clearTimeout(mostrarFeedbackScan._timer);
        mostrarFeedbackScan._timer = window.setTimeout(() => {
            vehiculoScanFeedback.hidden = true;
        }, 4500);
    }

    function extraerMotorDesdeQR(valor) {
        let texto = String(valor || "").trim();

        if (!texto) return "";

        try {
            const url = new URL(texto);
            texto =
                url.searchParams.get("qr") ||
                url.searchParams.get("data") ||
                url.searchParams.get("motor") ||
                url.searchParams.get("m") ||
                texto;
        } catch (e) {
            // No era URL.
        }

        texto = texto.trim();

        // Nuevo formato LTECO|ID|MOTOR
        if (texto.startsWith("LTECO|")) {
            const partes = texto.split("|").map(v => v.trim());

            if (partes.length >= 3) {
                return partes[2] || "";
            }
        }

        // Compatibilidad pipes legacy
        if (texto.includes("|")) {
            const partes = texto
                .split("|")
                .map(v => v.trim())
                .filter(Boolean);

            return partes[partes.length - 1] || "";
        }

        return texto;
    }

    function seleccionarVehiculoPorMotor(valor) {
        const motorBuscado = normalizarScanVehiculo(extraerMotorDesdeQR(valor));

        if (!motorBuscado) {
            mostrarFeedbackScan("Escanea o escribe un numero de motor.", "error");
            return;
        }

        const encontrado = Array.from(vehiculoChecks).find(check => {
            return normalizarScanVehiculo(check.dataset.motor || "") === motorBuscado;
        });

        if (!encontrado) {
            mostrarFeedbackScan("No se encontro una moto disponible con motor " + motorBuscado + ".", "error");
            return;
        }

        if (encontrado.checked) {
            mostrarFeedbackScan("La moto con motor " + motorBuscado + " ya estaba seleccionada.", "info");
        } else {
            encontrado.checked = true;
            encontrado.dispatchEvent(new Event("change", { bubbles: true }));
            mostrarFeedbackScan("Moto seleccionada: " + (encontrado.dataset.nombre || motorBuscado) + ".", "ok");

        const motosSeleccionadas = document.getElementById('motos-seleccionadas');
        if (motosSeleccionadas && !motosSeleccionadas.querySelector('[data-id="' + encontrado.value + '"]')) {
            const t = document.createElement('div');
            t.dataset.id = encontrado.value;
            t.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--color-background-secondary);border-radius:8px;margin-bottom:6px;border:1px solid var(--color-border-tertiary);';
            const nombre = encontrado.dataset.nombre || encontrado.value;
            const motor = encontrado.dataset.motor || '';
            const info = document.createElement('div');
            const nombreEl = document.createElement('strong');
            const motorEl = document.createElement('small');
            const quitarBtn = document.createElement('button');

            nombreEl.textContent = nombre;
            motorEl.textContent = 'Motor: ' + motor;
            motorEl.style.cssText = 'color:var(--color-text-secondary);margin-left:8px;';

            quitarBtn.type = 'button';
            quitarBtn.textContent = 'x';
            quitarBtn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:1.1rem;';
            quitarBtn.addEventListener('click', function() {
                deseleccionarMoto(encontrado.value);
            });

            info.appendChild(nombreEl);
            info.appendChild(motorEl);
            t.appendChild(info);
            t.appendChild(quitarBtn);
            motosSeleccionadas.appendChild(t);
        }

        // sale-choice ya no existe — animacion omitida, la tarjeta se muestra en motos-seleccionadas

        if (vehiculoScanInput) {
            vehiculoScanInput.value = "";
            vehiculoScanInput.focus();
        }

        }
        recalcular();
    }

    const vehiculoQrModal = document.getElementById("vehiculo_qr_modal");
    const vehiculoQrOpenBtn = document.getElementById("vehiculo_scan_camera_btn");
    const vehiculoQrCloseBtn = document.getElementById("vehiculo_qr_close");

    let vehiculoQrScanner = null;

    async function cerrarScannerVehiculo() {
        if (vehiculoQrScanner) {
            try {
                await vehiculoQrScanner.stop();
                await vehiculoQrScanner.clear();
            } catch (e) {}
        }

        vehiculoQrScanner = null;

        if (vehiculoQrModal) {
            vehiculoQrModal.hidden = true;
        }
    }

    async function abrirScannerVehiculo() {
        if (!vehiculoQrModal) return;

        try {
            await cargarHtml5Qrcode();
        } catch (e) {
            mostrarFeedbackScan("No se cargo el lector QR. Revisa conexion o bloqueo del navegador.", "error");
            return;
        }

        if (typeof Html5Qrcode === "undefined") {
            mostrarFeedbackScan("No se cargo el lector QR. Revisa conexion o bloqueo del navegador.", "error");
            return;
        }

        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            mostrarFeedbackScan("La camara requiere HTTPS o localhost. Abri el panel con HTTPS y revisa permisos del navegador.", "error");
            return;
        }

        vehiculoQrModal.hidden = false;

        try {
            vehiculoQrScanner = new Html5Qrcode("vehiculo_qr_reader");
            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                mostrarFeedbackScan("No se encontro ninguna camara en esta computadora.", "error");
                await cerrarScannerVehiculo();
                return;
            }

            const preferred = cameras.find((cam) => {
                const label = String(cam.label || "").toLowerCase();
                return label.includes("back") || label.includes("rear") || label.includes("environment") || label.includes("trasera");
            }) || cameras[0];

            await vehiculoQrScanner.start(
                preferred.id,
                {
                    fps: 10,
                    qrbox: { width: 260, height: 260 }
                },
                async (decodedText) => {
                    const valor = String(decodedText || "").trim();

                    if (vehiculoScanInput) {
                        vehiculoScanInput.value = valor;
                    }

                    await cerrarScannerVehiculo();
                    seleccionarVehiculoPorMotor(valor);
                },
                () => {}
            );
        } catch (error) {
            console.error(error);
            const name = error && error.name ? error.name : "";
            let detail = error && error.message ? error.message : "No se pudo iniciar la camara QR.";

            if (name === "NotAllowedError" || name === "PermissionDeniedError") {
                detail = "Permiso denegado. Toca el icono de ajustes junto a la URL, entra a permisos del sitio, permite la camara y volve a escanear.";
            } else if (name === "NotFoundError" || name === "DevicesNotFoundError") {
                detail = "No se encontro camara disponible en esta computadora.";
            } else if (name === "NotReadableError" || name === "TrackStartError") {
                detail = "La camara esta ocupada por otra aplicacion o bloqueada por el sistema.";
            }

            mostrarFeedbackScan(detail, "error");
            await cerrarScannerVehiculo();
        }
    }

    function cargarHtml5Qrcode() {
        if (typeof Html5Qrcode !== "undefined") {
            return Promise.resolve();
        }

        if (html5QrcodeLoader) {
            return html5QrcodeLoader;
        }

        html5QrcodeLoader = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = html5QrcodeSrc;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return html5QrcodeLoader;
    }

    if (vehiculoQrOpenBtn) {
        vehiculoQrOpenBtn.addEventListener("click", abrirScannerVehiculo);
    }

    if (vehiculoQrCloseBtn) {
        vehiculoQrCloseBtn.addEventListener("click", cerrarScannerVehiculo);
    }

    if (vehiculoScanBtn) {
        vehiculoScanBtn.addEventListener("click", () => seleccionarVehiculoPorMotor(vehiculoScanInput?.value || ""));
    }

    if (vehiculoScanInput) {
        vehiculoScanInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                seleccionarVehiculoPorMotor(vehiculoScanInput.value);
            }
        });
    }

    function recalcular() {
        let subtotalMotos = 0;
        let subtotalRepuestos = 0;
        let costoTotal = 0;

        const clienteId = clienteSelect.value;
        const monedaVenta = monedaVentaSelect.value;
        const tipoCambio = parseFloat(tipoCambioInput.value || <?= json_encode(defaultTipoCambioUSD()) ?>);
        const tipoCliente = tipoClienteSelect?.value || "Final";
        const metodoPago = metodoPagoSelect?.value || "Efectivo";

        vehiculoChecks.forEach(check => {
            const estado = check.dataset.estado;
            const clienteReserva = check.dataset.reservaCliente;
            const precio = precioComercial(check);
            const gasto = parseFloat(check.dataset.gasto || 0);
            const moneda = check.dataset.moneda;
            const tcVehiculo = parseFloat(check.dataset.tc || tipoCambio);

            if (check.checked) {
                if (estado === "Reservado" && clienteReserva && clienteReserva !== clienteId) {
                    check.checked = false;
                    alert("Ese vehiculo esta reservado para otro cliente.");
                    return;
                }

                const precioConvertido = convertirMoneda(precio, moneda, monedaVenta, tcVehiculo);
                const gastoConvertido = convertirMoneda(gasto, moneda, monedaVenta, tcVehiculo);
                subtotalMotos += precioConvertido;
                costoTotal += gastoConvertido;
            }
        });

        repuestosData.forEach(repuesto => {
            const cantidad = cantidadRepuestoPorId(repuesto.id);
            const precio = precioComercial({
                dataset: {
                    precio: repuesto.precio,
                    precioDistribuidor: repuesto.precioDistribuidor ?? ""
                }
            });
            const gasto = parseFloat(repuesto.gasto || 0);
            const moneda = repuesto.moneda;
            const tcRepuesto = parseFloat(repuesto.tc || tipoCambio);

            if (cantidad > 0) {
                const precioConvertido = convertirMoneda(precio, moneda, monedaVenta, tcRepuesto);
                const gastoConvertido = convertirMoneda(gasto, moneda, monedaVenta, tcRepuesto);
                subtotalRepuestos += precioConvertido * cantidad;
                costoTotal += gastoConvertido * cantidad;
            }
        });

        const subtotalBruto = redondear(subtotalMotos + subtotalRepuestos);

        let descuentoAplicado = 0;
        let recargoAplicado = 0;
        let comisionTarjeta = 0;
        let comisionDistribuidorAplicada = 0;
        let comisionVendedorAplicada = 0;

        if (metodosContado.has(metodoPago) && descuentoContadoPct > 0) {
            descuentoAplicado = redondear(subtotalBruto * descuentoContadoPct / 100);
        }

        const baseLuegoDescuento = redondear(subtotalBruto - descuentoAplicado);

        if (metodoPago === "Tarjeta") {
            /*
             * LTECO:VENTA_EXCEL_TARJETA_SIN_RECARGO_CLIENTE_JS_V3
             * La tarjeta no aumenta el total al cliente.
             * Se muestra como comisión/costo interno y resta ganancia.
             */
            recargoAplicado = 0;
            const comisionTarjetaPct = tipoTarjetaSelect?.value === "Débito" ? 2 : recargoTarjetaPct;
            comisionTarjeta = redondear(baseLuegoDescuento * comisionTarjetaPct / 100);
        }

        const total = redondear(baseLuegoDescuento);
        const montoIVA = calcularIVAIncluido(total);
        const totalSinIVA = redondear(total - montoIVA);

        if (tipoCliente === "Distribuidor" && comisionDistribuidorPct > 0) {
            comisionDistribuidorAplicada = redondear(total * comisionDistribuidorPct / 100); /* LTECO:FIX_COMISION_DISTRIBUIDOR_SOBRE_TOTAL_CON_IVA */
        }

        if (comisionVendedorPct > 0) {
            comisionVendedorAplicada = redondear(total * comisionVendedorPct / 100);
        }

        const ganancia = redondear(totalSinIVA - costoTotal - comisionTarjeta - comisionDistribuidorAplicada - comisionVendedorAplicada);
        ultimoTotal = total;

        subtotalMotosEl.textContent = formatear(subtotalMotos, monedaVenta);
        subtotalRepuestosEl.textContent = formatear(subtotalRepuestos, monedaVenta);
        if (subtotalBrutoPreviewEl) subtotalBrutoPreviewEl.textContent = formatear(subtotalBruto, monedaVenta);
        if (descuentoPreviewEl) descuentoPreviewEl.textContent = formatear(descuentoAplicado, monedaVenta);
        // LTECO:VENTA_EXCEL_PREVIEW_COMISION_TARJETA_INTERNA_V3
          if (recargoPreviewEl) recargoPreviewEl.textContent = formatear(comisionTarjeta, monedaVenta);
        if (ivaPreviewEl) ivaPreviewEl.textContent = formatear(montoIVA, monedaVenta);
        if (totalSinIvaPreviewEl) totalSinIvaPreviewEl.textContent = formatear(totalSinIVA, monedaVenta);
        totalGeneralEl.textContent = formatear(total, monedaVenta);
        if (gananciaEstimadaEl) gananciaEstimadaEl.textContent = formatear(ganancia, monedaVenta);

        actualizarResumen();
    }

    ventaForm.addEventListener("submit", (event) => {
    const errorBox = document.getElementById("venta_error_box");
    if (productosSeleccionados().length === 0) {
        event.preventDefault();

        if (errorBox) {
            errorBox.textContent = "Seleccioná al menos una moto o repuesto antes de confirmar la venta.";
            errorBox.hidden = false;
            errorBox.scrollIntoView({ behavior: "smooth", block: "center" });
        }

        return;
    }

    const montoPagado = montoPagadoInput && montoPagadoInput.value !== "" ? parseFloat(montoPagadoInput.value || 0) : ultimoTotal;
    if (montoPagado - ultimoTotal > 0.00001) {
        event.preventDefault();
        if (errorBox) {
            errorBox.textContent = "El monto pagado no puede superar el total de la venta.";
            errorBox.hidden = false;
            errorBox.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        return;
    }
});

    // Al iniciar resetear marca y cuotas para evitar cache del browser
    if (tipoTarjetaSelect) tipoTarjetaSelect.selectedIndex = 0;
    if (marcaTarjetaSelect) marcaTarjetaSelect.selectedIndex = 0;
    if (cuotasTarjetaSelect) cuotasTarjetaSelect.innerHTML = '<option value="">-- No aplica --</option>';
    actualizarClienteRapidoFiscal();
    actualizarClienteExistenteResumen();
    actualizarCamposTarjeta();
    recalcular();
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
