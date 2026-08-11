<?php
$pageTitle = "Nuevo repuesto | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Repuesto\RepuestoCrudService(
    new \Lteco\Infrastructure\Repository\RepuestoCrudRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$mensaje = '';
$errores = [];
$form = [
    'nombre' => '',
    'descripcion' => '',
    'costo' => '',
    'gasto_total' => '',
    'precio_venta' => '',
    'precio_distribuidor' => '',
    'moneda' => LTECO_DEFAULT_CURRENCY,
    'stock' => '0',
    'estado' => 'Disponible',
];
$empresaRut = obtenerEmpresaRutPrincipal($pdo);
$monedas = monedasSistema();
$importaciones = $service->importacionesActivas();
$estados = estadosRepuestoSistema();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
    $estado = normalizarEstadoRepuesto(opcionSistema($form['estado'], $estados, 'Disponible'), $stock);

    $form['nombre'] = $nombre;
    $form['moneda'] = $moneda;
    $form['stock'] = (string)$stock;
    $form['estado'] = $estado;

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
        'empresa_rut' => $empresaRut,
    ];

    $errores = array_merge($errores, $service->validar($datos));

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $idProducto = $service->crear($datos);

            $pdo->commit();
            registrarAuditoria($pdo, 'CREAR_REPUESTO', 'Repuestos', 'Repuesto creado: ' . $nombre, [
                'id_producto' => $idProducto,
                'stock' => $stock,
                'precio_venta' => $precioVenta,
                'moneda' => $moneda,
            ]);
            $mensaje = 'Repuesto creado correctamente.';
            $form = [
                'nombre' => '', 'descripcion' => '', 'costo' => '', 'gasto_total' => '', 'precio_venta' => '',
                'precio_distribuidor' => '', 'moneda' => LTECO_DEFAULT_CURRENCY, 'stock' => '0', 'estado' => 'Disponible', 'numero_importacion' => '',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logPanelError('crear_repuesto', $e, ['post_keys' => array_keys($_POST ?? [])]);
            $errores[] = 'No se pudo guardar el repuesto.';
        }
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <h1>Nuevo repuesto</h1>
        <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/index.php') ?>">Volver</a>
    </div>

    <div class="section-box">
        <?php if ($mensaje): ?><div class="notice notice--success"><?= h($mensaje, '') ?></div><?php endif; ?>
        <?php if ($errores): ?>
            <div class="notice notice--error"><strong>No se pudo guardar.</strong><ul class="notice-list"><?php foreach ($errores as $error): ?><li><?= h($error, '') ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group"><label>Nombre</label><input type="text" name="nombre" value="<?= h($form['nombre'], '') ?>" maxlength="150" required></div>
                <div class="form-group"><label>Stock</label><input type="number" name="stock" min="0" value="<?= h($form['stock'], '0') ?>" required></div>
                <div class="form-group"><label>Costo</label><input type="number" step="0.01" min="0" name="costo" value="<?= h($form['costo'], '') ?>"></div>
                <div class="form-group"><label>Gasto total</label><input type="number" step="0.01" min="0" name="gasto_total" value="<?= h($form['gasto_total'], '') ?>"></div>
                <div class="form-group"><label>Precio venta</label><input type="number" step="0.01" min="0.01" name="precio_venta" value="<?= h($form['precio_venta'], '') ?>" required></div>
                <div class="form-group"><label>Precio distribuidor</label><input type="number" step="0.01" min="0" name="precio_distribuidor" value="<?= h($form['precio_distribuidor'], '') ?>"></div>
                <div class="form-group">
                    <label>Moneda</label>
                    <select name="moneda" required>
                        <?php foreach ($monedas as $moneda): ?>
                            <option value="<?= h($moneda, '') ?>" <?= $form['moneda'] === $moneda ? 'selected' : '' ?>><?= h($moneda, '') ?></option>
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
                    <small class="field-help">Si el stock queda en 0, el sistema ajusta el estado automáticamente.</small>
                </div>
                <div class="form-group full"><label>Descripción</label><textarea name="descripcion" maxlength="1000"><?= h($form['descripcion'], '') ?></textarea></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Guardar repuesto</button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
