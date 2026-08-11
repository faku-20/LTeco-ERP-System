<?php
$pageTitle = "Nuevo pedido distribuidor | ERP";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

$idDistribuidor = esDistribuidor() ? requiereDistribuidorPanel() : 0;
if (!esDistribuidor()) {
    requiereAdmin();
    $idDistribuidor = (int)($_GET['id'] ?? 0);
}

$pedidoService = new \Lteco\Application\Distribuidor\DistribuidorPedidoService(
    new \Lteco\Infrastructure\Repository\DistribuidorPedidoRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    ),
    new \Lteco\Application\Distribuidor\DistribuidorStockService(
        new \Lteco\Infrastructure\Repository\DistribuidorStockRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    )
);
$datosFormulario = $pedidoService->datosFormularioPedido(esAdmin());
$distribuidores = $datosFormulario['distribuidores'];
$vehiculos = $datosFormulario['vehiculos'];
$repuestos = $datosFormulario['repuestos'];

$form = [
    'id_distribuidor' => (string)$idDistribuidor,
    'tipo_item' => 'Repuesto',
    'id_item' => '',
    'cantidad' => '1',
    'observaciones' => '',
];

$whatsappLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();

        foreach (array_keys($form) as $campo) {
            $form[$campo] = trim((string)($_POST[$campo] ?? ''));
        }

        if (esDistribuidor()) {
            $form['id_distribuidor'] = (string)$idDistribuidor;
        }

        $idDistribuidorPost = (int)$form['id_distribuidor'];
        $tipoItem = $form['tipo_item'] === 'Vehiculo' ? 'Vehiculo' : 'Repuesto';
        $cantidad = max(1, enteroNoNegativo($form['cantidad'], 1));
        $observaciones = normalizarTextoHumano($form['observaciones'], 500);
        $idVehiculo = null;
        $idRepuesto = null;

        if ($idDistribuidorPost <= 0) {
            throw new InvalidArgumentException('Seleccioná un distribuidor.');
        }

        if ($tipoItem === 'Vehiculo') {
            $idVehiculo = $form['id_item'];
            $cantidad = 1;
        } else {
            $idRepuesto = (int)$form['id_item'];
        }

        $item = $pedidoService->itemSolicitable($tipoItem, $idVehiculo, $idRepuesto);
        if (!$item) {
            throw new InvalidArgumentException('El producto seleccionado no existe o no está disponible.');
        }

        if ($tipoItem === 'Vehiculo') {
            if ($pedidoService->vehiculoYaSolicitado($idVehiculo)) {
                throw new InvalidArgumentException('Este vehículo ya tiene un pedido pendiente o aprobado. No se puede solicitar dos veces el mismo motor.');
            }
        }

        $pdo->beginTransaction();

        $idPedido = $pedidoService->registrarPedidoAprobado(
            $idDistribuidorPost,
            $tipoItem,
            $idVehiculo,
            $idRepuesto,
            $cantidad,
            limpiarTextoOpcional($observaciones),
            (int)(usuarioActual()['IdUsuario'] ?? 0),
            dbTieneTabla($pdo, 'remito')
        );

        registrarAuditoria($pdo, 'PEDIDO_DISTRIBUIDOR_APROBADO', 'Distribuidores', 'Pedido #' . $idPedido . ' procesado automáticamente', [
            'id_pedido' => $idPedido, 'id_distribuidor' => $idDistribuidorPost,
        ]);
        $pdo->commit();

        setFlash('success', 'Stock solicitado correctamente. El ítem ya fue asignado a tu cuenta.');
        redirect(panelBaseUrl('distribuidores/nuevo_pedido.php'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo registrar el pedido.'));
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Nuevo pedido</h1>
            <p class="subtle">Seleccioná el ítem y la cantidad. El stock se asigna automáticamente.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <?php if ($whatsappLink): ?>
        <div class="notice notice--info">
            <a class="btn" target="_blank" rel="noopener" href="<?= h($whatsappLink, '') ?>">Notificar por WhatsApp</a>
        </div>
    <?php endif; ?>

    <section class="section-box">
        <form method="POST" class="form-grid">
            <?= csrfInput() ?>
            <?php if (esAdmin()): ?>
                <div class="form-group">
                    <label>Distribuidor</label>
                    <select name="id_distribuidor" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($distribuidores as $dist): ?>
                            <option value="<?= (int)$dist['IdDistribuidor'] ?>" <?= $form['id_distribuidor'] === (string)$dist['IdDistribuidor'] ? 'selected' : '' ?>><?= h($dist['Nombre'], '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="id_distribuidor" value="<?= (int)$idDistribuidor ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Tipo</label>
                <select name="tipo_item" id="tipo_item">
                    <option value="Repuesto">Repuesto</option>
                    <option value="Vehiculo">Vehículo</option>
                </select>
            </div>
            <div class="form-group" id="item_repuesto">
                <label>Repuesto</label>
                <select>
                    <?php foreach ($repuestos as $r):
                        $precioR = (float)($r['PrecioDistribuidor'] ?? 0) > 0 ? (float)$r['PrecioDistribuidor'] : (float)$r['PrecioVenta'];
                        $precioRLabel = simboloMoneda((string)($r['Moneda'] ?? 'UYU')) . number_format($precioR, 2, ',', '.');
                    ?>
                        <option value="<?= (int)$r['IdRepuesto'] ?>" data-stock="<?= (int)$r['Stock'] ?>"><?= h($r['Nombre'], '') ?> · <?= $precioRLabel ?> · Stock <?= (int)$r['Stock'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="item_vehiculo" style="display:none;">
                <label>Vehículo</label>
                <select>
                    <?php foreach ($vehiculos as $v):
                        $precioV = (float)$v['PrecioVenta'];
                        $precioVLabel = simboloMoneda((string)($v['Moneda'] ?? 'UYU')) . number_format($precioV, 2, ',', '.');
                    ?>
                        <option value="<?= h($v['IdVehiculo'], '') ?>" data-stock="<?= (int)$v['Stock'] ?>"><?= h($v['Nombre'], '') ?> · <?= h($v['IdVehiculo'], '') ?> · <?= $precioVLabel ?> · Stock <?= (int)$v['Stock'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="id_item" id="id_item" value="">
            <div class="form-group">
                <label>Cantidad</label>
                <input type="number" name="cantidad" id="cantidad" min="1" value="1">
            </div>
            <div id="stock-warning" class="notice notice--error" style="display:none;"></div>
            <div class="form-group full">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3"><?= h($form['observaciones'], '') ?></textarea>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Enviar pedido</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/pedidos.php') ?>">Cancelar</a>
            </div>
        </form>
    </section>
</main>
<script nonce="<?= cspNonce() ?>">
(function () {
    const tipo          = document.getElementById('tipo_item');
    const repuestoWrap  = document.getElementById('item_repuesto');
    const vehiculoWrap  = document.getElementById('item_vehiculo');
    const hidden        = document.getElementById('id_item');
    const cantidadInput = document.getElementById('cantidad');
    const warning       = document.getElementById('stock-warning');
    const repuestoSelect = repuestoWrap.querySelector('select');
    const vehiculoSelect = vehiculoWrap.querySelector('select');

    function stockActivo() {
        const sel = tipo.value === 'Vehiculo' ? vehiculoSelect : repuestoSelect;
        const opt = sel.options[sel.selectedIndex];
        return opt ? parseInt(opt.dataset.stock ?? '0', 10) : 0;
    }

    function checkStock() {
        const stock    = stockActivo();
        const cantidad = parseInt(cantidadInput.value, 10) || 1;
        const submitBtn = document.querySelector('button[type="submit"]');
        if (stock === 0) {
            warning.textContent = 'Este ítem no tiene stock disponible en ERP. No podés enviar el pedido.';
            warning.style.display = '';
            submitBtn.disabled = true;
        } else if (cantidad > stock) {
            warning.textContent = 'Estás pidiendo ' + cantidad + ' unidades pero el stock disponible es ' + stock + '. Ajustá la cantidad.';
            warning.style.display = '';
            submitBtn.disabled = true;
        } else {
            warning.style.display = 'none';
            submitBtn.disabled = false;
        }
    }

    function sync() {
        const esVehiculo = tipo.value === 'Vehiculo';
        repuestoWrap.style.display = esVehiculo ? 'none' : '';
        vehiculoWrap.style.display = esVehiculo ? '' : 'none';
        hidden.value = (esVehiculo ? vehiculoSelect : repuestoSelect).value || '';
        checkStock();
    }

    tipo.addEventListener('change', sync);
    repuestoSelect.addEventListener('change', sync);
    vehiculoSelect.addEventListener('change', sync);
    cantidadInput.addEventListener('input', checkStock);
    sync();
})();
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
