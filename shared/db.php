<?php
require_once __DIR__ . '/app_config.php';

$host = configEnv('LTECO_DB_HOST', 'localhost');
$dbname = configEnv('LTECO_DB_NAME', 'lteco_db');
$user = configEnv('LTECO_DB_USER', 'root');
$pass = configEnv('LTECO_DB_PASS', '');

$scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
$isPublicWeb = str_contains($scriptPath, '/public-web/');
$publicUser = trim((string)configEnv('LTECO_PUBLIC_DB_USER', ''));

if ($isPublicWeb && $publicUser !== '') {
    $user = $publicUser;
    $pass = (string)configEnv('LTECO_PUBLIC_DB_PASS', '');
}

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$hosts = [$host];
if ($host === 'host.docker.internal') {
    $hostDockerResuelve = gethostbyname($host) !== $host;
    $hosts = $hostDockerResuelve
        ? [$host, '127.0.0.1', 'localhost']
        : ['127.0.0.1', 'localhost', $host];
}

$ultimoError = null;
foreach (array_unique($hosts) as $hostIntento) {
    try {
        $pdo = new PDO(
            "mysql:host={$hostIntento};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            $pdoOptions
        );
        $ultimoError = null;
        break;
    } catch (PDOException $e) {
        $ultimoError = $e;
    }
}

if ($ultimoError instanceof PDOException) {
    error_log('[DB] ' . $ultimoError->getMessage());
    if (defined('LTECO_DB_THROW_ON_CONNECT') && LTECO_DB_THROW_ON_CONNECT === true) {
        throw $ultimoError;
    }
    http_response_code(500);
    exit(appDebug() ? 'Error de conexión: ' . $ultimoError->getMessage() : 'Error de conexión a la base de datos.');
}
