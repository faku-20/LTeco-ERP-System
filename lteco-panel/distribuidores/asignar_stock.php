<?php
$pageTitle = "Asignar stock | ERP";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

requiereAdmin();

$stockService = new \Lteco\Application\Distribuidor\DistribuidorStockService(
    new \Lteco\Infrastructure\Repository\DistribuidorStockRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$datosFormulario = $stockService->datosFormularioAsignacion();
$distribuidores = $datosFormulario['distribuidores'];
$vehiculos = $datosFormulario['vehiculos'];
$repuestos = $datosFormulario['repuestos'];

$form = [
    'id_distribuidor' => (string)($_GET['id'] ?? ''),
    'tipo_item' => 'Repuesto',
    'id_item' => '',
    'cantidad' => '1',
    'precio_venta' => '',
    'precio_minimo' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();

        foreach (array_keys($form) as $campo) {
            $form[$campo] = trim((string)($_POST[$campo] ?? ''));
        }

        $idDistribuidor = (int)$form['id_distribuidor'];
        $tipoItem = $form['tipo_item'] === 'Vehiculo' ? 'Vehiculo' : 'Repuesto';
        $cantidad = max(1, enteroNoNegativo($form['cantidad'], 1));
        $precioVenta = decimalNoNegativo($form['precio_venta']);
        $precioMinimo = decimalNoNegativo($form['precio_minimo']);
        $idVehiculo = null;
        $idRepuesto = null;

        if ($idDistribuidor <= 0) {
            throw new InvalidArgumentException('Seleccioná un distribuidor.');
        }

        if ($tipoItem === 'Vehiculo') {
            $idVehiculo = $form['id_item'];
            $cantidad = 1;
        } else {
            $idRepuesto = (int)$form['id_item'];
        }

        $item = $stockService->itemDisponible($tipoItem, $idVehiculo, $idRepuesto);
        if (!$item) {
            throw new InvalidArgumentException('El producto seleccionado no tiene stock disponible.');
        }
        if ($cantidad > (int)$item['Stock']) {
            throw new InvalidArgumentException('No hay stock suficiente para asignar esa cantidad.');
        }

        if ($precioVenta <= 0) {
            $precioVenta = (float)($item['PrecioDistribuidor'] ?? $item['PrecioVenta'] ?? 0);
        }
        if ($precioMinimo <= 0) {
            $precioMinimo = $precioVenta;
        }

        $pdo->beginTransaction();

        $stockService->asignarStock(
            $idDistribuidor,
            $tipoItem,
            $idVehiculo,
            $idRepuesto,
            $cantidad,
            $precioVenta,
            $precioMinimo,
            (int)$item['IdProducto'],
            (int)$item['Stock']
        );

        registrarAuditoria($pdo, 'ASIGNAR_STOCK_DISTRIBUIDOR', 'Distribuidores', 'Stock asignado a distribuidor', [
            'id_distribuidor' => $idDistribuidor,
            'tipo_item' => $tipoItem,
            'id_vehiculo' => $idVehiculo,
            'id_repuesto' => $idRepuesto,
            'cantidad' => $cantidad,
        ]);
        $pdo->commit();

        redirectWithFlash(panelBaseUrl('distribuidores/index.php'), 'success', 'Stock asignado correctamente.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo asignar el stock.'));
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Asignar stock</h1>
            <p class="subtle">Pasá unidades del stock interno al stock propio de un distribuidor.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <section class="section-box">
        <form method="POST" class="form-grid">
            <?= csrfInput() ?>
            <div class="form-group">
                <label>Distribuidor</label>
                <select name="id_distribuidor" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($distribuidores as $dist): ?>
                        <option value="<?= (int)$dist['IdDistribuidor'] ?>" <?= $form['id_distribuidor'] === (string)$dist['IdDistribuidor'] ? 'selected' : '' ?>><?= h($dist['Nombre'], '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select name="tipo_item" id="tipo_item">
                    <option value="Repuesto" <?= $form['tipo_item'] === 'Repuesto' ? 'selected' : '' ?>>Repuesto</option>
                    <option value="Vehiculo" <?= $form['tipo_item'] === 'Vehiculo' ? 'selected' : '' ?>>Vehículo</option>
                </select>
            </div>
            <div class="form-group" id="item_repuesto">
                <label>Repuesto</label>
                <select name="id_item_repuesto">
                    <?php foreach ($repuestos as $r): ?>
                        <option value="<?= (int)$r['IdRepuesto'] ?>" data-precio="<?= h((string)($r['PrecioDistribuidor'] ?: $r['PrecioVenta']), '') ?>"><?= h($r['Nombre'], '') ?> · Stock <?= (int)$r['Stock'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="item_vehiculo" style="display:none;">
                <label>Vehículo</label>
                <select name="id_item_vehiculo">
                    <?php foreach ($vehiculos as $v): ?>
                        <option value="<?= h($v['IdVehiculo'], '') ?>" data-precio="<?= h((string)$v['PrecioVenta'], '') ?>"><?= h($v['Nombre'], '') ?> · <?= h($v['IdVehiculo'], '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="id_item" id="id_item" value="">
            <div class="form-group">
                <label>Cantidad</label>
                <input type="number" name="cantidad" min="1" value="<?= h($form['cantidad'], '1') ?>">
            </div>
            <div class="form-group">
                <label>Precio venta</label>
                <input type="number" step="0.01" min="0" name="precio_venta" id="precio_venta" value="<?= h($form['precio_venta'], '') ?>">
            </div>
            <div class="form-group">
                <label>Precio mínimo</label>
                <input type="number" step="0.01" min="0" name="precio_minimo" value="<?= h($form['precio_minimo'], '') ?>">
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Asignar stock</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </section>
</main>
<script nonce="<?= cspNonce() ?>">
(function () {
    const tipo = document.getElementById('tipo_item');
    const repuestoWrap = document.getElementById('item_repuesto');
    const vehiculoWrap = document.getElementById('item_vehiculo');
    const hidden = document.getElementById('id_item');
    const precio = document.getElementById('precio_venta');
    const repuestoSelect = repuestoWrap.querySelector('select');
    const vehiculoSelect = vehiculoWrap.querySelector('select');

    function sync() {
        const esVehiculo = tipo.value === 'Vehiculo';
        repuestoWrap.style.display = esVehiculo ? 'none' : '';
        vehiculoWrap.style.display = esVehiculo ? '' : 'none';
        const select = esVehiculo ? vehiculoSelect : repuestoSelect;
        hidden.value = select.value || '';
        const opt = select.options[select.selectedIndex];
        if (opt && !precio.value) precio.value = opt.dataset.precio || '';
    }

    tipo.addEventListener('change', function () { precio.value = ''; sync(); });
    repuestoSelect.addEventListener('change', function () { precio.value = ''; sync(); });
    vehiculoSelect.addEventListener('change', function () { precio.value = ''; sync(); });
    sync();
})();
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
