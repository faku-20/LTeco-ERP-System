<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requiereLogin();
requiereModulo('postventa');

try {
    requirePost();
    verifyCsrfOrFail();

    $idService  = (int)($_POST['id_service'] ?? 0);
    $idVehiculo = trim((string)($_POST['id_vehiculo'] ?? ''));

    if ($idService <= 0 || $idVehiculo === '') {
        throw new RuntimeException('Datos inválidos.');
    }

    if (esVendedor() && !vendedorPuedeOperarPostventaService($pdo, $idService, $idVehiculo)) {
        denegarAcceso('No tenés permisos para modificar este service.');
    }

    $usuario = (string)(usuarioActual()['Usuario'] ?? usuarioActual()['NombreCompleto'] ?? 'Sistema');

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\PostventaRepository($conexion);
    $service = new Lteco\Application\Postventa\PostventaService($repo);

    $service->marcarNotificadoWa($idService, $idVehiculo, $usuario);

    redirectWithFlash(panelBaseUrl('postventa/index.php'), 'success', 'Recordatorio marcado como enviado.');
} catch (Throwable $e) {
    redirectWithFlash(panelBaseUrl('postventa/index.php'), 'error', 'No se pudo registrar el recordatorio.');
}
