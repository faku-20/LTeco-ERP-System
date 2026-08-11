<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereGestionUsuarios();
require_once __DIR__ . "/../includes/helpers.php";

try {
    requirePost();
    verifyCsrfOrFail();

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario inválido.');
    }

    $usuarioService = new \Lteco\Application\Usuario\UsuarioCrudService(
        new \Lteco\Infrastructure\Repository\UsuarioRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    );

    $usuario = $usuarioService->obtenerParaToggle($id);

    if (!$usuario) {
        redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario no encontrado.');
    }

    $usuario['Rol'] = rolNormalizado($usuario['Rol'] ?? ROL_VENDEDOR);

    if (!puedeActivarDesactivarUsuario($usuario)) {
        denegarAcceso('No tenés permisos para activar o desactivar ese usuario.');
    }

    $nuevoEstado = ((int)$usuario['Activo'] === 1) ? 0 : 1;
    $usuarioService->actualizarActivo($id, $nuevoEstado);
    registrarAuditoria($pdo, $nuevoEstado === 1 ? 'ACTIVAR_USUARIO' : 'DESACTIVAR_USUARIO', 'Usuarios', 'Estado actualizado para usuario: ' . (string)$usuario['Usuario'], ['id_usuario' => $id, 'activo' => $nuevoEstado]);

    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'success', 'Estado del usuario actualizado correctamente.');
} catch (Throwable $e) {
    redirectErrorSeguro(panelBaseUrl('usuarios/index.php'), $e, 'No se pudo actualizar el usuario.');
}
