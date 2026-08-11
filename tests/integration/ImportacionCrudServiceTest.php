<?php

declare(strict_types=1);

/**
 * Integración E4. Verifica alta, lectura y edición de importaciones
 * preservando el comportamiento legacy (la edición NO modifica el Numero),
 * dentro de una transacción con rollback.
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

use Lteco\Application\Importacion\ImportacionCrudService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\ImportacionCrudRepository;

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
    $service = new ImportacionCrudService(new ImportacionCrudRepository(new Connection($pdo)));

    $maxNum = (int) $pdo->query("SELECT COALESCE(MAX(Numero), 0) FROM importacion")->fetchColumn();
    $numero = $maxNum + 2000;

    // --- alta ---
    $id = $service->crear([
        'numero' => $numero,
        'tipo_cambio_usd' => 42.50,
        'fecha' => '2099-07-01',
        'descripcion' => 'E4 alta',
        'activa' => 1,
    ]);
    $check('crear devuelve un IdImportacion > 0', $id > 0);

    $i = $service->obtener($id);
    $check('obtener trae la importación creada', is_array($i) && (int) $i['IdImportacion'] === $id);
    $check('alta persiste Numero', (int) $i['Numero'] === $numero);
    $check('alta persiste TipoCambioUSD', (float) $i['TipoCambioUSD'] === 42.50);
    $check('alta persiste Fecha', (string) $i['Fecha'] === '2099-07-01');
    $check('alta persiste Descripcion', $i['Descripcion'] === 'E4 alta');
    $check('alta persiste Activa', (int) $i['Activa'] === 1);

    // --- alta con fecha/descripcion nulas ---
    $idNull = $service->crear([
        'numero' => $numero + 1,
        'tipo_cambio_usd' => 50.00,
        'fecha' => null,
        'descripcion' => null,
        'activa' => 0,
    ]);
    $iNull = $service->obtener($idNull);
    $check('alta acepta Fecha/Descripcion NULL', $iNull['Fecha'] === null && $iNull['Descripcion'] === null);
    $check('alta persiste Activa = 0', (int) $iNull['Activa'] === 0);

    // --- edición: cambia datos pero NO el Numero ---
    $service->editar($id, [
        'tipo_cambio_usd' => 45.75,
        'fecha' => '2099-08-15',
        'descripcion' => 'E4 editada',
        'activa' => 0,
    ]);
    $i2 = $service->obtener($id);
    $check('edición conserva el Numero original', (int) $i2['Numero'] === $numero);
    $check('edición actualiza TipoCambioUSD', (float) $i2['TipoCambioUSD'] === 45.75);
    $check('edición actualiza Fecha', (string) $i2['Fecha'] === '2099-08-15');
    $check('edición actualiza Descripcion', $i2['Descripcion'] === 'E4 editada');
    $check('edición actualiza Activa', (int) $i2['Activa'] === 0);

    // --- obtener inexistente ---
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
