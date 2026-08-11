<?php

declare(strict_types=1);

use Lteco\Application\Operations\OperationalHealthService;

final class B6OperationalHealthTest
{
    public static function run(): void
    {
        $root = sys_get_temp_dir() . '/lteco-b6-' . bin2hex(random_bytes(4));
        mkdir($root . '/lteco-panel', 0775, true);
        mkdir($root . '/state', 0775, true);
        $storefront = $root . '/storefront.html';
        file_put_contents($storefront, 'ok');
        $backup = $root . '/backup.sql.gz';
        file_put_contents($backup, 'backup');
        $status = $root . '/backup-status.json';
        $heartbeat = $root . '/state/ecommerce-heartbeat.json';

        $pdo = self::sqliteInventoryDb(false);
        self::writeJson($heartbeat, ['ok' => true, 'timestamp' => time()]);
        self::writeJson($status, ['ok' => true, 'file' => $backup, 'timestamp' => time()]);
        $service = self::service($pdo, $root, $status, $heartbeat, 'file://' . $storefront);
        $healthy = $service->evaluate();
        Assert::isTrue('B6 health', 'heartbeat sano', $healthy['checks']['worker_heartbeat']['ok']);
        Assert::isTrue('B6 health', 'backup sano', $healthy['checks']['backup']['ok']);
        Assert::isTrue('B6 health', 'reconciliacion sana', $healthy['checks']['inventory_reconciliation']['ok']);
        Assert::isTrue('B6 health', 'exit code sano deriva de ok=true', $healthy['ok']);

        self::writeJson($heartbeat, ['ok' => true, 'timestamp' => time() - 600]);
        $staleHeartbeat = $service->evaluate();
        Assert::isFalse('B6 health', 'heartbeat vencido', $staleHeartbeat['checks']['worker_heartbeat']['ok']);

        self::writeJson($heartbeat, ['ok' => true, 'timestamp' => time()]);
        self::writeJson($status, ['ok' => false, 'file' => $backup, 'timestamp' => time()]);
        $failedBackup = $service->evaluate();
        Assert::isFalse('B6 health', 'backup fallido', $failedBackup['checks']['backup']['ok']);

        self::writeJson($status, ['ok' => true, 'file' => $backup, 'timestamp' => time() - 200000]);
        $staleBackup = $service->evaluate();
        Assert::isFalse('B6 health', 'backup vencido', $staleBackup['checks']['backup']['ok']);

        self::writeJson($status, ['ok' => true, 'file' => $backup, 'timestamp' => time()]);
        $badInventory = self::service(self::sqliteInventoryDb(true), $root, $status, $heartbeat, 'file://' . $storefront)->evaluate();
        Assert::isFalse('B6 health', 'reconciliacion inconsistente', $badInventory['checks']['inventory_reconciliation']['ok']);
        Assert::isFalse('B6 health', 'exit code falla deriva de ok=false', $badInventory['ok']);

        $sent = 0;
        $alertService = self::service(self::sqliteInventoryDb(true), $root, $status, $heartbeat, 'file://' . $storefront);
        $alertService->alertOnTransition($badInventory, static function () use (&$sent): bool {
            $sent++;
            return true;
        });
        $alertService->alertOnTransition($badInventory, static function () use (&$sent): bool {
            $sent++;
            return true;
        });
        Assert::same('B6 health', 'deduplicacion de alertas', 1, $sent);

        $script = (string)file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/scripts/operational_health.php');
        Assert::isTrue('B6 health', 'CLI usa exit code segun estado', str_contains($script, "exit(\$result['ok'] ? 0 : 1);"));

        self::removeTree($root);
    }

    private static function service(PDO $pdo, string $root, string $status, string $heartbeat, string $storefront): OperationalHealthService
    {
        return new OperationalHealthService($pdo, $root, $status, $heartbeat, $root . '/state', 180, 93600, 1, 3600, $storefront);
    }

    private static function sqliteInventoryDb(bool $inconsistent): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('CONCAT', static fn (...$parts): string => implode('', array_map('strval', $parts)));
        $pdo->exec('CREATE TABLE vehiculo (IdVehiculo TEXT, IdProducto INTEGER, ClienteReservaId INTEGER NULL)');
        $pdo->exec('CREATE TABLE producto (IdProducto INTEGER, TipoProducto TEXT, Estado TEXT, Stock INTEGER)');
        $pdo->exec('CREATE TABLE venta (IdVenta INTEGER, EstadoVenta TEXT NULL)');
        $pdo->exec('CREATE TABLE venta_detalle (Venta_IdVenta INTEGER, Producto_IdProducto INTEGER)');
        $pdo->exec('CREATE TABLE storefront_reservation (ReservationId INTEGER, Estado TEXT)');
        $pdo->exec('CREATE TABLE storefront_reservation_item (ReservationId INTEGER, IdVehiculo TEXT)');
        $pdo->exec('CREATE TABLE distribuidor_stock (IdStock INTEGER, Cantidad INTEGER, TipoItem TEXT, IdDistribuidor INTEGER, IdVehiculo TEXT, IdRepuesto INTEGER)');
        $pdo->exec('CREATE TABLE repuesto (IdRepuesto INTEGER, IdProducto INTEGER)');
        $pdo->exec('CREATE TABLE repuesto_caja_item (IdCajaItem INTEGER, IdRepuesto INTEGER, Cantidad INTEGER)');
        if ($inconsistent) {
            $pdo->exec("INSERT INTO producto (IdProducto,TipoProducto,Estado,Stock) VALUES (1,'Moto','Vendido',1)");
            $pdo->exec("INSERT INTO vehiculo (IdVehiculo,IdProducto,ClienteReservaId) VALUES ('B6-1',1,NULL)");
        }
        return $pdo;
    }

    /** @param array<string,mixed> $data */
    private static function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $full = $path . '/' . $entry;
            is_dir($full) ? self::removeTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
