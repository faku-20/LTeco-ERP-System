<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/whatsapp.php';
require_once __DIR__ . '/../../includes/ai.php';

requiereSuperadmin();

try {
    requirePost();
    verifyCsrfOrFail();

    whatsappEnsureTabla($pdo);
    aiEnsureSchema($pdo);

    $telefono = whatsappFormatearTelefono((string)configEnv('LTECO_WHATSAPP_TEST_PHONE', ''));
    if ($telefono === null) {
        redirectWithFlash(
            panelBaseUrl('configuracion/mantenimiento/index.php'),
            'error',
            'Configurá LTECO_WHATSAPP_TEST_PHONE antes de usar esta prueba.'
        );
    }

    whatsappService($pdo)->programarResetPrueba($telefono);

    registrarAuditoria($pdo, 'WHATSAPP_RESET_PRUEBA', 'WhatsApp', 'Próxima respuesta inicial habilitada para el teléfono de prueba.', [
        'telefono' => $telefono,
    ]);

    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'success',
        'Prueba habilitada. El próximo mensaje válido de ese número recibirá una única respuesta inicial sin borrar el historial.'
    );
} catch (Throwable $e) {
    logPanelError('whatsapp_reset_prueba', $e);
    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'error',
        mensajeErrorSeguro($e, 'No se pudo resetear el teléfono de prueba.')
    );
}
