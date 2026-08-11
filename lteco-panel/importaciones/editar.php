<?php
$pageTitle = "Editar importación | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$id = (int)($_GET['id'] ?? 0);

$service = new \Lteco\Application\Importacion\ImportacionCrudService(
    new \Lteco\Infrastructure\Repository\ImportacionCrudRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$imp = $service->obtener($id);
if (!$imp) { redirect(panelBaseUrl('importaciones/index.php')); }

$errores = [];
$form = ['numero' => $imp['Numero'], 'tipo_cambio_usd' => $imp['TipoCambioUSD'], 'fecha' => $imp['Fecha'] ?? '', 'descripcion' => $imp['Descripcion'] ?? '', 'activa' => $imp['Activa']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrFail();

    $form['tipo_cambio_usd'] = decimalNoNegativo($_POST['tipo_cambio_usd'] ?? 0);
    $form['fecha'] = trim((string)($_POST['fecha'] ?? ''));
    $form['descripcion'] = normalizarTextoHumano($_POST['descripcion'] ?? '', 200);
    $form['activa'] = (int)($_POST['activa'] ?? 1);

    if ($form['tipo_cambio_usd'] <= 0) $errores[] = 'El tipo de cambio debe ser mayor a 0.';
    if ($form['fecha'] !== '' && !fechaYmdValida($form['fecha'])) $errores[] = 'La fecha ingresada no es válida.';

    if (!$errores) {
        $service->editar($id, [
            'tipo_cambio_usd' => $form['tipo_cambio_usd'],
            'fecha' => $form['fecha'] ?: null,
            'descripcion' => $form['descripcion'] ?: null,
            'activa' => $form['activa'] === 1 ? 1 : 0,
        ]);
        redirect(panelBaseUrl('importaciones/index.php'));
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Editar importaci&oacute;n #<?= (int)$imp['Numero'] ?></h1>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('importaciones/index.php') ?>">Volver</a>
    </div>

    <?php if ($errores): ?>
        <div class="notice notice--error"><?= implode('<br>', array_map('h', $errores)) ?></div>
    <?php endif; ?>

    <div class="section-box">
        <form method="POST">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>N&uacute;mero de importaci&oacute;n</label>
                    <input type="text" value="#<?= (int)$imp['Numero'] ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Tipo de cambio USD</label>
                    <input type="number" step="0.01" name="tipo_cambio_usd" value="<?= h($form['tipo_cambio_usd']) ?>" min="0.01" required>
                </div>
                <div class="form-group">
                    <label>Fecha <small class="field-help">(opcional)</small></label>
                    <input type="date" name="fecha" value="<?= h($form['fecha']) ?>">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="activa">
                        <option value="1" <?= $form['activa'] == 1 ? 'selected' : '' ?>>Activa</option>
                        <option value="0" <?= $form['activa'] == 0 ? 'selected' : '' ?>>Inactiva</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Descripci&oacute;n <small class="field-help">(opcional)</small></label>
                    <input type="text" name="descripcion" value="<?= h($form['descripcion']) ?>" maxlength="200">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Guardar cambios</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('importaciones/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
