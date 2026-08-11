<?php

declare(strict_types=1);

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

use Lteco\Application\Busqueda\BusquedaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\BusquedaRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) { $ok++; echo "  ✓ {$nombre}\n"; return; }
    $fallos[] = $nombre;
    echo "  ✗ {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . (getenv('LTECO_DB_NAME') ?: 'lteco_db') . ';charset=utf8mb4',
    getenv('LTECO_DB_USER') ?: 'lteco_user',
    getenv('LTECO_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new BusquedaService(new BusquedaRepository(new Connection($pdo)));
    $recientes = $service->consultar('', 0, false);
    $check('vacío devuelve cuatro grupos recientes', count($recientes['recientes']) === 4);
    $check('recientes limita cada grupo a cuatro', max(array_map('count', $recientes['recientes'])) <= 4);

    $sufijo = bin2hex(random_bytes(4));
    $vendedorId = (int) $pdo->query("SELECT IdUsuario FROM usuario ORDER BY IdUsuario ASC LIMIT 1")->fetchColumn();
    $insertCliente = $pdo->prepare("INSERT INTO cliente (NombreApellido, Telefono, Correo) VALUES (?, ?, ?)");
    $insertCliente->execute(['Busqueda Propio ' . $sufijo, '099' . substr($sufijo, 0, 6), 'propio_' . $sufijo . '@example.com']);
    $clientePropio = (int) $pdo->lastInsertId();
    $insertCliente->execute(['Busqueda Ajeno ' . $sufijo, '098' . substr($sufijo, 0, 6), 'ajeno_' . $sufijo . '@example.com']);

    $factura = 'G2-' . strtoupper($sufijo);
    $pdo->prepare("
        INSERT INTO venta
            (Cliente_IdCliente, NumeroFactura, FechaVenta, Total, Moneda, EstadoVenta, MetodoPago, UsuarioVendedorId)
        VALUES (?, ?, NOW(), 1234, 'UYU', 'Confirmada', 'Efectivo', ?)
    ")->execute([$clientePropio, $factura, $vendedorId]);
    $ventaId = (int) $pdo->lastInsertId();

    $admin = $service->consultar('Busqueda Propio ' . $sufijo, 0, false);
    $check('admin encuentra cliente', count($admin['resultados']['clientes']) === 1);
    $check('detalle contiene ventas activas', count($admin['clientesConDetalle'][$clientePropio]['ventas'] ?? []) === 1);

    $venta = $service->consultar($factura, $vendedorId, true);
    $idsVenta = array_map(static fn(array $fila): int => (int) $fila['IdVenta'], $venta['resultados']['ventas']);
    $check('vendedor encuentra su venta por factura', in_array($ventaId, $idsVenta, true));

    $ajeno = $service->consultar('Busqueda Ajeno ' . $sufijo, $vendedorId, true);
    $check('vendedor no recibe cliente ajeno', $ajeno['resultados']['clientes'] === []);
    $check('vendedor recibe aviso de coincidencia ajena', $ajeno['clienteAjenoCoincide'] === true);

    $texto = $service->consultar('<script>', 0, false);
    $check('texto especial no rompe y devuelve estructura', isset($texto['resultados']['vehiculos'], $texto['clientesConDetalle']));
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n[ROLLBACK ejecutado]\n";
    }
}

if ($fallos) {
    fwrite(STDERR, "\nFALLÓ — " . implode(', ', $fallos) . "\n");
    exit(1);
}
echo "\nOK — {$ok} aserciones de integración pasaron.\n";
