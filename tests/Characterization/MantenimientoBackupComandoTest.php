<?php

declare(strict_types=1);

use Lteco\Domain\Mantenimiento\BackupComando;

/**
 * Unit F5/F6: lógica pura de backup/restore (nombres, comandos, guard de
 * extensión, content-type). Sin DB ni shell: nunca ejecuta comandos reales.
 */
final class MantenimientoBackupComandoTest
{
    public static function run(): void
    {
        $caso = 'BackupComando';

        // --- nombres de archivo ---
        Assert::same($caso, 'nombreBackup', 'backup_2099-01-02_03-04-05.sql', BackupComando::nombreBackup('2099-01-02_03-04-05'));
        Assert::same($caso, 'nombreBackupPreRestore', 'auto_pre_restore_2099-01-02_03-04-05.sql', BackupComando::nombreBackupPreRestore('2099-01-02_03-04-05'));

        // --- argumentos de comando (idénticos al legacy, sin password) ---
        Assert::same($caso, 'comandoDump', ['mysqldump', '--ssl=0', '-h', 'dbhost', '-u', 'dbuser', 'dbname'], BackupComando::comandoDump('dbhost', 'dbuser', 'dbname'));
        Assert::same($caso, 'comandoRestore', ['mysql', '--ssl=0', '-h', 'dbhost', '-u', 'dbuser', 'dbname'], BackupComando::comandoRestore('dbhost', 'dbuser', 'dbname'));
        Assert::isFalse($caso, 'comandoDump no incluye password', in_array('MYSQL_PWD', BackupComando::comandoDump('h', 'u', 'd'), true));

        // --- guard de restaurable: solo .sql ---
        Assert::isTrue($caso, '.sql es restaurable', BackupComando::esRestaurable('backup_2099-01-01_00-00-00.sql'));
        Assert::isFalse($caso, '.sql.gz NO es restaurable', BackupComando::esRestaurable('lteco_db_2099-01-01_00-00-00.sql.gz'));
        Assert::isFalse($caso, '.gz NO es restaurable', BackupComando::esRestaurable('algo.gz'));

        // --- content-type de descarga ---
        Assert::same($caso, 'content-type gz', 'application/gzip', BackupComando::contentTypeDescarga('x.sql.gz'));
        Assert::same($caso, 'content-type sql', 'application/sql', BackupComando::contentTypeDescarga('x.sql'));
    }
}
