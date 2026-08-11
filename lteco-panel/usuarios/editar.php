<?php
$pageTitle = "Editar usuario | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereSuperadmin();
require_once __DIR__ . "/../includes/helpers.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario inválido.');
}

$usuarioService = new \Lteco\Application\Usuario\UsuarioCrudService(
    new \Lteco\Infrastructure\Repository\UsuarioRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$usuarioData = $usuarioService->obtenerParaEdicion($id);

if (!$usuarioData) {
    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario no encontrado.');
}

$usuarioData['Rol'] = rolNormalizado($usuarioData['Rol'] ?? ROL_VENDEDOR);
$roles = rolesSistema();
$distribuidores = $usuarioService->distribuidoresActivos();
$errores = [];
$form = [
    'nombre_completo' => (string)$usuarioData['NombreCompleto'],
    'usuario'         => (string)$usuarioData['Usuario'],
    'rol'             => (string)$usuarioData['Rol'],
    'id_distribuidor' => (string)($usuarioData['IdDistribuidor'] ?? ''),
    'comision_pct'    => (string)($usuarioData['ComisionPct'] ?? '0'),
    'comision_distribuidor_pct' => (string)($usuarioData['ComisionDistribuidorPct'] ?? '0'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        $errores[] = 'La sesión del formulario venció. Volvé a intentar.';
    }
    $form['nombre_completo'] = trim((string)($_POST['nombre_completo'] ?? ''));
    $form['usuario'] = trim((string)($_POST['usuario'] ?? ''));
    $form['rol'] = rolNormalizado((string)($_POST['rol'] ?? ROL_VENDEDOR));
    $form['id_distribuidor'] = trim((string)($_POST['id_distribuidor'] ?? ''));
    $form['comision_pct'] = trim((string)($_POST['comision_pct'] ?? '0'));
    $form['comision_distribuidor_pct'] = trim((string)($_POST['comision_distribuidor_pct'] ?? '0'));

    if ($form['nombre_completo'] === '') {
        $errores[] = 'El nombre completo es obligatorio.';
    }

    if ($form['usuario'] === '') {
        $errores[] = 'El usuario es obligatorio.';
    }

    if (!in_array($form['rol'], $roles, true)) {
        $errores[] = 'Rol inválido.';
    }

    $idDistribuidor = null;
    if ($form['rol'] === ROL_DISTRIBUIDOR) {
        $idDistribuidor = $form['id_distribuidor'] !== '' ? (int)$form['id_distribuidor'] : null;
        if ($idDistribuidor === null) {
            $errores[] = 'Seleccioná el distribuidor asociado a este usuario.';
        }
    }

    $idActual = (int)(usuarioActual()['IdUsuario'] ?? 0);
    if ($idActual === $id && $usuarioData['Rol'] === ROL_SUPERADMIN && $form['rol'] !== ROL_SUPERADMIN) {
        $errores[] = 'No podés quitarte tu propio rol Superadmin desde tu sesión actual.';
    }

    if (!$errores) {
        if (!$usuarioService->usuarioDisponibleExcepto($form['usuario'], $id)) {
            $errores[] = 'Ese nombre de usuario ya está en uso.';
        }
    }

    if (!$errores) {
        $comisionPct = max(0.0, min(100.0, (float)str_replace(',', '.', (string)($form['comision_pct'] ?? 0))));
        $comisionDistribuidorPct = max(0.0, min(100.0, (float)str_replace(',', '.', (string)($form['comision_distribuidor_pct'] ?? 0))));
        $usuarioService->actualizar($id, [
            'nombre_completo' => $form['nombre_completo'],
            'usuario' => $form['usuario'],
            'rol' => $form['rol'],
            'id_distribuidor' => $idDistribuidor,
            'comision_pct' => $comisionPct,
            'comision_distribuidor_pct' => $comisionDistribuidorPct,
        ]);
        registrarAuditoria($pdo, 'EDITAR_USUARIO', 'Usuarios', 'Usuario actualizado: ' . $form['usuario'], ['id_usuario' => $id, 'rol' => $form['rol']]);

        if ((int)(usuarioActual()['IdUsuario'] ?? 0) === $id) {
            $_SESSION['usuario']['NombreCompleto'] = $form['nombre_completo'];
            $_SESSION['usuario']['Usuario'] = $form['usuario'];
            $_SESSION['usuario']['Rol'] = $form['rol'];
        }

        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'success', 'Usuario actualizado correctamente.');
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Editar usuario</h1>
            <p class="subtle">Solo Superadmin puede modificar roles y tocar cuentas Administrador o Superadmin.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('usuarios/index.php') ?>">Volver</a>
    </div>

    <?php if ($errores): ?>
        <div class="notice notice--error">
            <strong>No se pudo actualizar.</strong>
            <ul class="notice-list">
                <?php foreach ($errores as $error): ?>
                    <li><?= h($error, '') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="section-box">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int)$usuarioData['IdUsuario'] ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre_completo" value="<?= h($form['nombre_completo'], '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" name="usuario" value="<?= h($form['usuario'], '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" id="rol_select" required>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= h($rol, '') ?>" <?= $form['rol'] === $rol ? 'selected' : '' ?>>
                                <?= h($rol, '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="comision_field" style="display:none;">
                    <label>Comisión venta directa % <small class="muted">(0 = sin comisión)</small></label>
                    <input type="number" step="0.01" min="0" max="100" name="comision_pct" value="<?= h($form['comision_pct'], '0') ?>">
                    <small class="field-help">Se usa cuando este usuario registra una venta directa.</small>
                </div>
                <div class="form-group" id="comision_distribuidor_field" style="display:none;">
                    <label>Comisión venta distribuidor % <small class="muted">(0 = sin comisión)</small></label>
                    <input type="number" step="0.01" min="0" max="100" name="comision_distribuidor_pct" value="<?= h($form['comision_distribuidor_pct'], '0') ?>">
                    <small class="field-help">Se usa para la comisión interna cuando vende un distribuidor.</small>
                </div>
                <div class="form-group" id="distribuidor_field" style="display:none;">
                    <label>Distribuidor asociado</label>
                    <select name="id_distribuidor" id="id_distribuidor">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($distribuidores as $dist): ?>
                            <option value="<?= (int)$dist['IdDistribuidor'] ?>" <?= $form['id_distribuidor'] === (string)$dist['IdDistribuidor'] ? 'selected' : '' ?>>
                                <?= h($dist['Nombre'], '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <script nonce="<?= cspNonce() ?>">
            (function() {
                var rolSelect     = document.getElementById('rol_select');
                var distField     = document.getElementById('distribuidor_field');
                var comisionField = document.getElementById('comision_field');
                var comisionDistribuidorField = document.getElementById('comision_distribuidor_field');
                function toggle() {
                    distField.style.display = rolSelect.value === 'Distribuidor' ? '' : 'none';
                    var muestraComisiones = ['Vendedor','Administrador'].includes(rolSelect.value);
                    if (comisionField) comisionField.style.display = muestraComisiones ? '' : 'none';
                    if (comisionDistribuidorField) comisionDistribuidorField.style.display = muestraComisiones ? '' : 'none';
                }
                rolSelect.addEventListener('change', toggle);
                toggle();
            })();
            </script>

            <div class="form-actions">
                <button type="submit" class="btn">Guardar cambios</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('usuarios/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
