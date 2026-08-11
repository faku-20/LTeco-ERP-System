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

use Lteco\Application\Dashboard\DashboardService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\DashboardRepository;

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
    $service = new DashboardService(new DashboardRepository(new Connection($pdo)));
    $antes = $service->cargar(40, true, date('Y-m'));

    $sufijo = bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO cliente (NombreApellido, Telefono) VALUES (?, ?)")
        ->execute(['Dashboard G3 ' . $sufijo, '097' . substr($sufijo, 0, 6)]);
    $clienteId = (int) $pdo->lastInsertId();
    $pdo->prepare("
        INSERT INTO venta
            (Cliente_IdCliente, FechaVenta, Total, Moneda, GananciaEstimada,
             MontoPagado, SaldoPendiente, TipoCambioAplicado, EstadoVenta)
        VALUES (?, NOW(), 1000, 'UYU', 300, 400, 600, 0, 'Pendiente')
    ")->execute([$clienteId]);

    $despues = $service->cargar(40, true, date('Y-m'));
    $check('carga claves usadas por la vista', isset(
        $despues['resumen'],
        $despues['inventario'],
        $despues['clientesConDeuda'],
        $despues['topClientes'],
        $despues['ultimasVentas']
    ));
    $check(
        'venta pendiente incrementa activas',
        $despues['resumen']['ventas_activas'] === $antes['resumen']['ventas_activas'] + 1
    );
    $check(
        'venta pendiente incrementa pendientes',
        $despues['resumen']['ventas_pendientes'] === $antes['resumen']['ventas_pendientes'] + 1
    );
    $check(
        'saldo se convierte y acumula',
        abs(($despues['resumen']['saldo_pendiente_uyu'] - $antes['resumen']['saldo_pendiente_uyu']) - 600) < 0.01
    );
    $check(
        'cliente con deuda incrementa conteo',
        $despues['clientesConDeuda'] === $antes['clientesConDeuda'] + 1
    );
    $check('últimas ventas limita a ocho', count($despues['ultimasVentas']) <= 8);
    $check('inventario expone métricas visibles', isset(
        $despues['inventario']['motos_disponibles'],
        $despues['inventario']['repuestos_unidades'],
        $despues['inventario']['repuestos_stock_bajo']
    ));
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
