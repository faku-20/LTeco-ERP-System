<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requiereModulo("postventa");

/*
 * LTECO:POST_CSRF_SERVICE_ACTION_V3
 */
try {
    requirePost();
    verifyCsrfOrFail();
} catch (Throwable $e) {
    $idVehiculoCsrf = trim((string)($_POST['id_vehiculo'] ?? ''));
    $redirectCsrf = $idVehiculoCsrf !== ''
        ? panelBaseUrl('postventa/detalle.php') . '?' . http_build_query(['id' => $idVehiculoCsrf])
        : panelBaseUrl('postventa/index.php');

    redirectWithFlash(
        $redirectCsrf,
        'error',
        'La sesión del formulario venció o el envío no es válido. Volvé a intentar.'
    );
}

$idService = isset($_POST['id_service']) ? (int)$_POST['id_service'] : 0;
$idVehiculo = trim((string)($_POST['id_vehiculo'] ?? ''));
$nota = trim((string)($_POST['nota_tecnica'] ?? ''));

$redirectUrl = $idVehiculo !== ''
    ? panelBaseUrl('postventa/detalle.php') . '?' . http_build_query(['id' => $idVehiculo])
    : panelBaseUrl('postventa/index.php');

if ($idService <= 0 || $idVehiculo === '') {
    redirectWithFlash($redirectUrl, 'error', 'Service inválido.');
}

if (esVendedor() && !vendedorPuedeOperarPostventaService($pdo, $idService, $idVehiculo)) {
    denegarAcceso('No tenés permisos para modificar este service.');
}

if ($nota === '') {
    redirectWithFlash($redirectUrl, 'error', 'Ingresá una observación técnica.');
}

if (mb_strlen($nota) > 300) {
    redirectWithFlash($redirectUrl, 'error', 'La observación técnica no puede superar 300 caracteres.');
}

try {
    // Usuario para el historial (concern de auth, se resuelve en el handler).
    $usuarioHistorial = function_exists('usuarioActual')
        ? (string)(usuarioActual()['Usuario'] ?? usuarioActual()['NombreCompleto'] ?? 'Sistema')
        : 'Sistema';

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\PostventaRepository($conexion);
    $service = new Lteco\Application\Postventa\PostventaService($repo);

    $resultado = $service->agregarObservacion($idService, $idVehiculo, $nota, $usuarioHistorial);

    redirectWithFlash(
        $redirectUrl,
        $resultado['success'] ? 'success' : 'error',
        $resultado['mensaje']
    );
} catch (Throwable $e) {
    redirectWithFlash(
        $redirectUrl,
        'error',
        'No se pudo agregar la observación técnica.'
    );
}
