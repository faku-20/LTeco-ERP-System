<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sesionExpiradaPorInactividad(): bool
{
    if (empty($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
        return false;
    }

    $ultimoUso = (int)($_SESSION['last_activity'] ?? time());
    return (time() - $ultimoUso) > sessionIdleTimeoutSeconds();
}

function mantenerSesionSegura(): void
{
    if (empty($_SESSION['usuario']) || !is_array($_SESSION['usuario'])) {
        return;
    }

    if (sesionExpiradaPorInactividad()) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        return;
    }

    $creada = (int)($_SESSION['created_at'] ?? time());
    if ((time() - $creada) > sessionRegenerateSeconds()) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }

    $_SESSION['last_activity'] = time();
}

function usuarioLogueado(): bool
{
    mantenerSesionSegura();
    return !empty($_SESSION['usuario']) && is_array($_SESSION['usuario']);
}

function usuarioActual(): ?array
{
    if (!usuarioLogueado()) {
        return null;
    }

    $usuario = $_SESSION['usuario'];
    $usuario['Rol'] = rolNormalizado($usuario['Rol'] ?? ROL_VENDEDOR);
    return $usuario;
}

function rolActual(): string
{
    return rolNormalizado(usuarioActual()['Rol'] ?? ROL_VENDEDOR);
}

function requiereLogin(): void
{
    if (!usuarioLogueado()) {
        redirect(panelBaseUrl('login.php'));
    }

    requerirMfaPrivilegiadoSiCorresponde();
}

function esSuperadmin(): bool
{
    return rolActual() === ROL_SUPERADMIN;
}

function esAdministrador(): bool
{
    return rolActual() === ROL_ADMINISTRADOR;
}

function esVendedor(): bool
{
    return rolActual() === ROL_VENDEDOR;
}

function esDistribuidor(): bool
{
    return rolActual() === ROL_DISTRIBUIDOR;
}

/**
 * Compatibilidad con el código existente.
 * En este proyecto "admin" significa rol con permisos estructurales/funcionales altos:
 * Superadmin o Administrador.
 */
function esAdmin(): bool
{
    return esSuperadmin() || esAdministrador();
}

function esAdminOperativo(): bool
{
    return esAdmin();
}

function denegarAcceso(string $mensaje = 'No tenés permisos para acceder a esa sección.'): never
{
    http_response_code(403);
    $mensajeSeguro = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    if (esDistribuidor()) {
        $volverPath = 'distribuidores/index.php';
    } elseif (esVendedor()) {
        $volverPath = 'inicio.php';
    } else {
        $volverPath = 'inicio.php';
    }
    $volver = htmlspecialchars(panelBaseUrl($volverPath), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso denegado</title></head><body><h1>Acceso denegado</h1><p>' . $mensajeSeguro . '</p><p><a href="' . $volver . '">Volver al panel</a></p></body></html>';
    exit;
}

function requiereAdmin(): void
{
    requiereLogin();

    if (!esAdmin()) {
        denegarAcceso();
    }
}

function requiereNoDistribuidor(): void
{
    requiereLogin();

    if (esDistribuidor()) {
        denegarAcceso('Los distribuidores solo pueden acceder a su módulo de stock, pedidos y ventas.');
    }
}

function requiereSuperadmin(): void
{
    requiereLogin();

    if (!esSuperadmin()) {
        denegarAcceso('Solo el Superadmin puede realizar esa acción.');
    }
}

function requiereGestionUsuarios(): void
{
    requiereLogin();

    if (!esSuperadmin() && !esAdministrador()) {
        denegarAcceso('No tenés permisos para gestionar usuarios.');
    }
}

function rolesQuePuedeCrearUsuarioActual(): array
{
    if (esSuperadmin()) {
        return [ROL_VENDEDOR, ROL_ADMINISTRADOR, ROL_SUPERADMIN, ROL_DISTRIBUIDOR];
    }

    if (esAdministrador()) {
        return [ROL_VENDEDOR, ROL_DISTRIBUIDOR];
    }

    return [];
}

function puedeCrearRol(string $rol): bool
{
    return in_array(rolNormalizado($rol), rolesQuePuedeCrearUsuarioActual(), true);
}

function puedeGestionarUsuario(array $usuarioObjetivo, string $accion = 'ver'): bool
{
    requiereLogin();

    $rolObjetivo = rolNormalizado($usuarioObjetivo['Rol'] ?? ROL_VENDEDOR);
    $idObjetivo = (int)($usuarioObjetivo['IdUsuario'] ?? 0);
    $idActual = (int)(usuarioActual()['IdUsuario'] ?? 0);

    if ($accion === 'ver') {
        return esSuperadmin() || esAdministrador();
    }

    // Auto-servicio: cualquier usuario puede cambiar su propia clave.
    if ($accion === 'clave' && $idObjetivo > 0 && $idObjetivo === $idActual) {
        return true;
    }

    if ($accion === 'desactivar' && $idObjetivo > 0 && $idObjetivo === $idActual) {
        return false;
    }

    if (esSuperadmin()) {
        return true;
    }

    if (esAdministrador()) {
        // El Administrador puede cambiar la clave de cualquier usuario salvo el Superadmin
        // (su propio rol, Distribuidores y Vendedores). La activación/desactivación sigue
        // acotada a Vendedores.
        if ($accion === 'clave') {
            return $rolObjetivo !== ROL_SUPERADMIN;
        }

        return $rolObjetivo === ROL_VENDEDOR && in_array($accion, ['activar', 'desactivar'], true);
    }

    return false;
}

function puedeCambiarClaveUsuario(array $usuarioObjetivo): bool
{
    return puedeGestionarUsuario($usuarioObjetivo, 'clave');
}

function puedeActivarDesactivarUsuario(array $usuarioObjetivo): bool
{
    $activo = (int)($usuarioObjetivo['Activo'] ?? 0) === 1;
    return puedeGestionarUsuario($usuarioObjetivo, $activo ? 'desactivar' : 'activar');
}

function puedeCambiarRolUsuario(array $usuarioObjetivo): bool
{
    return esSuperadmin();
}


function puedeVerAuditoria(): bool
{
    return esSuperadmin() || esAdministrador();
}

function requiereAuditoria(): void
{
    requiereLogin();

    if (!puedeVerAuditoria()) {
        denegarAcceso('No tenés permisos para ver la auditoría del sistema.');
    }
}

function permisosPorRol(): array
{
    return [
        ROL_SUPERADMIN => ['*'],
        ROL_ADMINISTRADOR => [
            'dashboard', 'busqueda', 'ventas', 'clientes', 'vehiculos', 'repuestos',
            'postventa', 'gastos', 'balance', 'importaciones', 'distribuidores',
            'usuarios', 'configuracion', 'ecommerce',
        ],
        ROL_VENDEDOR => ['inicio', 'busqueda', 'ventas', 'clientes', 'vehiculos', 'repuestos', 'postventa'],
        ROL_DISTRIBUIDOR => ['distribuidor_panel'],
    ];
}

function puedeAccederModulo(string $modulo, ?string $rol = null): bool
{
    $modulo = trim($modulo);
    $rol = $rol !== null ? rolNormalizado($rol) : rolActual();
    $permisos = permisosPorRol()[$rol] ?? [];
    return in_array('*', $permisos, true) || in_array($modulo, $permisos, true);
}

function puedeEjecutarAccion(string $accion, ?string $rol = null): bool
{
    $rol = $rol !== null ? rolNormalizado($rol) : rolActual();
    $accion = trim($accion);

    if ($rol === ROL_SUPERADMIN) {
        return true;
    }

    $acciones = [
        ROL_ADMINISTRADOR => [
            'crear_venta', 'anular_venta', 'editar_cliente', 'editar_vehiculo', 'editar_repuesto',
            'crear_gasto', 'editar_gasto', 'gestionar_distribuidor', 'asignar_stock_distribuidor',
            'gestionar_postventa', 'gestionar_usuario_operativo', 'cambiar_configuracion', 'gestionar_ecommerce',
        ],
        ROL_VENDEDOR => ['crear_venta', 'ver_cliente', 'ver_vehiculo', 'ver_postventa'],
        ROL_DISTRIBUIDOR => ['crear_venta_distribuidor', 'crear_pedido_distribuidor', 'ver_estado_cuenta_propio'],
    ];

    return in_array($accion, $acciones[$rol] ?? [], true);
}

function requiereModulo(string $modulo): void
{
    requiereLogin();

    if (!puedeAccederModulo($modulo)) {
        denegarAcceso('No tenés permisos para acceder a ese módulo.');
    }
}

function puedeVerRegistro(string $tipo, int|string $id): bool
{
    if (!usuarioLogueado()) {
        return false;
    }

    if (esSuperadmin() || esAdministrador()) {
        return true;
    }

    if (esDistribuidor()) {
        return $tipo === 'distribuidor_panel';
    }

    if ($tipo === 'venta') {
        global $pdo;
        return $pdo instanceof PDO && vendedorPuedeVerVenta($pdo, (int)$id);
    }

    if ($tipo === 'cliente') {
        global $pdo;
        return $pdo instanceof PDO && vendedorPuedeVerCliente($pdo, (int)$id);
    }

    if ($tipo === 'postventa') {
        global $pdo;
        return $pdo instanceof PDO && vendedorPuedeVerPostventa($pdo, (string)$id);
    }

    // Alcance futuro: filtrar vehículos por UsuarioVendedorId o sucursal.
    return in_array($tipo, ['vehiculo', 'repuesto'], true);
}

function vendedorPuedeVerVenta(PDO $pdo, int $idVenta, ?int $idUsuario = null): bool
{
    $idUsuario = $idUsuario ?? (int)(usuarioActual()['IdUsuario'] ?? 0);
    return seguridadService($pdo)->vendedorPuedeVerVenta($idVenta, $idUsuario);
}

function vendedorPuedeVerCliente(PDO $pdo, int $idCliente, ?int $idUsuario = null): bool
{
    $idUsuario = $idUsuario ?? (int)(usuarioActual()['IdUsuario'] ?? 0);
    return seguridadService($pdo)->vendedorPuedeVerCliente($idCliente, $idUsuario);
}

function vendedorPuedeVerPostventa(PDO $pdo, string $idVehiculo, ?int $idUsuario = null): bool
{
    $idUsuario = $idUsuario ?? (int)(usuarioActual()['IdUsuario'] ?? 0);
    return seguridadService($pdo)->vendedorPuedeVerPostventa($idVehiculo, $idUsuario);
}

function vendedorPuedeOperarPostventaService(PDO $pdo, int $idService, string $idVehiculo, ?int $idUsuario = null): bool
{
    $idUsuario = $idUsuario ?? (int)(usuarioActual()['IdUsuario'] ?? 0);
    return seguridadService($pdo)->vendedorPuedeOperarPostventaService(
        $idService,
        $idVehiculo,
        $idUsuario
    );
}

function requierePuedeVerRegistro(string $tipo, int|string $id): void
{
    requiereLogin();

    if (!puedeVerRegistro($tipo, $id)) {
        denegarAcceso('No tenés permisos para acceder a ese registro.');
    }
}

function usuarioRequiereMfa(array $usuario): bool
{
    $rol = rolNormalizado((string)($usuario['Rol'] ?? ROL_VENDEDOR));
    return in_array($rol, [ROL_SUPERADMIN, ROL_ADMINISTRADOR], true);
}

function usuarioTieneMfaActivo(array $usuario): bool
{
    return !empty($usuario['mfa_enabled']) && !empty($usuario['mfa_secret']);
}

function usuarioMfaSetupRequired(array $usuario): bool
{
    return usuarioRequiereMfa($usuario) && !usuarioTieneMfaActivo($usuario);
}

function usuarioMfaVerificadoEnSesion(): bool
{
    return !empty($_SESSION['mfa_verified']) && !empty($_SESSION['mfa_verified_at']);
}

function rutaActualPanelRelativa(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($script, '/lteco-panel/');
    if ($pos !== false) {
        return ltrim(substr($script, $pos + strlen('/lteco-panel/')), '/');
    }
    return ltrim(basename($script), '/');
}

function rutaPermiteMfaPendiente(string $ruta): bool
{
    $ruta = ltrim($ruta, '/');
    if (in_array($ruta, ['login.php', 'logout.php', 'mfa_verificar.php', 'usuarios/mfa.php'], true)) {
        return true;
    }
    return str_starts_with($ruta, 'assets/');
}

function requerirMfaPrivilegiadoSiCorresponde(): void
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario) || !usuarioRequiereMfa($usuario)) {
        return;
    }

    $ruta = rutaActualPanelRelativa();
    if (rutaPermiteMfaPendiente($ruta)) {
        return;
    }

    if (usuarioMfaSetupRequired($usuario)) {
        redirect(panelBaseUrl('usuarios/mfa.php') . '?' . http_build_query(['id' => (int)($usuario['IdUsuario'] ?? 0), 'setup_required' => 1]));
    }

    if (!usuarioMfaVerificadoEnSesion()) {
        $_SESSION = [];
        redirectWithFlash(panelBaseUrl('login.php'), 'error', 'Ingresá nuevamente y completá MFA para continuar.');
    }
}

function esDistribuidorPropietario(array $usuario, array $registro): bool
{
    $idUsuarioDistribuidor = (int)($usuario['IdDistribuidor'] ?? 0);
    $idRegistroDistribuidor = (int)($registro['IdDistribuidor'] ?? $registro['DistribuidorVendedorId'] ?? 0);
    return $idUsuarioDistribuidor > 0 && $idUsuarioDistribuidor === $idRegistroDistribuidor;
}

function cerrarSesion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
