<?php

declare(strict_types=1);

/**
 * Integración D2. Verifica el listado, filtros, conteos e importaciones de
 * repuestos dentro de una transacción que siempre termina con rollback.
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

use Lteco\Application\Repuesto\RepuestoConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\RepuestoConsultaRepository;
use Lteco\Infrastructure\Repository\RepuestoCrudRepository;

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
    // Espeja shared/db.php: prepares nativos (sin emulación). Imprescindible
    // para reproducir HY093 cuando un placeholder con nombre se reusa.
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $connection = new Connection($pdo);
    $crud = new RepuestoCrudRepository($connection);
    $service = new RepuestoConsultaService(new RepuestoConsultaRepository($connection));

    $empresaRut = $pdo->query("SELECT Empresa_RUT FROM producto WHERE Empresa_RUT IS NOT NULL LIMIT 1")->fetchColumn();
    $empresaRut = $empresaRut !== false ? (string) $empresaRut : null;

    $marca = 'ZZD2_' . bin2hex(random_bytes(4));
    $idDisp = $crud->crear([
        'nombre' => $marca . ' Disponible', 'descripcion' => 'busca-' . $marca, 'costo' => 1.0,
        'gasto_total' => 0.0, 'precio_venta' => 100.0, 'precio_distribuidor' => 80.0, 'moneda' => 'UYU',
        'stock' => 9, 'estado' => 'Disponible', 'numero_importacion' => null, 'empresa_rut' => $empresaRut,
    ]);
    $idOculto = $crud->crear([
        'nombre' => $marca . ' Oculto', 'descripcion' => 'otro', 'costo' => 1.0,
        'gasto_total' => 0.0, 'precio_venta' => 100.0, 'precio_distribuidor' => null, 'moneda' => 'UYU',
        'stock' => 0, 'estado' => 'Oculto', 'numero_importacion' => null, 'empresa_rut' => $empresaRut,
    ]);

    // --- listar con búsqueda por texto + estado/importación vacíos (caso bug Ola D) ---
    // Con prepares nativos, reusar el placeholder :q lanzaba HY093. estado=''
    // e importacion='' deben tratarse como "todos"/"todas" sin filtrar.
    $porTexto = $service->listar(['estado' => '', 'q' => $marca, 'importacion' => '', 'es_distribuidor' => false]);
    $check('listar con q + estado/importación vacíos no explota', count($porTexto) === 2);
    $ids = array_map(static fn($r) => (int) $r['IdProducto'], $porTexto);
    $check('listar incluye disponible y oculto', in_array($idDisp, $ids, true) && in_array($idOculto, $ids, true));

    // --- búsqueda por texto que también matchea la descripción ---
    $porDescripcion = $service->listar(['estado' => '', 'q' => 'busca-' . $marca, 'importacion' => '', 'es_distribuidor' => false]);
    $check('búsqueda matchea por descripción', count($porDescripcion) === 1 && (int) $porDescripcion[0]['IdProducto'] === $idDisp);

    // --- búsqueda sin resultados devuelve lista vacía, no error ---
    $sinResultados = $service->listar(['estado' => '', 'q' => 'NO_EXISTE_' . $marca, 'importacion' => '', 'es_distribuidor' => false]);
    $check('búsqueda sin resultados devuelve lista vacía', $sinResultados === []);

    // --- filtro por estado (solo admin) ---
    $porEstado = $service->listar(['estado' => 'Oculto', 'q' => $marca, 'importacion' => '', 'es_distribuidor' => false]);
    $check('filtro por estado Oculto deja solo el oculto', count($porEstado) === 1 && (int) $porEstado[0]['IdProducto'] === $idOculto);

    // --- filtro importación "sin" ---
    $sinImport = $service->listar(['estado' => '', 'q' => $marca, 'importacion' => 'sin', 'es_distribuidor' => false]);
    $check('filtro importación sin devuelve los dos sin importación', count($sinImport) === 2);

    // --- vista distribuidor: solo Disponible con stock ---
    $distrib = $service->listar(['estado' => 'Oculto', 'q' => $marca, 'importacion' => '5', 'es_distribuidor' => true]);
    $check('vista distribuidor ignora estado/importación y filtra disponible+stock', count($distrib) === 1 && (int) $distrib[0]['IdProducto'] === $idDisp);

    // --- conteos ---
    $conteos = $service->conteos(false);
    $check('conteos trae claves esperadas', array_key_exists('', $conteos) && array_key_exists('Disponible', $conteos) && array_key_exists('Oculto', $conteos));
    $check('conteos total = suma de estados', $conteos[''] === $conteos['Disponible'] + $conteos['Sin stock'] + $conteos['Oculto']);

    $conteosDistrib = $service->conteos(true);
    $check('conteos distribuidor cuenta disponibles con stock', $conteosDistrib[''] >= 1);

    // --- importaciones ---
    $check('importaciones devuelve array', is_array($service->importaciones()));
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
