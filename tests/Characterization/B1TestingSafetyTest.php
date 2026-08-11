<?php

declare(strict_types=1);

final class B1TestingSafetyTest
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        $guard = (string) file_get_contents($root . '/tests/Support/PanelTestGuard.php');
        $init = is_file($root . '/scripts/init-panel-test-db.sh') ? (string) file_get_contents($root . '/scripts/init-panel-test-db.sh') : '';
        $panel = is_file($root . '/scripts/test-panel-fast.sh') ? (string) file_get_contents($root . '/scripts/test-panel-fast.sh') : '';
        $critical = is_file($root . '/scripts/test-critical.sh') ? (string) file_get_contents($root . '/scripts/test-critical.sh') : '';
        $cleanup = (string) file_get_contents($root . '/lteco-panel/scripts/cleanup_test_data.php');

        Assert::isTrue('B1 safety', 'guard exige flag dedicado', str_contains($guard, 'LTECO_TEST_DB_ALLOW'));
        Assert::isTrue('B1 safety', 'guard exige entorno testing', str_contains($guard, "['testing', 'test', 'ci']"));
        Assert::isTrue('B1 safety', 'guard exige nombre DB test', str_contains($guard, '_test$'));
        Assert::isTrue('B1 safety', 'guard exige usuario DB test dedicado', str_contains($guard, 'dedicated test DB user'));
        if ($init !== '') {
            Assert::isTrue('B1 safety', 'init usa schema sin datos', str_contains($init, '--no-data'));
            Assert::isTrue('B1 safety', 'init fuerza DB test por defecto', str_contains($init, 'lteco_db_poo_test'));
            Assert::isFalse('B1 safety', 'init no exige credenciales admin en reset normal', str_contains($init, 'LTECO_TEST_DB_ADMIN_USER'));
            Assert::isTrue('B1 safety', 'init usa usuario test dedicado para reset', str_contains($init, 'TEST_USER="${LTECO_TEST_DB_USER}"'));
        }
        if ($panel !== '') {
            Assert::isTrue('B1 safety', 'panel runner usa contenedor panel', str_contains($panel, 'docker compose exec -T panel'));
        }
        if ($critical !== '') {
            Assert::isTrue('B1 safety', 'critical runner exporta env testing', str_contains($critical, 'LTECO_ENV=testing'));
        }
        Assert::isTrue('B1 safety', 'cleanup script usa guard reutilizable', str_contains($cleanup, 'PanelTestGuard::assertSafeForMutation'));
    }
}
