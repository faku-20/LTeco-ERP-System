<?php

declare(strict_types=1);

final class B5DbLifecycleWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $runtimeFiles = array_merge(
            glob($base . '/src/**/*.php', GLOB_BRACE) ?: [],
            glob($base . '/src/*/*/*/*.php') ?: [],
            glob($base . '/lteco-panel/**/*.php', GLOB_BRACE) ?: []
        );
        $offenders = [];
        foreach ($runtimeFiles as $file) {
            $relative = str_replace($base . '/', '', $file);
            if (str_starts_with($relative, 'lteco-panel/scripts/')) {
                continue;
            }
            $source = (string)file_get_contents($file);
            if (preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|CREATE\s+(UNIQUE\s+)?INDEX|DROP\s+TABLE|CREATE\s+DATABASE)\b/i', $source)) {
                $offenders[] = $relative;
            }
        }
        Assert::same('B5 DDL runtime', 'sin DDL en request/runtime normal', [], array_values(array_unique($offenders)));

        $runner = (string)file_get_contents($base . '/lteco-panel/scripts/panel_migrate.php');
        Assert::isTrue('B5 migrations', 'ledger schema_migrations', str_contains($runner, 'schema_migrations'));
        Assert::isTrue('B5 migrations', 'requiere allow production', str_contains($runner, '--allow-production'));
        Assert::isTrue('B5 migrations', 'soporta baseline existing', str_contains($runner, '--baseline-existing'));
        Assert::isTrue('B5 migrations', 'detecta destructivas', str_contains($runner, '--allow-destructive') && str_contains($runner, 'isDestructive'));
        Assert::isTrue('B5 migrations', 'baseline sin datos existe', is_file($base . '/database/baseline/2026_08_05_current_schema.sql'));

        $backupCli = (string)file_get_contents($base . '/lteco-panel/scripts/backup_cli.php');
        Assert::isTrue('B5 backup', 'backup usa parser PHP seguro', str_contains($backupCli, 'shared/app_config.php') && !str_contains($backupCli, 'source .env'));
        Assert::isTrue('B5 backup', 'backup genera checksum', str_contains($backupCli, 'hash_file') && str_contains($backupCli, '.sha256'));
        Assert::isTrue('B5 backup', 'backup valida gzip', str_contains($backupCli, "['gzip', '-t'"));
        Assert::isTrue('B5 backup', 'backup escribe estado estructurado', str_contains($backupCli, 'backup-status.json'));
        Assert::isTrue('B5 backup', 'retencion valida nombres', str_contains($backupCli, 'lteco_db_*.sql.gz') && str_contains($backupCli, 'preg_match'));

        $restore = (string)file_get_contents($base . '/lteco-panel/configuracion/mantenimiento/restore.php');
        Assert::isTrue('B5 restore', 'restore web productivo deshabilitado', str_contains($restore, 'appIsProduction()') && str_contains($restore, 'deshabilitado'));
        Assert::isTrue('B5 restore', 'restore valida checksum si existe', str_contains($restore, '.sha256') && str_contains($restore, 'hash_equals'));

        $preflight = (string)file_get_contents($base . '/lteco-panel/scripts/panel_preflight.php');
        Assert::isTrue('B5 preflight', 'valida DB', str_contains($preflight, 'db_connection'));
        Assert::isTrue('B5 preflight', 'valida schema migrations', str_contains($preflight, 'schema_migrations_ledger'));
        Assert::isTrue('B5 preflight', 'no aplica migraciones', !str_contains($preflight, 'panel_migrate'));
    }
}
