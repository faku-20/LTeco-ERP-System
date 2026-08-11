<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/helpers.php";

requiereSuperadmin();

try {
    $file = basename((string)($_GET['file'] ?? ''));
    $path = ltecoBackupPathSeguro($file);

    registrarAuditoria(
        $pdo,
        'DESCARGAR_BACKUP',
        'Mantenimiento',
        'Se descargó un backup del sistema.',
        ['archivo' => $file]
    );

    header('Content-Type: ' . \Lteco\Domain\Mantenimiento\BackupComando::contentTypeDescarga($file));
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
} catch (Throwable $e) {
    logPanelError('download_backup', $e);
    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'error',
        mensajeErrorSeguro($e, 'No se pudo descargar el backup.')
    );
}
