<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

if (!usuarioLogueado()) {
    redirect(panelBaseUrl('login.php'));
}

try {
    requirePost();
    verifyCsrfOrFail();
} catch (Throwable $e) {
    http_response_code(405);
    redirectWithFlash(panelBaseUrl(esSuperadmin() ? 'dashboard.php' : 'inicio.php'), 'error', 'El cierre de sesión debe hacerse desde el botón del panel.');
}

registrarAuditoria($pdo, 'LOGOUT', 'Seguridad', 'Cierre de sesión', [
    'ip' => ipClienteSegura(),
]);
cerrarSesion();
redirect(panelBaseUrl('login.php'));
