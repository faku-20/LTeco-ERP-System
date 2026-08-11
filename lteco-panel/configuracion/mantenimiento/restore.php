<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/helpers.php";

requiereSuperadmin();

try {
    requirePost();
    verifyCsrfOrFail();

    if (appIsProduction()) {
        throw new RuntimeException('Restore productivo desde web está deshabilitado. Usá el procedimiento administrativo CLI documentado.');
    }

    $file = basename((string)($_POST['file'] ?? ''));

    if (!\Lteco\Domain\Mantenimiento\BackupComando::esRestaurable($file)) {
        throw new RuntimeException('Solo se pueden restaurar backups .sql desde el panel. Los .sql.gz quedan disponibles para descarga.');
    }

    $rutaBackup = ltecoBackupPathSeguro($file);
    if (!is_file($rutaBackup) || filesize($rutaBackup) <= 0) {
        throw new RuntimeException('Backup vacío o ilegible.');
    }
    if (is_file($rutaBackup . '.sha256')) {
        $expected = trim(strtok((string)file_get_contents($rutaBackup . '.sha256'), " \t"));
        $actual = hash_file('sha256', $rutaBackup);
        if ($expected === '' || $actual === false || !hash_equals($expected, $actual)) {
            throw new RuntimeException('Checksum del backup inválido.');
        }
    }
    $backupDir = ltecoEnsureBackupDir();
    [$dbHost, $dbName, $dbUser, $dbPass] = ltecoMysqlEnv();
    putenv('MYSQL_PWD=' . $dbPass);

    // Backup automático antes de restaurar. Así siempre hay vuelta atrás.
    $fechaAuto = date('Y-m-d_H-i-s');
    $backupAuto = \Lteco\Domain\Mantenimiento\BackupComando::nombreBackupPreRestore($fechaAuto);
    $rutaBackupAuto = $backupDir . DIRECTORY_SEPARATOR . $backupAuto;

    [$backupCode, $backupStdout, $backupStderr] = ltecoRunCommandSeguro(
        \Lteco\Domain\Mantenimiento\BackupComando::comandoDump($dbHost, $dbUser, $dbName),
        null,
        $rutaBackupAuto
    );

    if ($backupCode !== 0) {
        @unlink($rutaBackupAuto);
        throw new RuntimeException('No se pudo generar el backup automático previo a la restauración: ' . trim($backupStderr ?: $backupStdout));
    }

    @chmod($rutaBackupAuto, 0640);

    [$restoreCode, $restoreStdout, $restoreStderr] = ltecoRunCommandSeguro(
        \Lteco\Domain\Mantenimiento\BackupComando::comandoRestore($dbHost, $dbUser, $dbName),
        $rutaBackup,
        null
    );

    putenv('MYSQL_PWD');

    if ($restoreCode !== 0) {
        registrarAuditoria(
            $pdo,
            'RESTORE_ERROR',
            'Mantenimiento',
            'Falló la restauración de un backup.',
            ['archivo' => $file, 'error' => trim($restoreStderr ?: $restoreStdout)]
        );
        throw new RuntimeException('No se pudo restaurar el backup: ' . trim($restoreStderr ?: $restoreStdout));
    }

    registrarAuditoria(
        $pdo,
        'RESTORE_BACKUP',
        'Mantenimiento',
        'Se restauró un backup del sistema.',
        ['archivo' => $file, 'backup_previo' => $backupAuto]
    );

    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'success',
        'Backup restaurado correctamente. Se generó un backup automático previo: ' . $backupAuto
    );
} catch (Throwable $e) {
    putenv('MYSQL_PWD');
    logPanelError('restore_backup', $e);
    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'error',
        mensajeErrorSeguro($e, 'No se pudo restaurar el backup.')
    );
}
