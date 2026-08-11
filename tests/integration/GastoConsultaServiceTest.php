<?php

declare(strict_types=1);

/**
 * Integración E1. Verifica el listado de gastos y sus filtros legacy
 * (estado activo, categoría, método, rango de fechas y orden) dentro de una
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

use Lteco\Application\Gasto\GastoConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\GastoConsultaRepository;

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
    // Espeja shared/db.php: prepares nativos (sin emulación), para no esconder
    // bugs de placeholders nombrados (HY093).
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new GastoConsultaService(new GastoConsultaRepository(new Connection($pdo)));

    // Ventana de fechas aislada (2099) para no colisionar con datos reales.
    $insert = $pdo->prepare("
        INSERT INTO gasto (FechaGasto, Concepto, Categoria, MetodoPago, Moneda, Monto, Estado)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $marca = 'ZZE1_' . bin2hex(random_bytes(4));
    // g1: Repuestos / Efectivo / 2099-01-10 / Activo
    $insert->execute(['2099-01-10', $marca . ' g1', 'Repuestos', 'Efectivo', 'UYU', 100.00, 'Activo']);
    // g2: Publicidad / Transferencia / 2099-03-20 / Activo
    $insert->execute(['2099-03-20', $marca . ' g2', 'Publicidad', 'Transferencia', 'USD', 50.00, 'Activo']);
    // g3: Repuestos / Efectivo / 2099-02-15 / Anulado (debe excluirse)
    $insert->execute(['2099-02-15', $marca . ' g3', 'Repuestos', 'Efectivo', 'UYU', 999.00, 'Anulado']);
    // g4: Repuestos / Tarjeta / 2098-12-01 / Activo (fuera de la ventana desde)
    $insert->execute(['2098-12-01', $marca . ' g4', 'Repuestos', 'Tarjeta', 'UYU', 10.00, 'Activo']);

    $ventana = ['categoria' => '', 'metodo' => '', 'desde' => '2099-01-01', 'hasta' => '2099-12-31'];

    // --- base: solo activos dentro de la ventana ---
    $base = $service->listar($ventana);
    $check('base devuelve solo activos en la ventana (g1, g2)', count($base) === 2);
    $check('base excluye el gasto anulado (g3)', !in_array($marca . ' g3', array_column($base, 'Concepto'), true));

    // --- orden por FechaGasto DESC, IdGasto DESC ---
    $check('orden DESC: g2 (03-20) antes que g1 (01-10)', $base[0]['Concepto'] === $marca . ' g2' && $base[1]['Concepto'] === $marca . ' g1');

    // --- columnas legacy: set exacto, sin Estado/TipoCambioAplicado/Descripcion ---
    $claves = array_keys($base[0]);
    sort($claves);
    $esperadas = ['Categoria', 'Concepto', 'FechaGasto', 'IdGasto', 'MetodoPago', 'Moneda', 'Monto', 'Observaciones'];
    sort($esperadas);
    $check('columnas devueltas = set legacy', $claves === $esperadas);

    // --- filtro categoría exacto ---
    $porCategoria = $service->listar(['categoria' => 'Repuestos', 'metodo' => '', 'desde' => '2099-01-01', 'hasta' => '2099-12-31']);
    $check('filtro categoría Repuestos deja solo g1 (g3 anulado fuera)', count($porCategoria) === 1 && $porCategoria[0]['Concepto'] === $marca . ' g1');

    // --- filtro método exacto ---
    $porMetodo = $service->listar(['categoria' => '', 'metodo' => 'Transferencia', 'desde' => '2099-01-01', 'hasta' => '2099-12-31']);
    $check('filtro método Transferencia deja solo g2', count($porMetodo) === 1 && $porMetodo[0]['Concepto'] === $marca . ' g2');

    // --- filtro desde (límite inferior inclusivo) ---
    $desde = $service->listar(['categoria' => '', 'metodo' => '', 'desde' => '2099-02-01', 'hasta' => '2099-12-31']);
    $check('filtro desde=2099-02-01 deja solo g2', count($desde) === 1 && $desde[0]['Concepto'] === $marca . ' g2');

    // --- filtro hasta (límite superior inclusivo) ---
    $hasta = $service->listar(['categoria' => '', 'metodo' => '', 'desde' => '2099-01-01', 'hasta' => '2099-01-31']);
    $check('filtro hasta=2099-01-31 deja solo g1', count($hasta) === 1 && $hasta[0]['Concepto'] === $marca . ' g1');

    // --- g4 (2098) nunca aparece con desde=2099 ---
    $check('gasto fuera de ventana (2098) excluido', !in_array($marca . ' g4', array_column($base, 'Concepto'), true));
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
