<?php

declare(strict_types=1);

/**
 * Wiring F5/F6: los handlers de mantenimiento usan el dominio BackupComando para
 * la lógica pura y PRESERVAN la seguridad (CSRF, guard superadmin, protección de
 * path traversal y backup automático previo a restaurar). No se ejecuta nada
 * destructivo aquí: solo se inspecciona el código fuente.
 */
final class MantenimientoWiringTest
{
    public static function run(): void
    {
        $mant = dirname(__DIR__, 2) . '/lteco-panel/configuracion/mantenimiento/';

        // --- backup.php ---
        $backup = (string) @file_get_contents($mant . 'backup.php');
        Assert::isTrue('Wiring mantenimiento (backup)', 'usa BackupComando', strpos($backup, 'BackupComando') !== false);
        Assert::same('Wiring mantenimiento (backup)', 'arma el comando dump por dominio', 1, substr_count($backup, 'BackupComando::comandoDump('));
        Assert::isTrue('Wiring mantenimiento (backup)', 'conserva guard superadmin', strpos($backup, 'requiereSuperadmin()') !== false);
        Assert::isTrue('Wiring mantenimiento (backup)', 'conserva CSRF', strpos($backup, 'verifyCsrfOrFail()') !== false);
        Assert::isTrue('Wiring mantenimiento (backup)', 'sigue usando el runner seguro', strpos($backup, 'ltecoRunCommandSeguro(') !== false);

        // --- restore.php (destructivo: máxima cautela) ---
        $restore = (string) @file_get_contents($mant . 'restore.php');
        Assert::isTrue('Wiring mantenimiento (restore)', 'usa BackupComando', strpos($restore, 'BackupComando') !== false);
        Assert::same('Wiring mantenimiento (restore)', 'valida extensión restaurable por dominio', 1, substr_count($restore, 'BackupComando::esRestaurable('));
        Assert::same('Wiring mantenimiento (restore)', 'arma el comando restore por dominio', 1, substr_count($restore, 'BackupComando::comandoRestore('));
        Assert::isTrue('Wiring mantenimiento (restore)', 'conserva guard superadmin', strpos($restore, 'requiereSuperadmin()') !== false);
        Assert::isTrue('Wiring mantenimiento (restore)', 'conserva CSRF', strpos($restore, 'verifyCsrfOrFail()') !== false);
        Assert::isTrue('Wiring mantenimiento (restore)', 'conserva protección de path traversal', strpos($restore, 'ltecoBackupPathSeguro(') !== false);
        Assert::isTrue('Wiring mantenimiento (restore)', 'conserva backup automático previo', strpos($restore, 'nombreBackupPreRestore(') !== false);

        // --- download.php ---
        $download = (string) @file_get_contents($mant . 'download.php');
        Assert::isTrue('Wiring mantenimiento (download)', 'usa BackupComando para content-type', strpos($download, 'BackupComando::contentTypeDescarga(') !== false);
        Assert::isTrue('Wiring mantenimiento (download)', 'conserva guard superadmin', strpos($download, 'requiereSuperadmin()') !== false);
        Assert::isTrue('Wiring mantenimiento (download)', 'conserva protección de path traversal', strpos($download, 'ltecoBackupPathSeguro(') !== false);

        // --- whatsapp_probar.php ---
        $wa = (string) @file_get_contents($mant . 'whatsapp_probar.php');
        Assert::isTrue('Wiring mantenimiento (whatsapp_probar)', 'usa ConfiguracionService', strpos($wa, 'ConfiguracionService') !== false);
        Assert::same('Wiring mantenimiento (whatsapp_probar)', 'delega lectura de contacto', 1, substr_count($wa, '->obtenerEmpresaContacto('));
        Assert::same('Wiring mantenimiento (whatsapp_probar)', 'sin SQL inline', 0, preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $wa));
        Assert::isTrue('Wiring mantenimiento (whatsapp_probar)', 'conserva guard superadmin', strpos($wa, 'requiereSuperadmin()') !== false);
        Assert::isTrue('Wiring mantenimiento (whatsapp_probar)', 'conserva CSRF', strpos($wa, 'verifyCsrfOrFail()') !== false);

        // --- index.php (health-check de MariaDB movido fuera de la vista) ---
        $index = (string) @file_get_contents($mant . 'index.php');
        Assert::isTrue('Wiring mantenimiento (index)', 'usa EstadoSistemaService', strpos($index, 'EstadoSistemaService') !== false);
        Assert::same('Wiring mantenimiento (index)', 'delega el ping de MariaDB', 1, substr_count($index, '->mariadbOnline('));
        Assert::same('Wiring mantenimiento (index)', 'sin SQL inline', 0, preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $index));
        Assert::isTrue('Wiring mantenimiento (index)', 'conserva guard superadmin', strpos($index, 'requiereSuperadmin()') !== false);

        // --- repo/service del ping: el SQL vive en el repositorio, no en la vista ---
        $src = dirname(__DIR__, 2) . '/src';
        Assert::isTrue('Wiring mantenimiento (clases)', 'existe MantenimientoRepository', class_exists(\Lteco\Infrastructure\Repository\MantenimientoRepository::class));
        Assert::isTrue('Wiring mantenimiento (clases)', 'existe EstadoSistemaService', class_exists(\Lteco\Application\Mantenimiento\EstadoSistemaService::class));
        Assert::isTrue('Wiring mantenimiento (clases)', 'repo expone baseDeDatosResponde', method_exists(\Lteco\Infrastructure\Repository\MantenimientoRepository::class, 'baseDeDatosResponde'));
        Assert::isTrue('Wiring mantenimiento (clases)', 'service expone mariadbOnline', method_exists(\Lteco\Application\Mantenimiento\EstadoSistemaService::class, 'mariadbOnline'));

        $repo = (string) @file_get_contents($src . '/Infrastructure/Repository/MantenimientoRepository.php');
        Assert::isTrue('Wiring mantenimiento (repo)', 'el repo ejecuta el ping SELECT 1', strpos($repo, 'SELECT 1') !== false);
        Assert::isTrue('Wiring mantenimiento (repo)', 'traduce Throwable a offline', strpos($repo, 'catch (Throwable') !== false);
    }
}
