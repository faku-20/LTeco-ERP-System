<?php
$pageTitle = "Nueva caja de repuestos | ERP";
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . '/../../includes/flash.php';

requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../../includes/helpers.php";

$repo = new \Lteco\Infrastructure\Repository\RepuestoCajaRepository(
    new \Lteco\Infrastructure\Db\Connection($pdo)
);
$service = new \Lteco\Application\Repuesto\RepuestoCajaService(
    $repo,
    new \Lteco\Application\Repuesto\RepuestoCrudService(
        new \Lteco\Infrastructure\Repository\RepuestoCrudRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    )
);

$errores = [];
$repuestos = $service->repuestosParaSelect();
$importaciones = $service->importacionesActivas();
$empresaRut = obtenerEmpresaRutPrincipal($pdo);
$usuarioActual = usuarioActual();
$form = [
    'modo' => 'ingreso',
    'nombre' => '',
    'ubicacion' => '',
    'observaciones' => '',
];
$lineasForm = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['modo'] = (string)($_POST['modo'] ?? 'ingreso');
    $form['nombre'] = (string)($_POST['nombre'] ?? '');
    $form['ubicacion'] = (string)($_POST['ubicacion'] ?? '');
    $form['observaciones'] = (string)($_POST['observaciones'] ?? '');
    try {
        verifyCsrfOrFail();
        $lineas = [];
        foreach (($_POST['lineas'] ?? []) as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $lineas[] = $linea;
        }
        $lineasForm = $lineas;
        $resultado = $service->crear([
            'modo' => $form['modo'],
            'nombre' => $form['nombre'],
            'ubicacion' => $form['ubicacion'],
            'observaciones' => $form['observaciones'],
            'empresa_rut' => $empresaRut,
        ], $lineas, (int)($usuarioActual['IdUsuario'] ?? 0));

        registrarAuditoria($pdo, 'CREAR_CAJA_REPUESTOS', 'Repuestos', 'Caja creada: ' . $resultado['codigo'], [
            'id_caja' => $resultado['id_caja'],
            'codigo' => $resultado['codigo'],
            'modo' => (string)($_POST['modo'] ?? 'ingreso'),
        ]);

        redirectWithFlash(
            panelBaseUrl('repuestos/cajas/ver.php?c=' . urlencode($resultado['codigo'])),
            'success',
            'Caja ' . $resultado['codigo'] . ' creada correctamente.'
        );
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logPanelError('crear_caja_repuestos', $e, ['post_keys' => array_keys($_POST ?? [])]);
        $errores[] = mensajeErrorSeguro($e, 'No se pudo crear la caja.');
    }
}

if ($lineasForm === []) {
    $lineasForm = [[
        'tipo' => 'existente',
        'cantidad' => '1',
        'id_repuesto' => '',
        'nombre' => '',
        'descripcion' => '',
        'costo' => '',
        'gasto_total' => '',
        'precio_venta' => '',
        'precio_distribuidor' => '',
        'moneda' => 'UYU',
        'numero_importacion' => '',
    ]];
}

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Nueva caja</h1>
            <p class="subtle">Ingresá repuestos nuevos o ubicá unidades ya existentes sin duplicar stock.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/cajas/index.php') ?>">Volver</a>
    </div>

    <div class="section-box">
        <?php if ($errores): ?>
            <div class="notice notice--error"><strong>No se pudo guardar.</strong><ul class="notice-list"><?php foreach ($errores as $error): ?><li><?= h($error, '') ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST" id="cajaForm">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Tipo de movimiento</label>
                    <select name="modo" id="modoCaja" required>
                        <option value="ingreso" <?= $form['modo'] === 'ingreso' ? 'selected' : '' ?>>Nuevo ingreso: aumenta stock general</option>
                        <option value="ubicar" <?= $form['modo'] === 'ubicar' ? 'selected' : '' ?>>Ubicar stock ya existente: no aumenta stock</option>
                    </select>
                </div>
                <div class="form-group"><label>Nombre de caja</label><input type="text" name="nombre" maxlength="160" value="<?= h($form['nombre'], '') ?>" placeholder="Caja 1, Piso, Mostrador"></div>
                <div class="form-group"><label>Ubicación</label><input type="text" name="ubicacion" maxlength="160" value="<?= h($form['ubicacion'], '') ?>" placeholder="Depósito, estante o referencia física"></div>
                <div class="form-group full"><label>Observaciones</label><textarea name="observaciones" maxlength="1000"><?= h($form['observaciones'], '') ?></textarea></div>
            </div>

            <div class="list-head-v4 u-mt-1">
                <div>
                    <h2>Contenido</h2>
                    <p>Usá una línea por repuesto. Las cantidades deben ser mayores a cero.</p>
                </div>
                <button type="button" class="btn-secondary btn-small" id="agregarLinea">+ Línea</button>
            </div>

            <div id="lineasCaja" class="caja-lines"></div>

            <div class="form-actions">
                <button type="submit" class="btn">Guardar caja</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('repuestos/cajas/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<template id="lineaCajaTemplate">
    <section class="v4-card caja-line" data-linea>
        <div class="form-grid">
            <div class="form-group">
                <label>Origen</label>
                <select data-field="tipo">
                    <option value="existente">Repuesto existente</option>
                    <option value="nuevo">Crear repuesto nuevo</option>
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad en caja</label>
                <input data-field="cantidad" type="number" min="1" value="1" required>
            </div>
            <div class="form-group full js-existing">
                <label>Repuesto existente</label>
                <select data-field="id_repuesto">
                    <option value="">Seleccionar</option>
                    <?php foreach ($repuestos as $repuesto): ?>
                        <option value="<?= (int)$repuesto['IdRepuesto'] ?>">
                            <?= h($repuesto['Nombre'], '') ?> · stock <?= (int)$repuesto['Stock'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group js-new" hidden><label>Nombre</label><input data-field="nombre" type="text" maxlength="150"></div>
            <div class="form-group js-new" hidden><label>Costo</label><input data-field="costo" type="number" step="0.01" min="0"></div>
            <div class="form-group js-new" hidden><label>Gasto total</label><input data-field="gasto_total" type="number" step="0.01" min="0"></div>
            <div class="form-group js-new" hidden><label>Precio venta</label><input data-field="precio_venta" type="number" step="0.01" min="0.01"></div>
            <div class="form-group js-new" hidden><label>Precio distribuidor</label><input data-field="precio_distribuidor" type="number" step="0.01" min="0"></div>
            <div class="form-group js-new" hidden>
                <label>Moneda</label>
                <select data-field="moneda"><option value="UYU">UYU</option><option value="USD">USD</option></select>
            </div>
            <div class="form-group js-new" hidden>
                <label>Importación</label>
                <select data-field="numero_importacion">
                    <option value="">Sin importación</option>
                    <?php foreach ($importaciones as $imp): ?>
                        <option value="<?= (int)$imp['Numero'] ?>">Importación <?= (int)$imp['Numero'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full js-new" hidden><label>Descripción</label><textarea data-field="descripcion" maxlength="1000"></textarea></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-danger btn-small js-remove-line">Quitar línea</button>
        </div>
    </section>
</template>

<script nonce="<?= cspNonce() ?>">
(function () {
    const container = document.getElementById('lineasCaja');
    const template = document.getElementById('lineaCajaTemplate');
    const addBtn = document.getElementById('agregarLinea');
    const modo = document.getElementById('modoCaja');
    const initialLines = <?= json_encode($lineasForm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let index = 0;

    function syncNames(line) {
        const idx = line.dataset.index;
        line.querySelectorAll('[data-field]').forEach(function (input) {
            input.name = 'lineas[' + idx + '][' + input.dataset.field + ']';
        });
    }

    function syncLine(line) {
        const tipo = line.querySelector('[data-field="tipo"]').value;
        const nuevo = tipo === 'nuevo';
        line.querySelectorAll('.js-new').forEach(function (el) { el.hidden = !nuevo; });
        line.querySelectorAll('.js-existing').forEach(function (el) { el.hidden = nuevo; });
        line.querySelector('[data-field="id_repuesto"]').required = !nuevo;
        line.querySelector('[data-field="nombre"]').required = nuevo;
        line.querySelector('[data-field="precio_venta"]').required = nuevo;
    }

    function syncModo() {
        const ubicando = modo.value === 'ubicar';
        container.querySelectorAll('[data-field="tipo"]').forEach(function (select) {
            const newOption = select.querySelector('option[value="nuevo"]');
            if (newOption) newOption.disabled = ubicando;
            if (ubicando && select.value === 'nuevo') {
                select.value = 'existente';
                syncLine(select.closest('[data-linea]'));
            }
        });
    }

    function applyData(line, data) {
        Object.keys(data || {}).forEach(function (key) {
            const input = line.querySelector('[data-field="' + CSS.escape(key) + '"]');
            if (!input) return;
            input.value = data[key] == null ? '' : String(data[key]);
        });
    }

    function addLine(data) {
        const fragment = template.content.cloneNode(true);
        const line = fragment.querySelector('[data-linea]');
        line.dataset.index = String(index++);
        syncNames(line);
        applyData(line, data || {});
        syncLine(line);
        container.appendChild(fragment);
        syncModo();
    }

    addBtn.addEventListener('click', addLine);
    modo.addEventListener('change', syncModo);
    container.addEventListener('change', function (event) {
        if (event.target.matches('[data-field="tipo"]')) syncLine(event.target.closest('[data-linea]'));
    });
    container.addEventListener('click', function (event) {
        const btn = event.target.closest('.js-remove-line');
        if (!btn) return;
        btn.closest('[data-linea]').remove();
    });

    if (initialLines.length > 0) {
        initialLines.forEach(function (line) { addLine(line); });
    } else {
        addLine();
    }
})();
</script>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
