<?php
declare(strict_types=1);

/**
 * Batch de estados de postventa (S3): delega en Lteco\Application\Postventa\
 * PostventaService::actualizarEstados(). La resolución del vendedor (auth) y el
 * chequeo de existencia de la tabla garantia (infra del panel) se quedan en el
 * handler; toda la lógica de mutación vive en el servicio + PostventaRepository.
 */
function actualizarEstadosPostventa(PDO $pdo): void
{
    $idUsuarioVendedor = function_exists('esVendedor') && esVendedor()
        ? (int)(usuarioActual()['IdUsuario'] ?? 0)
        : 0;

    $incluirGarantias = function_exists('dbTieneTabla') && dbTieneTabla($pdo, 'garantia');

    $conexion = new Lteco\Infrastructure\Db\Connection($pdo);
    $repo = new Lteco\Infrastructure\Repository\PostventaRepository($conexion);
    $service = new Lteco\Application\Postventa\PostventaService($repo);

    $service->actualizarEstados($idUsuarioVendedor, $incluirGarantias);
}

// Guard de inclusión: este archivo es un partial, no un endpoint. Solo corre
// cuando una página del panel (con su guard de módulo + db.php) ya estableció
// $pdo. El acceso HTTP directo cae acá y retorna sin mutar ni filtrar errores,
// en vez de fatalear por $pdo indefinido.
if (!isset($pdo) || !$pdo instanceof PDO) {
    return;
}

// Ejecutar automáticamente al incluir este archivo
actualizarEstadosPostventa($pdo);

// Notificaciones de services (solo una vez al día, en la primera carga del día)
$cacheFile = sys_get_temp_dir() . '/lteco_svc_notif_' . date('Y-m-d') . '.lock';
if (function_exists('esAdmin') && esAdmin() && !file_exists($cacheFile)) {
    @file_put_contents($cacheFile, '1');
    try {
        require_once __DIR__ . '/../notificaciones/eventos.php';

        $notificacionService = new Lteco\Application\Postventa\PostventaService(
            new Lteco\Infrastructure\Repository\PostventaRepository(
                new Lteco\Infrastructure\Db\Connection($pdo)
            )
        );
        $notificaciones = $notificacionService->servicesParaNotificar();
        $proximos = $notificaciones['proximos'];
        if ($proximos) notificarServicesProximos($proximos);

        $vencidos = $notificaciones['vencidos'];
        if ($vencidos) notificarServicesVencidos($vencidos);
    } catch (Throwable) {}
}
