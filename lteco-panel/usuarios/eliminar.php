<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requiereLogin();
require_once __DIR__ . '/../includes/helpers.php';

try {
    requirePost();
    verifyCsrfOrFail();

    if (!esSuperadmin()) {
        denegarAcceso('Solo el Superadmin puede eliminar usuarios.');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario inválido.');
    }

    $idActual = (int)(usuarioActual()['IdUsuario'] ?? 0);
    if ($id === $idActual) {
        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'No podés eliminar tu propio usuario.');
    }

    $usuarioService = new \Lteco\Application\Usuario\UsuarioCrudService(
        new \Lteco\Infrastructure\Repository\UsuarioRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    );

    $usuario = $usuarioService->obtenerParaEliminar($id);

    if (!$usuario) {
        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario no encontrado.');
    }

    if (rolNormalizado($usuario['Rol'] ?? '') === ROL_SUPERADMIN) {
        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'No se puede eliminar a otro Superadmin.');
    }

    $usuarioService->eliminar($id);

    registrarAuditoria($pdo, 'ELIMINAR_USUARIO', 'Usuarios', 'Usuario eliminado: ' . (string)$usuario['Usuario'], [
        'id_usuario'      => $id,
        'nombre_completo' => (string)$usuario['NombreCompleto'],
        'rol'             => (string)$usuario['Rol'],
    ]);

    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'success', 'Usuario "' . $usuario['Usuario'] . '" eliminado correctamente.');
} catch (Throwable $e) {
    redirectErrorSeguro(panelBaseUrl('usuarios/index.php'), $e, 'No se pudo eliminar el usuario.');
}
