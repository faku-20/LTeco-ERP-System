<?php
$pageTitle = "Nueva venta | Lteco";
require_once __DIR__ . "/_common.php";

$idDistribuidor = requiereDistribuidorPanel();

$conexion = new \Lteco\Infrastructure\Db\Connection($pdo);
$clienteService = new \Lteco\Application\Cliente\ClienteCrudService(
    new \Lteco\Infrastructure\Repository\ClienteCrudRepository($conexion)
);
$clientes = $clienteService->listarParaSelector();
$ventaDistribuidor = new \Lteco\Application\Distribuidor\DistribuidorVentaService(
    new \Lteco\Infrastructure\Repository\DistribuidorVentaRepository($conexion),
    new \Lteco\Application\Venta\VentaLineasService($conexion)
);
$stock = $ventaDistribuidor->stockDisponible($idDistribuidor);

$ventaCommercial = new \Lteco\Application\Venta\VentaCommercialService(
    new \Lteco\Infrastructure\Repository\VentaCommercialRepository($conexion)
);
$tasaIVA = $ventaCommercial->configuracion(defaultTasaIVA())['TasaIVA'];
$usrComisionDist = $ventaCommercial->usuarioInternoDistribuidor(ROL_DISTRIBUIDOR);
$idUsuarioComisionDistribuidor = $usrComisionDist['IdUsuario'];
$comisionVendedorPct = $usrComisionDist['ComisionDistribuidorPct'];

$flashError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();

        $clienteId     = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
        $idStock       = (int)($_POST['id_stock'] ?? 0);
        $cantidad      = enteroNoNegativo($_POST['cantidad'] ?? 0);
        $metodoPago    = trim((string)($_POST['metodo_pago'] ?? 'Efectivo'));
        $numeroFactura = limpiarTextoOpcional(normalizarTextoHumano($_POST['numero_factura'] ?? '', 80));
        $observaciones = limpiarTextoOpcional($_POST['observaciones'] ?? null);

        if (!in_array($metodoPago, ['Efectivo', 'Transferencia', 'Tarjeta'], true)) {
            $metodoPago = 'Efectivo';
        }
        if ($idStock <= 0 || $cantidad <= 0) {
            throw new RuntimeException('Seleccioná un producto y una cantidad válida.');
        }
        $idempotencyKey = panelIdempotencyRequestKey();
        $idempotencyHash = panelIdempotencyRequestHash('panel.distribuidor.venta.crear', $_POST);

        $pdo->beginTransaction();
        $idempotencyRow = panelIdempotencyClaim(
            $pdo,
            'panel.distribuidor.venta.crear',
            $idempotencyKey,
            $idempotencyHash,
            (int)(usuarioActual()['IdUsuario'] ?? 0)
        );
        if ($idempotencyRow !== null) {
            $pdo->commit();
            redirect((string)($idempotencyRow['RedirectUrl'] ?: panelBaseUrl('distribuidores/ventas.php')));
        }

        $ventaPreparada = $ventaDistribuidor->prepararVenta($idStock, $idDistribuidor, $cantidad);

        // Find or create client
        if (!$clienteId) {
            $nuevoTipoFiscal = trim((string)($_POST['nuevo_tipo_fiscal'] ?? 'Consumidor final'));
            $nuevoNombre = normalizarTextoHumano($_POST['nuevo_nombre'] ?? '', 80);
            $nuevoApellido = normalizarTextoHumano($_POST['nuevo_apellido'] ?? '', 80);
            $nuevoTelefono = normalizarTelefono($_POST['nuevo_telefono'] ?? null);
            $nuevoCorreo = limpiarTextoOpcional(mb_strtolower(normalizarTextoHumano($_POST['nuevo_correo'] ?? '', 150), 'UTF-8'));
            $nuevoCedula = normalizarCedula($_POST['nuevo_cedula'] ?? '');
            $nuevoDireccion = limpiarTextoOpcional(normalizarTextoHumano($_POST['nuevo_direccion'] ?? '', 255));
            $nuevoRut = limpiarTextoOpcional(normalizarTextoHumano($_POST['nuevo_rut'] ?? '', 40));

            if (!in_array($nuevoTipoFiscal, tiposClienteFiscalSistema(), true)) {
                $nuevoTipoFiscal = 'Consumidor final';
            }

            if ($nuevoNombre === '' || ($nuevoTipoFiscal === 'Consumidor final' && $nuevoApellido === '')) {
                throw new RuntimeException($nuevoTipoFiscal === 'Empresa/RUT'
                    ? 'Tenés que seleccionar un cliente o cargar la razón social del cliente nuevo.'
                    : 'Tenés que seleccionar un cliente o cargar nombre y apellido del cliente nuevo.');
            }

            if ($nuevoTipoFiscal === 'Empresa/RUT' && $nuevoRut === null) {
                throw new RuntimeException('El RUT es obligatorio para clientes Empresa/RUT.');
            }

            if ($nuevoTipoFiscal === 'Consumidor final') {
                $nuevoRut = null;
            }

            if ($nuevoTelefono !== null && !telefonoValido($nuevoTelefono)) {
                throw new RuntimeException('El teléfono del cliente nuevo no tiene un formato válido.');
            }

            if ($nuevoCorreo !== null && !filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El correo del cliente nuevo no tiene un formato válido.');
            }

            if ($nuevoTelefono !== null && !$clienteCrud->telefonoDisponible($nuevoTelefono, null)) {
                throw new RuntimeException('Ya existe un cliente con ese teléfono. Seleccionalo desde la lista.');
            }

            if ($nuevoCorreo !== null && !$clienteCrud->correoDisponible($nuevoCorreo, null)) {
                throw new RuntimeException('Ya existe un cliente con ese correo. Seleccionalo desde la lista.');
            }

            if ($nuevoTipoFiscal === 'Consumidor final' && $nuevoCedula === '') {
                throw new RuntimeException('La cédula es obligatoria para el cliente nuevo (Consumidor final).');
            }

            if ($nuevoCedula !== '' && !cedulaUruguayaValida($nuevoCedula)) {
                throw new RuntimeException('La cédula del cliente nuevo no es válida.');
            }

            if ($nuevoCedula !== '' && !$clienteCrud->cedulaDisponible($nuevoCedula, null)) {
                throw new RuntimeException('Ya existe un cliente con esa cédula. Seleccionalo desde la lista.');
            }

            $clienteColumns = [
                'NombreApellido' => trim($nuevoNombre . ' ' . $nuevoApellido),
                'Telefono' => $nuevoTelefono,
                'Correo' => $nuevoCorreo,
                'TipoFiscal' => $nuevoTipoFiscal,
                'Cedula' => limpiarTextoOpcional($nuevoCedula),
                'Direccion' => $nuevoDireccion,
                'RUT' => $nuevoRut,
            ];

            $clienteId = $clienteService->crear([
                'nombre_apellido' => $clienteColumns['NombreApellido'],
                'telefono' => $clienteColumns['Telefono'],
                'correo' => $clienteColumns['Correo'],
                'tipo_fiscal' => $clienteColumns['TipoFiscal'],
                'cedula' => $clienteColumns['Cedula'],
                'direccion' => $clienteColumns['Direccion'],
                'rut' => $clienteColumns['RUT'],
            ]);

            registrarAuditoria($pdo, 'CREAR_CLIENTE', 'Clientes', 'Cliente creado desde venta distribuidor: ' . $nuevoNombre . ' ' . $nuevoApellido, [
                'id_cliente' => $clienteId,
                'tipo_fiscal' => $nuevoTipoFiscal,
                'origen' => 'distribuidor',
            ]);
        } else {
            if ($clienteService->obtener($clienteId) === null) {
                throw new RuntimeException('El cliente seleccionado no existe.');
            }
        }

        $ventaResultado = $ventaDistribuidor->registrarVenta(
            $ventaPreparada,
            (int)$clienteId,
            $metodoPago,
            $idUsuarioComisionDistribuidor,
            $observaciones,
            $comisionVendedorPct,
            $tasaIVA
        );
        $idVenta = (int)$ventaResultado['idVenta'];
        $precioVenta = (float)$ventaResultado['precioVenta'];
        $comisionDistribuidorPct = (float)$ventaResultado['comisionDistribuidorPct'];
        $subtotal = (float)$ventaResultado['subtotal'];
        $comisionDistribuidor = (float)$ventaResultado['comisionDistribuidor'];
        $comisionVendedor = (float)$ventaResultado['comisionVendedor'];
        $montoIVA = (float)$ventaResultado['montoIVA'];
        $ganancia = (float)$ventaResultado['ganancia'];

        $ventaDistribuidor->registrarComisiones(
            $ventaResultado,
            $comisionVendedorPct,
            dbTieneTabla($pdo, 'distribuidor_comision'),
            dbTieneTabla($pdo, 'gasto')
        );

        $ventaDistribuidor->facturarRemito(
            $ventaResultado,
            $numeroFactura,
            dbTieneTabla($pdo, 'remito')
        );

        registrarAuditoria($pdo, 'VENTA_DISTRIBUIDOR', 'Distribuidores', 'Venta distribuidor #' . $idVenta, [
            'id_venta'        => $idVenta,
            'id_distribuidor' => $idDistribuidor,
            'id_stock'        => $idStock,
            'cantidad'        => $cantidad,
            'precio_venta'    => $precioVenta,
            'precio_fuente'   => 'distribuidor_stock.PrecioVenta',
            'numero_factura'  => $numeroFactura,
            'total'           => $subtotal,
            'iva'             => $montoIVA,
            'comision_distribuidor' => $comisionDistribuidor,
            'comision_vendedor'     => $comisionVendedor,
            'ganancia_real'         => $ganancia,
        ]);
        $redirectUrl = panelBaseUrl('distribuidores/ventas.php');
        panelIdempotencyComplete($pdo, $idempotencyKey, 'venta', $idVenta, $redirectUrl);

        $pdo->commit();

        redirectWithFlash($redirectUrl, 'success', 'Venta #' . $idVenta . ' registrada correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $flashError = mensajeErrorSeguro($e, 'No se pudo registrar la venta.');
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";

$vehiculosStock = array_values(array_filter($stock, static fn($i) => $i['TipoItem'] === 'Vehiculo'));
$repuestosStock = array_values(array_filter($stock, static fn($i) => $i['TipoItem'] === 'Repuesto'));
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Nueva venta</h1>
            <p class="subtle">Registrá una venta del stock que tenés asignado.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <?php if ($flashError): ?>
        <div class="notice notice--error" role="alert"><?= h($flashError) ?></div>
    <?php endif; ?>

    <?php if (!$stock): ?>
        <div class="notice notice--info">
            No tenés stock asignado todavía.
            <a href="<?= panelBaseUrl('distribuidores/nuevo_pedido.php') ?>">Solicitá stock al administrador.</a>
        </div>
    <?php else: ?>

    <form method="POST" id="ventaForm">
        <?= csrfInput() ?>
        <?= panelIdempotencyInput() ?>

        <div class="section-box" style="max-width:640px;">

            <!-- Cliente -->
            <div class="form-group">
                <label for="cliente_id">Cliente existente</label>
                <select name="cliente_id" id="cliente_id">
                    <option value="">-- Seleccionar cliente --</option>
                    <?php foreach ($clientes as $c): ?>
                        <option
                            value="<?= (int)$c['IdCliente'] ?>"
                            data-telefono="<?= h($c['Telefono'] ?? '', '') ?>"
                            data-correo="<?= h($c['Correo'] ?? '', '') ?>"
                            data-tipo-fiscal="<?= h($c['TipoFiscal'] ?? 'Consumidor final', '') ?>"
                            data-cedula="<?= h($c['Cedula'] ?? '', '') ?>"
                            data-direccion="<?= h($c['Direccion'] ?? '', '') ?>"
                            data-rut="<?= h($c['RUT'] ?? '', '') ?>"
                        ><?= h($c['NombreApellido']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

              <div class="form-group full" id="clienteExistenteResumen" hidden style="margin-top:8px;margin-bottom:10px;">
                  <div class="notice notice--info sale-client-summary sale-client-summary--compact">
                      <div class="sale-client-summary__title" id="clienteExistenteTitulo">Cliente seleccionado</div>
                      <div class="sale-client-summary__meta" id="clienteExistenteDatos"></div>
                  </div>
              </div>

            <details id="nuevoClientePanel" style="margin-top:6px;margin-bottom:20px;">
                <summary style="cursor:pointer;font-size:.9rem;padding:4px 0;">+ Crear cliente nuevo</summary>
                <p class="help-text">Usá estos campos solo si el cliente todavía no existe. Si elegís un cliente existente, estos datos no se usan.</p>
                <div id="clienteDuplicadoAviso" style="display:none;margin-top:6px;padding:8px 12px;background:#fef9c3;border-radius:8px;border-left:3px solid #f59e0b;font-size:.85rem;color:#92400e;"></div>

                <div class="form-grid" style="margin-top:12px;">
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
                        <input type="text" name="nuevo_nombre" id="nuevo_nombre" maxlength="120" placeholder="Nombre o razón social">
                    </div>

                    <div class="form-group">
                        <label for="nuevo_apellido" id="nuevo_apellido_label">Apellido</label>
                        <input type="text" name="nuevo_apellido" id="nuevo_apellido" maxlength="120" placeholder="Apellido o contacto">
                    </div>

                    <div class="form-group">
                        <label for="nuevo_telefono">Teléfono</label>
                        <input type="text" name="nuevo_telefono" id="nuevo_telefono" placeholder="09X XXX XXX">
                    </div>

                    <div class="form-group">
                        <label for="nuevo_correo">Correo</label>
                        <input type="email" name="nuevo_correo" id="nuevo_correo" maxlength="100" placeholder="cliente@correo.com">
                    </div>

                    <div class="form-group">
                        <label for="nuevo_cedula">Cédula</label>
                        <input type="text" name="nuevo_cedula" id="nuevo_cedula" maxlength="40" placeholder="Opcional">
                    </div>

                    <div class="form-group" id="nuevo_rut_field">
                        <label for="nuevo_rut">RUT</label>
                        <input type="text" name="nuevo_rut" id="nuevo_rut" maxlength="40" placeholder="Obligatorio si es Empresa/RUT">
                    </div>

                    <div class="form-group full">
                        <label for="nuevo_direccion">Dirección</label>
                        <input type="text" name="nuevo_direccion" id="nuevo_direccion" maxlength="255" placeholder="Opcional">
                    </div>
                </div>
            </details>

            <hr style="margin:0 0 20px;border:none;border-top:1px solid var(--color-border-secondary);">

            <!-- Producto -->
            <div class="form-group">
                <label for="id_stock">Producto <span style="color:var(--color-danger,#ef4444)">*</span></label>
                <select name="id_stock" id="id_stock" required>
                    <option value="">-- Seleccionar producto --</option>
                    <?php if ($vehiculosStock): ?>
                        <optgroup label="Vehículos">
                            <?php foreach ($vehiculosStock as $item): ?>
                                <option
                                    value="<?= (int)$item['IdStock'] ?>"
                                    data-cantidad="<?= (int)$item['Cantidad'] ?>"
                                    data-precio-venta="<?= number_format((float)$item['PrecioVenta'], 2, '.', '') ?>"
                                    data-precio-minimo="<?= number_format((float)$item['PrecioMinimo'], 2, '.', '') ?>"
                                    data-numero-motor="<?= h($item['VehiculoNumeroMotor'] ?? '', '') ?>"
                                ><?= h(distribuidorItemLabel($item)) ?> · Stock: <?= (int)$item['Cantidad'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if ($repuestosStock): ?>
                        <optgroup label="Repuestos">
                            <?php foreach ($repuestosStock as $item): ?>
                                <option
                                    value="<?= (int)$item['IdStock'] ?>"
                                    data-cantidad="<?= (int)$item['Cantidad'] ?>"
                                    data-precio-venta="<?= number_format((float)$item['PrecioVenta'], 2, '.', '') ?>"
                                    data-precio-minimo="<?= number_format((float)$item['PrecioMinimo'], 2, '.', '') ?>"
                                ><?= h(distribuidorItemLabel($item)) ?> · Stock: <?= (int)$item['Cantidad'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <div id="motorInfoBox" class="readonly-info-box" style="display:none;">
                <span class="readonly-info-box__label">N° Motor</span>
                <strong id="motorInfoVal" class="readonly-info-box__value"></strong>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="cantidad">Cantidad <span style="color:var(--color-danger,#ef4444)">*</span></label>
                    <input type="number" name="cantidad" id="cantidad" min="1" max="9999" value="1" required>
                </div>
                <div class="form-group">
                    <label for="precio_venta">Precio de venta sistema (UYU)</label>
                    <input
                        type="number"
                        id="precio_venta"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                        readonly
                        aria-readonly="true"
                        style="background:rgba(255,255,255,.06);border:1.5px solid var(--color-border-secondary,#3a332b);color:var(--color-text-primary,#f5f5f5);cursor:not-allowed;opacity:1;"
                    >
                    <small id="precioMinimoRef" style="display:none;margin-top:4px;color:var(--color-text-secondary,#d1c7b8);">
                        Este precio lo define Ltecobike. Precio mínimo interno: <strong id="precioMinimoVal"></strong>
                    </small>
                </div>
            </div>

            <input type="hidden" name="metodo_pago" value="Efectivo">


            <input type="hidden" name="numero_factura" id="numero_factura" value="">


            <input type="hidden" name="observaciones" id="observaciones" value="">


            <div id="ventaErrorBox" class="notice notice--error" role="alert" hidden></div>

            <div class="form-actions">
                <button type="submit" class="btn">Confirmar venta</button>
            </div>
        </div>
    </form>

    <?php endif; ?>
</main>

<style>
    .readonly-info-box {
        margin-bottom: 16px;
        padding: 11px 14px;
        border: 1.5px solid var(--color-border-secondary,#3a332b);
        border-radius: 10px;
        background: rgba(255,255,255,.06);
        color: var(--color-text-primary,#f5f5f5);
        box-sizing: border-box;
    }

    .readonly-info-box__label {
        display: block;
        margin-bottom: 4px;
        color: var(--color-text-secondary,#d1c7b8);
        font-size: .74rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .readonly-info-box__value {
        display: block;
        font-family: monospace;
        letter-spacing: .05em;
        color: var(--color-text-primary,#f5f5f5);
        word-break: break-word;
    }
</style>

<style>
    .sale-client-summary--compact {
        padding: 8px 12px;
        border-radius: 10px;
        min-height: auto;
        font-size: .88rem;
        line-height: 1.35;
        margin-top: 2px;
    }

    .sale-client-summary__title {
        font-weight: 700;
        font-size: .92rem;
        margin-bottom: 3px;
    }

    .sale-client-summary__meta {
        color: var(--color-text-secondary,#d6cec1);
        font-size: .84rem;
        word-break: break-word;
    }
</style>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    const clienteSelect       = document.getElementById('cliente_id');
    const nuevoTipoFiscal     = document.getElementById('nuevo_tipo_fiscal');
    const nuevoNombre         = document.getElementById('nuevo_nombre');
    const nuevoApellido       = document.getElementById('nuevo_apellido');
    const nuevoTelefono       = document.getElementById('nuevo_telefono');
    const nuevoRut            = document.getElementById('nuevo_rut');
    const nuevoRutField       = document.getElementById('nuevo_rut_field');
    const nuevoNombreLabel    = document.getElementById('nuevo_nombre_label');
    const nuevoApellidoLabel  = document.getElementById('nuevo_apellido_label');
    const clienteExistenteResumen = document.getElementById('clienteExistenteResumen');
    const clienteExistenteTitulo = document.getElementById('clienteExistenteTitulo');
    const clienteExistenteDatos = document.getElementById('clienteExistenteDatos');
    const stockSelect     = document.getElementById('id_stock');
    const cantidadInput   = document.getElementById('cantidad');
    const precioInput     = document.getElementById('precio_venta');
    const precioMinimoRef = document.getElementById('precioMinimoRef');
    const precioMinimoVal = document.getElementById('precioMinimoVal');
    const motorInfoBox    = document.getElementById('motorInfoBox');
    const motorInfoVal    = document.getElementById('motorInfoVal');
    const ventaForm       = document.getElementById('ventaForm');
    const errorBox        = document.getElementById('ventaErrorBox');
    const dupAviso        = document.getElementById('clienteDuplicadoAviso');

    function fmtMonto(v) {
        return '$ ' + Number(v || 0).toLocaleString('es-UY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if (stockSelect) {
        stockSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!this.value) {
                if (precioMinimoRef) precioMinimoRef.style.display = 'none';
                if (motorInfoBox) motorInfoBox.style.display = 'none';
                return;
            }
            const maxCant    = parseInt(opt.dataset.cantidad || 1, 10);
            const precioSug  = parseFloat(opt.dataset.precioVenta || 0);
            const minPrecio  = parseFloat(opt.dataset.precioMinimo || 0);
            const numMotor   = opt.dataset.numeroMotor || '';

            if (cantidadInput) {
                cantidadInput.max   = maxCant;
                cantidadInput.value = Math.min(parseInt(cantidadInput.value || 1, 10), maxCant);
            }
            if (precioInput) {
                precioInput.value = precioSug > 0 ? precioSug.toFixed(2) : '';
                precioInput.min   = minPrecio.toFixed(2);
            }
            if (precioMinimoRef && precioMinimoVal) {
                precioMinimoVal.textContent = fmtMonto(minPrecio);
                precioMinimoRef.style.display = '';
            }
            if (motorInfoBox && motorInfoVal) {
                if (numMotor) {
                    motorInfoVal.textContent = numMotor;
                    motorInfoBox.style.display = '';
                } else {
                    motorInfoBox.style.display = 'none';
                }
            }
        });
    }

    function actualizarClienteExistenteResumenDistribuidor() {
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

    if (clienteSelect) {
        clienteSelect.addEventListener('change', actualizarClienteExistenteResumenDistribuidor);
        actualizarClienteExistenteResumenDistribuidor();
    }

    function actualizarClienteFiscalDistribuidor() {
        const esEmpresa = nuevoTipoFiscal && nuevoTipoFiscal.value === 'Empresa/RUT';

        if (nuevoNombreLabel) nuevoNombreLabel.textContent = esEmpresa ? 'Razón social' : 'Nombre';
        if (nuevoApellidoLabel) nuevoApellidoLabel.textContent = esEmpresa ? 'Contacto' : 'Apellido';

        if (nuevoRutField) nuevoRutField.hidden = !esEmpresa;
        if (nuevoRut) {
            nuevoRut.required = !!esEmpresa;
            nuevoRut.disabled = !esEmpresa;
            if (!esEmpresa) nuevoRut.value = '';
        }
    }

    if (nuevoTipoFiscal) {
        nuevoTipoFiscal.addEventListener('change', actualizarClienteFiscalDistribuidor);
        actualizarClienteFiscalDistribuidor();
    }

    if (nuevoTelefono && clienteSelect) {
        nuevoTelefono.addEventListener('blur', function () {
            const tel = this.value.trim();
            if (!tel) { if (dupAviso) dupAviso.style.display = 'none'; return; }
            const match = Array.from(clienteSelect.options).find(o => o.dataset.telefono && o.dataset.telefono.trim() === tel);
            if (match) {
                clienteSelect.value = match.value;
                actualizarClienteExistenteResumenDistribuidor();
                if (dupAviso) {
                    dupAviso.textContent = 'Ya existe ' + match.text + ' con ese teléfono — lo seleccionamos automáticamente.';
                    dupAviso.style.display = '';
                }
            } else {
                if (dupAviso) dupAviso.style.display = 'none';
            }
        });
    }

    if (ventaForm) {
        ventaForm.addEventListener('submit', function (e) {
            const clienteExistente = clienteSelect && clienteSelect.value;
            const nombreNuevo = nuevoNombre ? nuevoNombre.value.trim() : '';
            const apellidoNuevo = nuevoApellido ? nuevoApellido.value.trim() : '';
            const tipoFiscalNuevo = nuevoTipoFiscal ? nuevoTipoFiscal.value : 'Consumidor final';
            const esEmpresaNueva = !clienteExistente && tipoFiscalNuevo === 'Empresa/RUT';
            const rutNuevo = nuevoRut ? nuevoRut.value.trim() : '';
            const tieneCliente = clienteExistente || (nombreNuevo && (esEmpresaNueva || apellidoNuevo));
            const tieneProducto = stockSelect && stockSelect.value;
            const cantidad      = cantidadInput ? parseInt(cantidadInput.value || '0', 10) : 0;
            const precio        = precioInput ? parseFloat(precioInput.value || 0) : 0;
            let msg = '';

            if (!tieneCliente) {
                msg = esEmpresaNueva
                    ? 'Seleccioná un cliente o completá la razón social del cliente nuevo.'
                    : 'Seleccioná un cliente o completá nombre y apellido del cliente nuevo.';
            }
            else if (esEmpresaNueva && !rutNuevo) msg = 'El RUT es obligatorio para clientes Empresa/RUT.';
            else if (!tieneProducto) msg = 'Seleccioná un producto.';

            else if (cantidad <= 0)  msg = 'La cantidad debe ser mayor a cero.';
            else if (precio <= 0)    msg = 'El precio de venta debe ser mayor a cero.';
            else {
                const opt = stockSelect.options[stockSelect.selectedIndex];
                const min = parseFloat(opt?.dataset?.precioMinimo || 0);
                if (precio < min - 0.001) {
                    msg = 'El precio no puede ser menor al mínimo (' + fmtMonto(min) + ').';
                }
            }

            if (msg) {
                e.preventDefault();
                if (errorBox) {
                    errorBox.textContent = msg;
                    errorBox.hidden = false;
                    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
