<?php
$pageTitle = "Nuevo usuario | Ltecobike";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereGestionUsuarios();
require_once __DIR__ . "/../includes/helpers.php";

$rolesPermitidos = rolesQuePuedeCrearUsuarioActual();
if (!$rolesPermitidos) {
    denegarAcceso('No tenés permisos para crear usuarios.');
}

$usuarioService = new \Lteco\Application\Usuario\UsuarioCrudService(
    new \Lteco\Infrastructure\Repository\UsuarioRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$distribuidores = $usuarioService->distribuidoresActivos();

$errores = [];
$form = [
    'nombre_completo'  => '',
    'usuario'          => '',
    'rol'              => $rolesPermitidos[0] ?? ROL_VENDEDOR,
    'id_distribuidor'  => '',
    'comision_pct'     => '0',
    'comision_distribuidor_pct' => '0',
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
    $clave = (string)($_POST['clave'] ?? '');
    $clave2 = (string)($_POST['clave2'] ?? '');

    if ($form['nombre_completo'] === '') {
        $errores[] = 'El nombre completo es obligatorio.';
    }

    if ($form['usuario'] === '') {
        $errores[] = 'El usuario es obligatorio.';
    }

    if ($clave === '' || $clave2 === '') {
        $errores[] = 'Completá y repetí la contraseña.';
    } elseif ($clave !== $clave2) {
        $errores[] = 'Las contraseñas no coinciden.';
    } else {
        $errores = array_merge($errores, validarClaveSegunRol($clave, $form['rol']));
    }

    if (!puedeCrearRol($form['rol'])) {
        $errores[] = 'No tenés permisos para crear usuarios con ese rol.';
    }

    $idDistribuidor = null;
    if ($form['rol'] === ROL_DISTRIBUIDOR) {
        $idDistribuidor = $form['id_distribuidor'] !== '' ? (int)$form['id_distribuidor'] : null;
        if ($idDistribuidor === null) {
            $errores[] = 'Seleccioná el distribuidor asociado a este usuario.';
        }
    }

    if (!$errores) {
        if (!$usuarioService->usuarioDisponible($form['usuario'])) {
            $errores[] = 'Ese nombre de usuario ya existe.';
        }
    }

    if (!$errores) {
        $hash         = password_hash($clave, PASSWORD_DEFAULT);
        $comisionPct  = max(0.0, min(100.0, (float)str_replace(',', '.', (string)($form['comision_pct'] ?? 0))));
        $comisionDistribuidorPct = max(0.0, min(100.0, (float)str_replace(',', '.', (string)($form['comision_distribuidor_pct'] ?? 0))));

        $usuarioService->crear([
            'nombre_completo' => $form['nombre_completo'],
            'usuario' => $form['usuario'],
            'clave_hash' => $hash,
            'rol' => $form['rol'],
            'id_distribuidor' => $idDistribuidor,
            'comision_pct' => $comisionPct,
            'comision_distribuidor_pct' => $comisionDistribuidorPct,
        ]);
        registrarAuditoria($pdo, 'CREAR_USUARIO', 'Usuarios', 'Usuario creado: ' . $form['usuario'], ['rol' => $form['rol']]);

        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'success', 'Usuario creado correctamente.');
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Nuevo usuario</h1>
            <p class="subtle">
                <?php if (esSuperadmin()): ?>
                    Como Superadmin podés crear vendedores, administradores y otros superadmins.
                <?php else: ?>
                    Como Administrador solo podés crear vendedores.
                <?php endif; ?>
            </p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('usuarios/index.php') ?>">Volver</a>
    </div>

    <?php if ($errores): ?>
        <div class="notice notice--error">
            <strong>No se pudo guardar.</strong>
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
                    <label>Contraseña</label>
                    <input type="password" name="clave" required>
                    <small class="field-help">Admin/Superadmin: mínimo 8 caracteres, una mayúscula, una minúscula y un número.</small>
                </div>

                <div class="form-group">
                    <label>Repetir contraseña</label>
                    <input type="password" name="clave2" required>
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" id="rol_select" required>
                        <?php foreach ($rolesPermitidos as $rol): ?>
                            <option value="<?= h($rol, '') ?>" <?= $form['rol'] === $rol ? 'selected' : '' ?>>
                                <?= h($rol, '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="comision_field" style="display:none;">
                    <label>Comisión venta directa % <small class="muted">(0 = sin comisión)</small></label>
                    <input type="number" step="0.01" min="0" max="100" name="comision_pct" value="<?= h($form['comision_pct'] ?? '0', '') ?>">
                    <small class="field-help">Se usa cuando este usuario registra una venta directa.</small>
                </div>
                <div class="form-group" id="comision_distribuidor_field" style="display:none;">
                    <label>Comisión venta distribuidor % <small class="muted">(0 = sin comisión)</small></label>
                    <input type="number" step="0.01" min="0" max="100" name="comision_distribuidor_pct" value="<?= h($form['comision_distribuidor_pct'] ?? '0', '') ?>">
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
                var rolSelect    = document.getElementById('rol_select');
                var distField    = document.getElementById('distribuidor_field');
                var comisionField = document.getElementById('comision_field');
                var comisionDistribuidorField = document.getElementById('comision_distribuidor_field');
                function toggle() {
                    distField.style.display     = rolSelect.value === 'Distribuidor' ? '' : 'none';
                    var muestraComisiones = ['Vendedor','Administrador'].includes(rolSelect.value);
                    comisionField.style.display = muestraComisiones ? '' : 'none';
                    comisionDistribuidorField.style.display = muestraComisiones ? '' : 'none';
                }
                rolSelect.addEventListener('change', toggle);
                toggle();
            })();
            </script>

            <div class="u-form-actions-inline">
                <button type="submit" class="btn">Guardar usuario</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('usuarios/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
