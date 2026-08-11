<?php

declare(strict_types=1);

/**
 * Integración D5. Verifica el listado para exportación CSV de clientes
 * (incluye FechaCliente y no aplica alcance por vendedor) dentro de una
 * transacción que siempre termina con rollback.
 */

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

use Lteco\Application\Cliente\ClienteConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\ClienteConsultaRepository;
use Lteco\Infrastructure\Repository\ClienteCrudRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  ✓ {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  ✗ {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . (getenv('LTECO_DB_NAME') ?: 'lteco_db') . ';charset=utf8mb4',
    getenv('LTECO_DB_USER') ?: 'lteco_user',
    getenv('LTECO_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $connection = new Connection($pdo);
    $service = new ClienteConsultaService(new ClienteConsultaRepository($connection));
    $crud = new ClienteCrudRepository($connection);

    $vendedorId = (int) $pdo->query("SELECT IdUsuario FROM usuario ORDER BY IdUsuario ASC LIMIT 1")->fetchColumn();
    $sufijo = bin2hex(random_bytes(4));
    $nombre = 'ExportD5 ' . $sufijo;
    $idCliente = $crud->crear([
        'nombre_apellido' => $nombre, 'telefono' => null, 'correo' => null,
        'tipo_fiscal' => 'Consumidor final', 'cedula' => null, 'direccion' => null, 'rut' => null,
    ]);

    $ins = $pdo->prepare("INSERT INTO venta (Cliente_IdCliente, Total, Moneda, SaldoPendiente, EstadoVenta, FechaVenta, UsuarioVendedorId)
        VALUES (?, 1500.0, 'UYU', 100.0, 'Confirmada', NOW(), ?)");
    $ins->execute([$idCliente, $vendedorId]);

    $rows = $service->listarParaExport($nombre, 40.0);
    $fila = null;
    foreach ($rows as $r) {
        if ((int) $r['IdCliente'] === $idCliente) {
            $fila = $r;
            break;
        }
    }

    $check('exportación encuentra el cliente', $fila !== null);
    $check('exportación incluye FechaCliente', $fila !== null && array_key_exists('FechaCliente', $fila));
    $check('exportación calcula total gastado', $fila !== null && abs((float) $fila['TotalGastadoUYU'] - 1500.0) < 0.01);
    $check('exportación cuenta compras activas', $fila !== null && (int) $fila['Compras'] === 1);
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado]\n";
}

if ($fallos === []) {
    echo "\nOK — {$ok} aserciones de integración pasaron.\n";
    exit(0);
}
echo "\nFALLÓ — " . count($fallos) . " aserciones.\n";
exit(1);
