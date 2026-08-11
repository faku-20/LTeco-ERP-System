<?php

declare(strict_types=1);

/**
 * Integración E2. Verifica alta, lectura y edición de gastos preservando el
 * comportamiento legacy (Descripcion = NULL, TipoCambioAplicado fijado al alta
 * y NO tocado en la edición), dentro de una transacción con rollback.
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

use Lteco\Application\Gasto\GastoCrudService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\GastoCrudRepository;

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
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new GastoCrudService(new GastoCrudRepository(new Connection($pdo)));

    $marca = 'ZZE2_' . bin2hex(random_bytes(4));

    // --- alta ---
    $id = $service->crear([
        'fecha_gasto' => '2099-05-01',
        'concepto' => $marca . ' alta',
        'categoria' => 'Repuestos',
        'metodo_pago' => 'Efectivo',
        'moneda' => 'USD',
        'monto' => 123.45,
        'observaciones' => 'obs inicial',
        'tipo_cambio_aplicado' => 41.50,
    ]);
    $check('crear devuelve un IdGasto > 0', $id > 0);

    $g = $service->obtener($id);
    $check('obtener trae el gasto recién creado', is_array($g) && (int) $g['IdGasto'] === $id);
    $check('alta persiste concepto/categoria/metodo/moneda', $g['Concepto'] === $marca . ' alta' && $g['Categoria'] === 'Repuestos' && $g['MetodoPago'] === 'Efectivo' && $g['Moneda'] === 'USD');
    $check('alta persiste monto', (float) $g['Monto'] === 123.45);
    $check('alta persiste observaciones', $g['Observaciones'] === 'obs inicial');
    $check('alta fija TipoCambioAplicado', (float) $g['TipoCambioAplicado'] === 41.50);
    $check('alta deja Descripcion en NULL', $g['Descripcion'] === null);

    // --- edición: cambia datos pero NO toca TipoCambioAplicado ---
    $service->editar($id, [
        'fecha_gasto' => '2099-06-15',
        'concepto' => $marca . ' editado',
        'categoria' => 'Publicidad',
        'metodo_pago' => 'Transferencia',
        'moneda' => 'UYU',
        'monto' => 999.99,
        'observaciones' => null,
    ]);

    $g2 = $service->obtener($id);
    $check('edición actualiza fecha', (string) $g2['FechaGasto'] === '2099-06-15');
    $check('edición actualiza concepto/categoria/metodo/moneda', $g2['Concepto'] === $marca . ' editado' && $g2['Categoria'] === 'Publicidad' && $g2['MetodoPago'] === 'Transferencia' && $g2['Moneda'] === 'UYU');
    $check('edición actualiza monto', (float) $g2['Monto'] === 999.99);
    $check('edición setea observaciones NULL', $g2['Observaciones'] === null);
    $check('edición conserva TipoCambioAplicado del alta', (float) $g2['TipoCambioAplicado'] === 41.50);
    $check('edición mantiene Descripcion en NULL', $g2['Descripcion'] === null);

    // --- obtener de un id inexistente devuelve null ---
    $check('obtener id inexistente devuelve null', $service->obtener(-1) === null);
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
