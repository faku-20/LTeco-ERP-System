<?php

declare(strict_types=1);

namespace Lteco\Domain\Mantenimiento;

/**
 * Lógica pura del mantenimiento de backups (F5/F6): construcción de nombres de
 * archivo, armado de los argumentos de mysqldump/mysql, guard de extensión
 * restaurable y content-type de descarga. Sin DB, sin shell, sin filesystem.
 *
 * NO reimplementa la validación de nombre ni la protección de path traversal:
 * esas siguen en los helpers auditados (ltecoBackupFilenameValido /
 * ltecoBackupPathSeguro). Esta clase solo encapsula decisiones puras para poder
 * testearlas sin ejecutar comandos destructivos.
 */
final class BackupComando
{
    /** Nombre del backup manual: backup_{fecha}.sql */
    public static function nombreBackup(string $fecha): string
    {
        return "backup_{$fecha}.sql";
    }

    /** Nombre del backup automático previo a una restauración. */
    public static function nombreBackupPreRestore(string $fecha): string
    {
        return "auto_pre_restore_{$fecha}.sql";
    }

    /**
     * Argumentos de mysqldump (idénticos al legacy). El password viaja por
     * MYSQL_PWD en el entorno, no como argumento.
     *
     * @return list<string>
     */
    public static function comandoDump(string $host, string $usuario, string $dbName): array
    {
        return ['mysqldump', '--ssl=0', '-h', $host, '-u', $usuario, $dbName];
    }

    /**
     * Argumentos de mysql para restaurar (idénticos al legacy).
     *
     * @return list<string>
     */
    public static function comandoRestore(string $host, string $usuario, string $dbName): array
    {
        return ['mysql', '--ssl=0', '-h', $host, '-u', $usuario, $dbName];
    }

    /**
     * Solo los .sql se pueden restaurar desde el panel (los .sql.gz quedan para
     * descarga).
     */
    public static function esRestaurable(string $file): bool
    {
        return str_ends_with($file, '.sql');
    }

    /** Content-Type de descarga según extensión. */
    public static function contentTypeDescarga(string $file): string
    {
        return str_ends_with($file, '.gz') ? 'application/gzip' : 'application/sql';
    }
}
