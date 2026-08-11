<?php

declare(strict_types=1);

require_once __DIR__ . '/PanelTestGuard.php';

final class PanelTestDb
{
    public static function connect(): PDO
    {
        $host = (string) (getenv('LTECO_TEST_DB_HOST') ?: getenv('LTECO_DB_HOST') ?: '127.0.0.1');
        $host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
        $db = (string) (getenv('LTECO_TEST_DB_NAME') ?: getenv('LTECO_DB_NAME') ?: '');
        $user = (string) (getenv('LTECO_TEST_DB_USER') ?: getenv('LTECO_DB_USER') ?: '');
        $pass = (string) (getenv('LTECO_TEST_DB_PASSWORD') ?: getenv('LTECO_TEST_DB_PASSWOR') ?: getenv('LTECO_DB_PASS') ?: '');

        putenv('LTECO_DB_NAME=' . $db);
        putenv('LTECO_DB_USER=' . $user);

        PanelTestGuard::assertSafeForMutation($db);

        return new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
