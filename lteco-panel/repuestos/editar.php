<?php
$pageTitle = "Editar repuesto | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';

requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$connection = new \Lteco\Infrastructure\Db\Connection($pdo);
$service = new \Lteco\Application\Repuesto\RepuestoCrudService(
    new \Lteco\Infrastructure\Repository\RepuestoCrudRepository(
        $connection
    )
);
$cajaService = new \Lteco\Application\Repuesto\RepuestoCajaService(
    new \Lteco\Infrastructure\Repository\RepuestoCajaRepository(
        $connection
    ),
    $service
);

if (!isset($_GET['id']) || trim((string)$_GET['id']) === '') {
    redirectWithFlash(panelBaseUrl('repuestos/index.php'), 'error', 'ID no recibido.');
}

$idProducto = (int)$_GET['id'];
$mensaje = '';
$errores = [];
$monedas = monedasSistema();
$importaciones = $service->importacionesActivas();
$estados = estadosRepuestoSistema();

$repuesto = $service->obtener($idProducto);

if (!$repuesto) {
    redirectWithFlash(panelBaseUrl('repuestos/index.php'), 'error', 'Repuesto no encontrado.');
}

$form = [
    'nombre' => (string)($repuesto['Nombre'] ?? ''),
    'descripcion' => (string)($repuesto['Descripcion'] ?? ''),
    'costo' => (string)($repuesto['Costo'] ?? ''),
    'gasto_total' => (string)($repuesto['GastoTotal'] ?? ''),
    'precio_venta' => (string)($repuesto['PrecioVenta'] ?? ''),
    'precio_distribuidor' => (string)($repuesto['PrecioDistribuidor'] ?? ''),
    'moneda' => (string)($repuesto['Moneda'] ?? LTECO_DEFAULT_CURRENCY),
    'numero_importacion' => (string)($repuesto['NumeroImportacion'] ?? ''),
    'stock' => (string)($repuesto['Stock'] ?? '0'),
    'estado' => (string)($repuesto['Estado'] ?? 'Disponible'),
    'motivo_stock' => '',
];

$cajasActivas = $cajaService->cajasActivas();
$cajaForm = [
    'id_caja' => '',
    'cantidad' => '1',
];
$mensajeCaja = '';
$erroresCaja = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST['accion'] ?? '') === 'asociar_caja') {
    try {
        verifyCsrfOrFail();
        $cajaForm['id_caja'] = trim((string)($_POST['id_caja'] ?? ''));
        $cajaForm['cantidad'] = trim((string)($_POST['cantidad_caja'] ?? '1'));
        $idCajaAsociar = (int)$cajaForm['id_caja'];
        $cantidadCaja = enteroNoNegativo($cajaForm['cantidad']);
        $idRepuestoAsociar = (int)($repuesto['IdRepuesto'] ?? 0);
        $cajaService->ubicarRepuestoExistente($idCajaAsociar, $idRepuestoAsociar, $cantidadCaja, (int)(usuarioActual()['IdUsuario'] ?? 0));

        $cajaData = $cajaService->obtener($idCajaAsociar, 'IdCaja');
        registrarAuditoria($pdo, 'UBICAR_REPUESTO_EN_CAJA', 'Repuestos', 'Repuesto ubicado en caja desde edición: ' . (string)($repuesto['Nombre'] ?? ''), [
            'id_producto' => $idProducto,
            'id_repuesto' => $idRepuestoAsociar,
            'id_caja' => $idCajaAsociar,
            'codigo' => (string)($cajaData['caja']['Codigo'] ?? ''),
            'cantidad' => $cantidadCaja,
        ]);

        $mensajeCaja = 'Repuesto ubicado en caja correctamente.';
        $cajaForm = ['id_caja' => '', 'cantidad' => '1'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logPanelError('asociar_repuesto_caja', $e, ['id_producto' => $idProducto]);
        $erroresCaja[] = mensajeErrorSeguro($e, 'No se pudo ubicar el repuesto en la caja.');
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST['accion'] ?? '') !== 'asociar_caja') {
    try {
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        $errores[] = "La sesión del formulario venció. Volvé a intentar.";
    }
    foreach (array_keys($form) as $campo) {
        $form[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    $nombre = normalizarTextoHumano($form['nombre'], 150);
    $descripcion = limpiarTextoOpcional($form['descripcion']);
    $costo = decimalNoNegativo($form['costo']);
    $numeroImportacion = !empty($form['numero_importacion']) ? (int)$form['numero_importacion'] : null;
    $gastoTotal = decimalNoNegativo($form['gasto_total']);
    $precioVenta = decimalNoNegativo($form['precio_venta']);
    $precioDistribuidor = $form['precio_distribuidor'] !== '' ? decimalNoNegativo($form['precio_distribuidor']) : null;
    $moneda = opcionSistema($form['moneda'], $monedas, LTECO_DEFAULT_CURRENCY);
    $stock = enteroNoNegativo($form['stock']);
    $stockAnterior = (int)($repuesto['Stock'] ?? 0);
    $precioVentaAnterior = (float)($repuesto['PrecioVenta'] ?? 0);
    $motivoStock = normalizarTextoHumano((string)($_POST['motivo_stock'] ?? ''), 500);
    $estado = normalizarEstadoRepuesto(opcionSistema($form['estado'], $estados, 'Disponible'), $stock);

    $form['nombre'] = $nombre;
    $form['moneda'] = $moneda;
    $form['stock'] = (string)$stock;
    $form['estado'] = $estado;
    $form['motivo_stock'] = $motivoStock;

    $datos = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'costo' => $costo,
        'gasto_total' => $gastoTotal,
        'precio_venta' => $precioVenta,
        'precio_distribuidor' => $precioDistribuidor,
        'moneda' => $moneda,
        'stock' => $stock,
        'estado' => $estado,
        'numero_importacion' => $numeroImportacion,
        'permitir_precio_venta_cero' => $precioVentaAnterior <= 0.0 && $precioVenta <= 0.0,
    ];

    $errores = array_merge($errores, $service->validar($datos));
    $ubicadoActual = array_sum(array_map(
        static fn(array $caja): int => (int)($caja['Cantidad'] ?? 0),
        $cajaService->cajasPorRepuesto((int)($repuesto['IdRepuesto'] ?? 0))
    ));
    if ($stock < $ubicadoActual) {
        $errores[] = 'El stock total no puede quedar por debajo de la cantidad ubicada en cajas.';
    }
    if ($stock !== $stockAnterior && $motivoStock === '') {
        $errores[] = 'Indicá el motivo del ajuste de stock.';
    }

    if (empty($errores)) {
        try {
            $service->editar($idProducto, $datos);

            registrarAuditoria($pdo, 'EDITAR_REPUESTO', 'Repuestos', 'Repuesto actualizado: ' . $nombre, [
                'id_producto' => $idProducto,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stock,
                'delta_stock' => $stock - $stockAnterior,
                'motivo_stock' => $stock !== $stockAnterior ? $motivoStock : null,
                'precio_venta' => $precioVenta,
                'moneda' => $moneda,
                'estado' => $estado,
            ]);

            $mensaje = 'Repuesto actualizado correctamente.';
            $repuesto = $service->obtener($idProducto) ?: $repuesto;
        } catch (Throwable $e) {
            logPanelError('editar_repuesto', $e, ['id_producto' => $idProducto]);
            $errores[] = 'No se pudo actualizar el repuesto.';
        }
    }
}

$cajasRepuesto = $cajaService->cajasPorRepuesto((int)($repuesto['IdRepuesto'] ?? 0));
$ubicadoEnCajas = array_sum(array_map(static fn(array $caja): int => (int)($caja['Cantidad'] ?? 0), $cajasRepuesto));
$stockSinUbicar = max(0, (int)($repuesto['Stock'] ?? 0) - $ubicadoEnCajas);

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
$precioVentaMin = ((float)($repuesto['PrecioVenta'] ?? 0) <= 0.0) ? '0' : '0.01';
?>

<main class="main">
    <div class="topbar">
        <h1>Editar repuesto</h1>
        <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/index.php') ?>">Volver</a>
    </div>

    <div class="section-box">
        <?php if ($mensaje): ?><div class="notice notice--success"><?= h($mensaje, '') ?></div><?php endif; ?>
        <?php if ($errores): ?>
            <div class="notice notice--error"><strong>No se pudo actualizar.</strong><ul class="notice-list"><?php foreach ($errores as $error): ?><li><?= h($error, '') ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group"><label>Nombre</label><input type="text" name="nombre" value="<?= h($form['nombre'], '') ?>" maxlength="150" required></div>
                <div class="form-group"><label>Stock</label><input type="number" name="stock" min="0" value="<?= h($form['stock'], '0') ?>" required></div>
                <div class="form-group"><label>Costo</label><input type="number" step="0.01" min="0" name="costo" value="<?= h($form['costo'], '') ?>"></div>
                <div class="form-group"><label>Gasto total</label><input type="number" step="0.01" min="0" name="gasto_total" value="<?= h($form['gasto_total'], '') ?>"></div>
                <div class="form-group"><label>Precio venta</label><input type="number" step="0.01" min="<?= h($precioVentaMin, '0.01') ?>" name="precio_venta" value="<?= h($form['precio_venta'], '') ?>" required></div>
                <div class="form-group"><label>Precio distribuidor</label><input type="number" step="0.01" min="0" name="precio_distribuidor" value="<?= h($form['precio_distribuidor'], '') ?>"></div>
                <div class="form-group full"><label>Motivo del ajuste de stock</label><textarea name="motivo_stock" maxlength="500" placeholder="Obligatorio si cambiás la cantidad"><?= h($form['motivo_stock'], '') ?></textarea></div>
                <div class="form-group">
                    <label>Moneda</label>
                    <select name="moneda" required>
                        <?php foreach ($monedas as $monedaItem): ?>
                            <option value="<?= h($monedaItem, '') ?>" <?= $form['moneda'] === $monedaItem ? 'selected' : '' ?>><?= h($monedaItem, '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Importaci&oacute;n <small class="field-help">(opcional)</small></label>
                    <select name="numero_importacion">
                        <option value="">-- Sin importaci&oacute;n --</option>
                        <?php foreach ($importaciones as $imp): ?>
                            <option value="<?= (int)$imp['Numero'] ?>"
                                <?= ((string)($form['numero_importacion'] ?? '') === (string)$imp['Numero']) ? 'selected' : '' ?>>
                                Importaci&oacute;n <?= (int)$imp['Numero'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" required>
                        <?php foreach ($estados as $estadoItem): ?>
                            <option value="<?= h($estadoItem, '') ?>" <?= $form['estado'] === $estadoItem ? 'selected' : '' ?>><?= h($estadoItem, '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-help">Si el stock queda en 0, el sistema ajusta el estado autom&aacute;ticamente.</small>
                </div>
                <div class="form-group full"><label>Descripción</label><textarea name="descripcion" maxlength="1000"><?= h($form['descripcion'], '') ?></textarea></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Guardar cambios</button>
            </div>
        </form>
    </div>

    <section class="section-box">
        <div class="list-head-v4">
            <div>
                <h2>Cajas</h2>
                <p>Stock total: <?= (int)($repuesto['Stock'] ?? 0) ?> · Sin ubicar: <?= (int)$stockSinUbicar ?></p>
            </div>
        </div>

        <?php if ($mensajeCaja): ?><div class="notice notice--success"><?= h($mensajeCaja, '') ?></div><?php endif; ?>
        <?php if ($erroresCaja): ?>
            <div class="notice notice--error"><strong>No se pudo ubicar.</strong><ul class="notice-list"><?php foreach ($erroresCaja as $error): ?><li><?= h($error, '') ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Caja</th><th>Nombre</th><th>Ubicación</th><th class="num">Cantidad</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($cajasRepuesto as $caja): ?>
                        <tr>
                            <td><a href="<?= panelBaseUrl('repuestos/cajas/ver.php?c=' . urlencode((string)$caja['Codigo'])) ?>"><?= h($caja['Codigo'], '') ?></a></td>
                            <td><?= h($caja['Nombre'] ?: $caja['Codigo'], '') ?></td>
                            <td><?= h($caja['Ubicacion']) ?></td>
                            <td class="num"><?= (int)$caja['Cantidad'] ?></td>
                            <td><span class="badge <?= $caja['Estado'] === 'Activa' ? 'badge-disponible' : 'badge-oculto' ?>"><?= h($caja['Estado'], '') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cajasRepuesto): ?><tr><td colspan="5" style="text-align:center;color:var(--ink-3);padding:24px;">Este repuesto todavía no está ubicado en cajas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <form method="POST" class="u-mt-1">
            <?= csrfInput() ?>
            <input type="hidden" name="accion" value="asociar_caja">
            <div class="form-grid">
                <div class="form-group">
                    <label>Ubicar en caja</label>
                    <select name="id_caja" required>
                        <option value="">Seleccionar caja activa</option>
                        <?php foreach ($cajasActivas as $caja): ?>
                            <option value="<?= (int)$caja['IdCaja'] ?>" <?= (string)$cajaForm['id_caja'] === (string)$caja['IdCaja'] ? 'selected' : '' ?>>
                                <?= h(($caja['Nombre'] ?: $caja['Codigo']) . ' · ' . $caja['Codigo'] . (($caja['Ubicacion'] ?? '') !== '' ? ' · ' . $caja['Ubicacion'] : ''), '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Cantidad</label><input type="number" name="cantidad_caja" min="1" max="<?= max(1, (int)$stockSinUbicar) ?>" value="<?= h($cajaForm['cantidad'], '1') ?>" required></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn" <?= $stockSinUbicar <= 0 || !$cajasActivas ? 'disabled' : '' ?>>Ubicar stock existente</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/cajas/crear.php') ?>">Nueva caja</a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
