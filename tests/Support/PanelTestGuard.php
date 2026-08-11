<?php

declare(strict_types=1);

final class PanelTestGuard
{
    public static function assertSafeForMutation(?string $dbName = null): void
    {
        $env = strtolower(trim((string) (getenv('LTECO_ENV') ?: getenv('APP_ENV') ?: '')));
        $allow = strtolower(trim((string) getenv('LTECO_TEST_DB_ALLOW')));
        $dbName ??= (string) getenv('LTECO_DB_NAME');
        $testUser = strtolower(trim((string) getenv('LTECO_TEST_DB_USER')));
        $dbUser = strtolower(trim((string) (getenv('LTECO_DB_USER') ?: getenv('LTECO_TEST_DB_USER'))));

        if (!in_array($allow, ['1', 'true', 'yes', 'on'], true)) {
            throw new RuntimeException('Refusing to run mutative test without LTECO_TEST_DB_ALLOW=1.');
        }

        if (!in_array($env, ['testing', 'test', 'ci'], true)) {
            throw new RuntimeException('Refusing to run mutative test outside testing env.');
        }

        if (!self::isTestDatabaseName($dbName)) {
            throw new RuntimeException('Refusing to run mutative test on non-test database.');
        }

        if ($testUser === '' || $dbUser === '' || $dbUser !== $testUser) {
            throw new RuntimeException('Refusing to run mutative test without dedicated test DB user.');
        }
    }

    public static function isTestDatabaseName(string $dbName): bool
    {
        $dbName = strtolower(trim($dbName));
        return $dbName !== ''
            && preg_match('/(^test_|_test$|_testing$)/', $dbName) === 1
            && !in_array($dbName, ['lteco_db', 'lteco_db_poo', 'production', 'prod'], true);
    }
}
