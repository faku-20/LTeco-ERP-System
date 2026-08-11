<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

requiereModulo("postventa");

try {
    requirePost();
    verifyCsrfOrFail();
} catch (Throwable $e) {
    redirectWithFlash(panelBaseUrl('postventa/index.php'), 'error', 'La sesión del formulario venció. Volvé a intentar.');
}

$idVehiculo = trim((string)($_POST['id_vehiculo'] ?? ''));
$idVenta = (int)($_POST['id_venta'] ?? 0);
$idCliente = (int)($_POST['id_cliente'] ?? 0);
$idService = (int)($_POST['id_service'] ?? 0);
$diagnostico = normalizarTextoHumano($_POST['diagnostico'] ?? '', 700);
$solucion = limpiarTextoOpcional($_POST['solucion'] ?? null);
$tecnico = normalizarTextoHumano($_POST['tecnico'] ?? '', 120);
$estado = trim((string)($_POST['estado'] ?? 'Abierta'));
$observaciones = limpiarTextoOpcional($_POST['observaciones'] ?? null);
$idRepuesto = (int)($_POST['id_repuesto'] ?? 0);
$cantidadRepuesto = enteroNoNegativo($_POST['cantidad_repuesto'] ?? 0);

$redirectUrl = $idVehiculo !== ''
    ? panelBaseUrl('postventa/detalle.php') . '?' . http_build_query(['id' => $idVehiculo])
    : panelBaseUrl('postventa/index.php');

try {
    if ($idVehiculo === '' || $idVenta <= 0 || $diagnostico === '') {
        throw new RuntimeException('Completá diagnóstico y vehículo.');
    }

    $idempotencyKey = panelIdempotencyRequestKey();
    $idempotencyHash = panelIdempotencyRequestHash('panel.postventa.intervencion.crear', $_POST);

    $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
    $repo = new Lteco\Infrastructure\Repository\PostventaRepository($conexion);
    $postventaService = new Lteco\Application\Postventa\PostventaService($repo);

    if (esVendedor()) {
        if (!$postventaService->vendedorPuedeIntervenir(
            $idVehiculo,
            $idVenta,
            (int)(usuarioActual()['IdUsuario'] ?? 0)
        )) {
            denegarAcceso('No tenés permisos para modificar esta postventa.');
        }

        if ($idService > 0 && !vendedorPuedeOperarPostventaService($pdo, $idService, $idVehiculo)) {
            denegarAcceso('No tenés permisos para modificar este service.');
        }
    }

    // Técnico e identidad del registrante: concern de auth, se resuelven acá y se
    // pasan ya resueltos al servicio.
    $tecnicoResuelto = $tecnico !== ''
        ? $tecnico
        : (usuarioActual()['NombreCompleto'] ?? usuarioActual()['Usuario'] ?? null);
    $idUsuarioRegistra = ((int)(usuarioActual()['IdUsuario'] ?? 0)) ?: null;

    $service = new Lteco\Application\Postventa\PostventaIntervencionService($repo);

    $pdo->beginTransaction();

    $idempotencyRow = panelIdempotencyClaim(
        $pdo,
        'panel.postventa.intervencion.crear',
        $idempotencyKey,
        $idempotencyHash,
        $idUsuarioRegistra
    );
    if ($idempotencyRow !== null) {
        $pdo->commit();
        redirect((string)($idempotencyRow['RedirectUrl'] ?: $redirectUrl));
    }

    $idHistorial = $service->guardarIntervencion(
        $idVehiculo,
        $idVenta,
        $idCliente,
        $idService,
        $diagnostico,
        $solucion,
        $tecnicoResuelto,
        $estado,
        $observaciones,
        $idRepuesto,
        $cantidadRepuesto,
        $idUsuarioRegistra
    );

    registrarAuditoria($pdo, 'POSTVENTA_INTERVENCION', 'Postventa', 'Intervención técnica registrada', [
        'id_historial_tecnico' => $idHistorial,
        'id_vehiculo' => $idVehiculo,
        'id_venta' => $idVenta,
        'id_repuesto' => $idRepuesto ?: null,
        'cantidad_repuesto' => $cantidadRepuesto ?: null,
    ]);

    panelIdempotencyComplete($pdo, $idempotencyKey, 'postventa_intervencion', $idHistorial, $redirectUrl);

    $pdo->commit();
    redirectWithFlash($redirectUrl, 'success', 'Intervención técnica registrada.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirectWithFlash($redirectUrl, 'error', mensajeErrorSeguro($e, 'No se pudo registrar la intervención.'));
}
