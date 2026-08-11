<?php
$pageTitle = "Editar gasto | Ltecobike";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . "/../includes/helpers.php";

requiereLogin();
requiereAdmin();

$idGasto = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($idGasto <= 0) {
    redirectWithFlash(panelBaseUrl('gastos/index.php'), 'error', 'ID de gasto no válido.');
}

$service = new \Lteco\Application\Gasto\GastoCrudService(
    new \Lteco\Infrastructure\Repository\GastoCrudRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$gasto = $service->obtener($idGasto);

if (!$gasto) {
    redirectWithFlash(panelBaseUrl('gastos/index.php'), 'error', 'Gasto no encontrado.');
}

$form = [
    'fecha_gasto' => (string)($gasto['FechaGasto'] ?? date('Y-m-d')),
    'concepto' => (string)($gasto['Concepto'] ?? ''),
    'categoria' => (string)($gasto['Categoria'] ?? 'Otros'),
    'metodo_pago' => (string)($gasto['MetodoPago'] ?? 'Efectivo'),
    'moneda' => (string)($gasto['Moneda'] ?? 'UYU'),
    'monto' => (string)($gasto['Monto'] ?? ''),
    'observaciones' => (string)(($gasto['Observaciones'] ?? '') !== '' ? $gasto['Observaciones'] : ($gasto['Descripcion'] ?? '')),
];

$categorias = categoriasGastoSistema();
$metodosPago = metodosPagoGastoSistema();
$monedas = monedasSistema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfOrFail();

        foreach (array_keys($form) as $campo) {
            $form[$campo] = trim((string)($_POST[$campo] ?? ''));
        }

        $fechaGasto = $form['fecha_gasto'] !== '' ? $form['fecha_gasto'] : date('Y-m-d');
        $concepto = $form['concepto'];
        $categoria = in_array($form['categoria'], $categorias, true) ? $form['categoria'] : 'Otros';
        $metodoPago = in_array($form['metodo_pago'], $metodosPago, true) ? $form['metodo_pago'] : 'Efectivo';
        $moneda = in_array($form['moneda'], $monedas, true) ? $form['moneda'] : 'UYU';
        $monto = decimalNoNegativo($form['monto']);
        $observaciones = limpiarTextoOpcional($form['observaciones']);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaGasto)) {
            throw new RuntimeException('La fecha del gasto no es válida.');
        }

        if ($concepto === '') {
            throw new RuntimeException('El concepto del gasto es obligatorio.');
        }

        if (mb_strlen($concepto, 'UTF-8') > 150) {
            throw new RuntimeException('El concepto no puede superar los 150 caracteres.');
        }

        if ($monto <= 0) {
            throw new RuntimeException('El monto del gasto debe ser mayor a 0.');
        }

        $service->editar($idGasto, [
            'fecha_gasto' => $fechaGasto,
            'concepto' => $concepto,
            'categoria' => $categoria,
            'metodo_pago' => $metodoPago,
            'moneda' => $moneda,
            'monto' => $monto,
            'observaciones' => $observaciones,
        ]);

        registrarAuditoria($pdo, 'EDITAR_GASTO', 'Gastos', 'Gasto actualizado: ' . $concepto, [
            'id_gasto' => $idGasto,
            'concepto' => $concepto,
            'categoria' => $categoria,
            'metodo_pago' => $metodoPago,
            'moneda' => $moneda,
            'monto' => $monto,
        ]);

        redirectWithFlash(panelBaseUrl('gastos/index.php'), 'success', 'Gasto actualizado correctamente.');
    } catch (Throwable $e) {
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo actualizar el gasto.'));
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <h1>Editar gasto</h1>
        <a class="btn-secondary" href="<?= panelBaseUrl('gastos/index.php') ?>">Volver</a>
    </div>

    <div class="section-box">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= h((string)$idGasto, '') ?>">

            <div class="form-grid-3">
                <div>
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha_gasto" value="<?= h($form['fecha_gasto'], '') ?>" class="input u-w-100" required>
                </div>

                <div>
                    <label class="form-label">Categoría</label>
                    <select name="categoria" class="input u-w-100" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= h($cat, '') ?>" <?= $form['categoria'] === $cat ? 'selected' : '' ?>>
                                <?= h($cat, '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Método de pago</label>
                    <select name="metodo_pago" class="input u-w-100" required>
                        <?php foreach ($metodosPago as $metodo): ?>
                            <option value="<?= h($metodo, '') ?>" <?= $form['metodo_pago'] === $metodo ? 'selected' : '' ?>>
                                <?= h($metodo, '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Moneda</label>
                    <select name="moneda" class="input u-w-100" required>
                        <?php foreach ($monedas as $moneda): ?>
                            <option value="<?= h($moneda, '') ?>" <?= $form['moneda'] === $moneda ? 'selected' : '' ?>>
                                <?= h($moneda, '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Monto</label>
                    <input type="number" step="0.01" min="0.01" name="monto" value="<?= h($form['monto'], '') ?>" class="input u-w-100" required>
                </div>

                <div class="u-full-grid">
                    <label class="form-label">Concepto</label>
                    <input type="text" name="concepto" value="<?= h($form['concepto'], '') ?>" class="input u-w-100" maxlength="150" required>
                </div>

                <div class="u-full-grid">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" rows="4" class="input u-w-100"><?= h($form['observaciones'], '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Guardar cambios</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('gastos/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
