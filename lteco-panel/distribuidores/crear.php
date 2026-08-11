<?php
$pageTitle = "Nuevo distribuidor | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Distribuidor\DistribuidorCrudService(
    new \Lteco\Infrastructure\Repository\DistribuidorCrudRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$errores = [];
$form = $service->formularioNuevo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrFail();

    $preparado = $service->prepararFormulario($_POST, false);
    $form = $preparado['form'];
    $errores = $preparado['errores'];

    if (!$errores) {
        $service->crear($form, $preparado['comision']);
        registrarAuditoria($pdo, 'CREAR_DISTRIBUIDOR', 'Distribuidores', 'Distribuidor creado: ' . $form['nombre'], []);
        redirectWithFlash(panelBaseUrl('distribuidores/index.php'), 'success', 'Distribuidor creado correctamente.');
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div><h1>Nuevo distribuidor</h1></div>
        <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
    </div>

    <?php if ($errores): ?>
        <div class="notice notice--error">
            <?php foreach ($errores as $e): ?><p><?= h($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-box">
        <form method="POST">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre <span class="required">*</span></label>
                    <input type="text" name="nombre" value="<?= h($form['nombre'], '') ?>" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Comision % <span class="required">*</span></label>
                    <input type="number" step="0.01" min="0" max="100" name="comision_pct" value="<?= h($form['comision_pct'], '') ?>" required>
                    <small class="field-help">Ej: 6.67 para el 6.67% sobre total con IVA.</small>
                </div>
                <div class="form-group">
                    <label>Contacto</label>
                    <input type="text" name="contacto" value="<?= h($form['contacto'], '') ?>" maxlength="150">
                </div>
                <div class="form-group">
                    <label>Telefono</label>
                    <input type="text" name="telefono" value="<?= h($form['telefono'], '') ?>" maxlength="30">
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" value="<?= h($form['correo'], '') ?>" maxlength="150">
                </div>
                <div class="form-group full">
                    <label>Observaciones</label>
                    <textarea name="observaciones" rows="3"><?= h($form['observaciones'], '') ?></textarea>
                </div>
            </div>
            <div class="u-form-actions-inline">
                <button type="submit" class="btn">Guardar</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
