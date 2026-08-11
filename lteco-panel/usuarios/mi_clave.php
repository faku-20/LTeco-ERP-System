<?php
$pageTitle = "Cambiar mi clave | Ltecobike";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
require_once __DIR__ . "/../includes/helpers.php";

$id = (int)(usuarioActual()['IdUsuario'] ?? 0);
$volverUrl = panelBaseUrl(esSuperadmin() ? 'dashboard.php' : 'inicio.php');

if ($id <= 0) {
    redirectWithFlash($volverUrl, 'error', 'Sesión inválida.');
}

$usuarioService = new \Lteco\Application\Usuario\UsuarioCrudService(
    new \Lteco\Infrastructure\Repository\UsuarioRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$usuarioData = $usuarioService->obtenerParaClave($id);

if (!$usuarioData) {
    redirectWithFlash($volverUrl, 'error', 'Usuario no encontrado.');
}

$usuarioData['Rol'] = rolNormalizado($usuarioData['Rol'] ?? ROL_VENDEDOR);

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        $errores[] = 'La sesión del formulario venció. Volvé a intentar.';
    }

    $actual = (string)($_POST['clave_actual'] ?? '');
    $clave  = (string)($_POST['clave'] ?? '');
    $clave2 = (string)($_POST['clave2'] ?? '');

    if ($actual === '' || $clave === '' || $clave2 === '') {
        $errores[] = 'Completá todos los campos.';
    } elseif (empty($usuarioData['ClaveHash']) || !password_verify($actual, (string)$usuarioData['ClaveHash'])) {
        $errores[] = 'La contraseña actual no es correcta.';
    } elseif ($clave !== $clave2) {
        $errores[] = 'Las contraseñas nuevas no coinciden.';
    } else {
        if (password_verify($clave, (string)$usuarioData['ClaveHash'])) {
            $errores[] = 'La nueva contraseña no puede ser igual a la actual.';
        }

        $errores = array_merge($errores, validarClaveSegunRol($clave, $usuarioData['Rol']));
    }

    if (!$errores) {
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $usuarioService->cambiarClave($id, $hash);
        registrarAuditoria($pdo, 'CAMBIAR_CLAVE_PROPIA', 'Usuarios', 'El usuario actualizó su propia contraseña.', ['id_usuario' => $id]);

        redirectWithFlash($volverUrl, 'success', 'Tu contraseña se actualizó correctamente.');
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Cambiar mi clave</h1>
            <p class="subtle">Actualizá tu propia contraseña de acceso.</p>
        </div>
        <a class="btn-secondary" href="<?= $volverUrl ?>">Volver</a>
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
        <p><strong>Usuario:</strong> <?= h($usuarioData['Usuario'], '') ?></p>
        <p><strong>Rol:</strong> <?= h($usuarioData['Rol'], '') ?></p>

        <form method="POST">
            <?= csrfInput() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Contraseña actual</label>
                    <input type="password" name="clave_actual" required>
                </div>

                <div class="form-group">
                    <label>Nueva contraseña</label>
                    <input type="password" name="clave" required>
                    <small class="field-help">Debe ser distinta a la actual. Admin/Superadmin requieren mínimo 8 caracteres, una mayúscula, una minúscula y un número.</small>
                </div>

                <div class="form-group">
                    <label>Repetir nueva contraseña</label>
                    <input type="password" name="clave2" required>
                </div>
            </div>

            <div class="u-form-actions-inline">
                <button type="submit" class="btn">Actualizar mi contraseña</button>
                <a class="btn-secondary" href="<?= $volverUrl ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
