<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/app_config.php';

final class PanelMigrationRunner
{
    private PDO $pdo;
    private string $root;
    private bool $dryRun;
    private bool $listOnly;
    private bool $allowProduction;
    private bool $baselineExisting;
    private bool $allowDestructive;
    private string $host;
    private string $name;
    private string $user;
    private string $pass;

    public function __construct(array $argv)
    {
        $this->root = dirname(__DIR__, 2);
        $this->dryRun = in_array('--dry-run', $argv, true);
        $this->listOnly = in_array('--list', $argv, true);
        $this->allowProduction = in_array('--allow-production', $argv, true);
        $this->baselineExisting = in_array('--baseline-existing', $argv, true);
        $this->allowDestructive = in_array('--allow-destructive', $argv, true);

        $env = appEnv();
        if ($env === 'production' && !$this->allowProduction && !$this->listOnly) {
            throw new RuntimeException('Producción requiere --allow-production.');
        }

        $this->host = (string)configEnv('LTECO_DB_HOST', 'host.docker.internal');
        $this->name = (string)configEnv('LTECO_DB_NAME', '');
        if ($env === 'production') {
            $this->user = (string)configEnv(
                'LTECO_DB_MIGRATOR_USER',
                (string)configEnv('LTECO_DB_USER', '')
            );
            $this->pass = (string)configEnv(
                'LTECO_DB_MIGRATOR_PASS',
                (string)configEnv(
                    'LTECO_DB_PASS',
                    (string)configEnv('LTECO_DB_PASSWORD', '')
                )
            );
        } else {
            // En testing/desarrollo se respetan las credenciales propias
            // del entorno. El migrator productivo nunca accede a la DB test.
            $this->user = (string)configEnv('LTECO_DB_USER', '');
            $this->pass = (string)configEnv(
                'LTECO_DB_PASS',
                (string)configEnv('LTECO_DB_PASSWORD', '')
            );
        }

        if ($this->name === '' || $this->user === '') {
            throw new RuntimeException(
                'Faltan credenciales de base de datos para migraciones.'
            );
        }

        $this->pdo = new PDO(
            "mysql:host={$this->host};dbname={$this->name};charset=utf8mb4",
            $this->user,
            $this->pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public function run(): void
    {
        $this->ensureLedger();
        $empty = $this->isApplicationSchemaEmpty();
        $hasBaseline = $this->hasMigration('baseline:2026_08_05_current_schema.sql');

        if ($empty && !$hasBaseline) {
            $this->applyBaseline();
            $this->markHistoricalMigrations();
        } elseif (!$empty && !$hasBaseline) {
            if (!$this->baselineExisting && !$this->listOnly) {
                throw new RuntimeException('DB existente sin ledger. Ejecutá con --baseline-existing para registrar baseline sin reejecutar schema histórico.');
            }
            if ($this->baselineExisting) {
                $this->recordMigration('baseline:2026_08_05_current_schema.sql', $this->checksum($this->baselinePath()), 'baseline-existing');
                $this->markHistoricalMigrations();
            }
        }

        $pending = $this->pendingMigrations();
        if ($this->listOnly || $this->dryRun) {
            foreach ($pending as $path) {
                echo ($this->isDestructive($path) ? 'DESTRUCTIVE ' : 'PENDING ') . basename($path) . PHP_EOL;
            }
            if ($pending === []) {
                echo "No pending migrations.\n";
            }
            return;
        }

        foreach ($pending as $path) {
            if ($this->isDestructive($path) && !$this->allowDestructive) {
                throw new RuntimeException('Migración destructiva requiere --allow-destructive: ' . basename($path));
            }
            $this->applySqlFile($path, basename($path));
        }
    }

    private function ensureLedger(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(190) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'applied',
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function isApplicationSchemaEmpty(): bool
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME <> 'schema_migrations'
        ");
        return (int)$stmt->fetchColumn() === 0;
    }

    private function applyBaseline(): void
    {
        $this->applySqlFile($this->baselinePath(), 'baseline:2026_08_05_current_schema.sql');
    }

    private function markHistoricalMigrations(): void
    {
        foreach ($this->migrationFiles() as $path) {
            if (basename($path) < '2026_08_05_000000_b5_runtime_schema.sql') {
                $this->recordMigration(basename($path), $this->checksum($path), 'covered-by-baseline');
            }
        }
    }

    /** @return list<string> */
    private function pendingMigrations(): array
    {
        $pending = [];
        foreach ($this->migrationFiles() as $path) {
            $name = basename($path);
            if (!$this->hasMigration($name)) {
                $pending[] = $path;
            }
        }
        return $pending;
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        $files = glob($this->root . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        return array_values($files);
    }

    private function baselinePath(): string
    {
        return $this->root . '/database/baseline/2026_08_05_current_schema.sql';
    }

    private function applySqlFile(string $path, string $migrationName): void
    {
        if (!is_file($path) || filesize($path) <= 0) {
            throw new RuntimeException('Migración vacía: ' . $migrationName);
        }
        $code = $this->runMysqlFile($path, $stderr);
        if ($code !== 0) {
            throw new RuntimeException('mysql falló aplicando ' . $migrationName . ': ' . trim($stderr));
        }
        $this->recordMigration($migrationName, $this->checksum($path), 'applied');
        echo "Applied {$migrationName}\n";
    }

    private function runMysqlFile(string $path, ?string &$stderr): int
    {
        $descriptors = [
            0 => ['file', $path, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = $_ENV;
        $env['MYSQL_PWD'] = $this->pass;
        $process = proc_open(['mysql', '--ssl=0', '-h', $this->host, '-u', $this->user, $this->name], $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('No se pudo ejecutar mysql.');
        }
        if (isset($pipes[1])) {
            fclose($pipes[1]);
        }
        $stderr = isset($pipes[2]) ? (stream_get_contents($pipes[2]) ?: '') : '';
        if (isset($pipes[2])) {
            fclose($pipes[2]);
        }
        return proc_close($process);
    }

    private function recordMigration(string $migration, string $checksum, string $status): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO schema_migrations (migration, checksum, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = VALUES(status)
        ");
        $stmt->execute([$migration, $checksum, $status]);
    }

    private function hasMigration(string $migration): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = ?');
        $stmt->execute([$migration]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function checksum(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('No se pudo calcular checksum: ' . $path);
        }
        return $hash;
    }

    private function isDestructive(string $path): bool
    {
        $sql = (string)file_get_contents($path);
        return preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM|RENAME\s+TABLE|MODIFY\s+COLUMN|CHANGE\s+COLUMN)\b/i', $sql) === 1;
    }
}

try {
    (new PanelMigrationRunner($argv))->run();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
