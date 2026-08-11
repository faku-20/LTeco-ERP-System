<?php
/**
 * Cron diario — Recordatorios de service por WhatsApp
 * Envía recordatorio a clientes con service programado en 14 días.
 *
 * Uso recomendado en crontab:
 *   0 9 * * * php /opt/ltecobike/lteco-panel/cron/whatsapp_services.php >> /opt/ltecobike/logs/cron_wa_services.log 2>&1
 */

define('LTECO_CRON', true);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// Bootstrap: db + helpers
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/whatsapp.php';

// Asegurar estructura en DB
whatsappEnsureTabla($pdo);
$whatsappService = whatsappService($pdo);

$cfg = whatsappObtenerConfig($pdo);

if (!$cfg['enabled']) {
    echo date('[Y-m-d H:i:s]') . " WhatsApp desactivado — cron finalizado.\n";
    exit(0);
}

if (empty($cfg['tpl_service'])) {
    echo date('[Y-m-d H:i:s]') . " WHATSAPP_TEMPLATE_SERVICE no configurado — cron finalizado.\n";
    exit(0);
}

// Buscar services con FechaProgramada entre hoy+14 y hoy+15 días y Estado = Pendiente
$fechaDesde = date('Y-m-d', strtotime('+14 days'));
$fechaHasta = date('Y-m-d', strtotime('+15 days'));

try {
    $services = $whatsappService->servicesParaRecordatorio($fechaDesde, $fechaHasta);
} catch (Throwable $e) {
    echo date('[Y-m-d H:i:s]') . " ERROR al consultar services: " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($services)) {
    echo date('[Y-m-d H:i:s]') . " Sin services para notificar en los próximos 14 días.\n";
    exit(0);
}

$enviados = 0;
$errores  = 0;
$omitidos = 0;

foreach ($services as $sv) {
    $telefono = trim((string)($sv['Telefono'] ?? ''));
    $idService = (int)$sv['IdService'];

    if ($telefono === '') {
        echo date('[Y-m-d H:i:s]') . " Service #{$idService} — cliente sin teléfono, omitido.\n";
        $omitidos++;
        continue;
    }

    // Verificar que no se haya enviado ya una notificación para este service
    try {
        if ($whatsappService->serviceYaNotificado($idService)) {
            echo date('[Y-m-d H:i:s]') . " Service #{$idService} — ya notificado, omitido.\n";
            $omitidos++;
            continue;
        }
    } catch (Throwable) {}

    $variables = [
        $sv['NombreApellido'] ?? 'Cliente',
        $sv['Modelo'] ?? '',
        date('d/m/Y', strtotime($sv['FechaProgramada'])),
        (string)($sv['NumeroService'] ?? '1'),
    ];

    $ok = enviarWhatsAppTemplateConPdo(
        $pdo,
        $telefono,
        $cfg['tpl_service'],
        $variables,
        'service',
        $idService
    );

    if ($ok) {
        echo date('[Y-m-d H:i:s]') . " Service #{$idService} — enviado a {$telefono}.\n";
        $enviados++;
    } else {
        echo date('[Y-m-d H:i:s]') . " Service #{$idService} — error al enviar a {$telefono}.\n";
        $errores++;
    }

    // Pequeña pausa para no saturar la API de Meta
    usleep(300000);
}

echo date('[Y-m-d H:i:s]') . " Resumen: enviados={$enviados}, errores={$errores}, omitidos={$omitidos}.\n";
exit($errores > 0 ? 1 : 0);
