<?php
$pageTitle = "Nuevo gasto | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . "/../includes/helpers.php";

requiereLogin();
requiereAdmin();

$form = [
    'fecha_gasto' => date('Y-m-d'),
    'concepto' => '',
    'categoria' => 'Otros',
    'metodo_pago' => 'Efectivo',
    'moneda' => 'UYU',
    'monto' => '',
    'observaciones' => '',
];

$categorias = categoriasGastoSistema();
$metodosPago = metodosPagoGastoSistema();
$monedas = monedasSistema();

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <h1>Nuevo gasto</h1>
        <a class="btn-secondary" href="<?= panelBaseUrl('gastos/index.php') ?>">Volver</a>
    </div>

    <div class="section-box">
        <form action="<?= panelBaseUrl('gastos/guardar.php') ?>" method="POST">
            <?= csrfInput() ?>

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

            <div class="u-form-actions-inline-tight">
                <button type="submit" class="btn">Guardar gasto</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('gastos/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
