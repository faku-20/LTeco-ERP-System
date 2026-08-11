<?php
$pageTitle = "Cambiar clave | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereGestionUsuarios();
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

$usuarioData = $usuarioService->obtenerParaClave($id);

if (!$usuarioData) {
    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario no encontrado.');
}

$usuarioData['Rol'] = rolNormalizado($usuarioData['Rol'] ?? ROL_VENDEDOR);
if (!puedeCambiarClaveUsuario($usuarioData)) {
    denegarAcceso('No tenés permisos para cambiar la clave de ese usuario.');
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        $errores[] = 'La sesión del formulario venció. Volvé a intentar.';
    }
    $clave = (string)($_POST['clave'] ?? '');
    $clave2 = (string)($_POST['clave2'] ?? '');

    if ($clave === '' || $clave2 === '') {
        $errores[] = 'Completá ambos campos.';
    } elseif ($clave !== $clave2) {
        $errores[] = 'Las contraseñas no coinciden.';
    } else {
        if (!empty($usuarioData['ClaveHash']) && password_verify($clave, (string)$usuarioData['ClaveHash'])) {
            $errores[] = 'La nueva contraseña no puede ser igual a la contraseña actual.';
        }

        $errores = array_merge($errores, validarClaveSegunRol($clave, $usuarioData['Rol']));
    }

    if (!$errores) {
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $usuarioService->cambiarClave($id, $hash);
        registrarAuditoria($pdo, 'CAMBIAR_CLAVE_USUARIO', 'Usuarios', 'Contraseña actualizada para usuario: ' . (string)$usuarioData['Usuario'], ['id_usuario' => $id]);

        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'success', 'Contraseña actualizada correctamente.');
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Cambiar clave</h1>
            <p class="subtle">Actualización de contraseña respetando la jerarquía de usuarios.</p>
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
        <p><strong>Usuario:</strong> <?= h($usuarioData['Usuario'], '') ?></p>
        <p><strong>Nombre:</strong> <?= h($usuarioData['NombreCompleto'], '') ?></p>
        <p><strong>Rol:</strong> <?= h($usuarioData['Rol'], '') ?></p>

        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="id" value="<?= (int)$usuarioData['IdUsuario'] ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Nueva contraseña</label>
                    <input type="password" name="clave" required>
                    <small class="field-help">Debe ser distinta a la actual. Admin/Superadmin requieren mínimo 8 caracteres, una mayúscula, una minúscula y un número.</small>
                </div>

                <div class="form-group">
                    <label>Repetir contraseña</label>
                    <input type="password" name="clave2" required>
                </div>
            </div>

            <div class="u-form-actions-inline">
                <button type="submit" class="btn">Actualizar contraseña</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('usuarios/index.php') ?>">Cancelar</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
