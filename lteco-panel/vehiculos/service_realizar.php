<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requiereLogin();
requiereAdmin();

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
$observacionesPorService = $_POST['observaciones_service'] ?? [];
$origenService = trim((string)($_POST['origen'] ?? ''));
$redirectUrl = ($origenService === 'postventa' && $idVehiculo !== '')
    ? panelBaseUrl('postventa/detalle.php') . '?' . http_build_query(['id' => $idVehiculo])
    : panelBaseUrl('vehiculos/editar.php') . '?' . http_build_query(['id' => $idVehiculo]);
$observaciones = '';

if (is_array($observacionesPorService) && $idService > 0) {
    $observaciones = trim((string)($observacionesPorService[(string)$idService] ?? $observacionesPorService[$idService] ?? ''));
} else {
    $observaciones = trim((string)($_POST['observaciones'] ?? ''));
}

if ($idService <= 0 || $idVehiculo === '') {
    redirectWithFlash($redirectUrl, 'error', 'Service inválido.');
}

try {
    // Usuario para el historial (concern de auth, se resuelve en el handler).
    $usuarioHistorial = function_exists('usuarioActual')
        ? (string)(usuarioActual()['Usuario'] ?? usuarioActual()['NombreCompleto'] ?? 'Sistema')
        : 'Sistema';

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\PostventaRepository($conexion);
    $service = new Lteco\Application\Postventa\PostventaService($repo);

    $resultado = $service->realizarService($idService, $idVehiculo, $observaciones, $usuarioHistorial);

    redirectWithFlash(
        $redirectUrl,
        $resultado['success'] ? 'success' : 'error',
        $resultado['mensaje']
    );
} catch (Throwable $e) {
    redirectWithFlash(
        $redirectUrl,
        'error',
        'No se pudo actualizar el service.'
    );
}
