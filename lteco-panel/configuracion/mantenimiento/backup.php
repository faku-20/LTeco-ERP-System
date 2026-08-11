<?php
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/helpers.php";

requiereSuperadmin();

try {
    requirePost();
    verifyCsrfOrFail();

    $backupDir = ltecoEnsureBackupDir();
    [$dbHost, $dbName, $dbUser, $dbPass] = ltecoMysqlEnv();
    putenv('MYSQL_PWD=' . $dbPass);

    $fecha = date('Y-m-d_H-i-s');
    $archivo = \Lteco\Domain\Mantenimiento\BackupComando::nombreBackup($fecha);
    $rutaBackup = $backupDir . DIRECTORY_SEPARATOR . $archivo;

    [$code, $stdout, $stderr] = ltecoRunCommandSeguro(
        \Lteco\Domain\Mantenimiento\BackupComando::comandoDump($dbHost, $dbUser, $dbName),
        null,
        $rutaBackup
    );

    putenv('MYSQL_PWD');

    if ($code !== 0) {
        @unlink($rutaBackup);
        throw new RuntimeException('mysqldump falló: ' . trim($stderr ?: $stdout));
    }
    if (!is_file($rutaBackup) || filesize($rutaBackup) <= 0) {
        @unlink($rutaBackup);
        throw new RuntimeException('mysqldump generó un archivo vacío.');
    }
    $dump = (string)file_get_contents($rutaBackup);
    $dump = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/i', '', $dump) ?? $dump;
    $dump = preg_replace('#/\*!50017\s+DEFINER=[^*]+ \*/#i', '', $dump) ?? $dump;
    file_put_contents($rutaBackup, $dump);
    $checksum = hash_file('sha256', $rutaBackup);
    if ($checksum === false) {
        @unlink($rutaBackup);
        throw new RuntimeException('No se pudo calcular checksum del backup.');
    }
    file_put_contents($rutaBackup . '.sha256', $checksum . '  ' . basename($rutaBackup) . PHP_EOL);

    @chmod($rutaBackup, 0640);
    @chmod($rutaBackup . '.sha256', 0640);

    registrarAuditoria(
        $pdo,
        'CREAR_BACKUP',
        'Mantenimiento',
        'Se generó un backup manual del sistema.',
        ['archivo' => $archivo, 'sha256' => $checksum]
    );

    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'success',
        'Backup generado correctamente.'
    );
} catch (Throwable $e) {
    putenv('MYSQL_PWD');
    logPanelError('backup_manual', $e);
    redirectWithFlash(
        panelBaseUrl('configuracion/mantenimiento/index.php'),
        'error',
        mensajeErrorSeguro($e, 'No se pudo generar el backup.')
    );
}
