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

use Lteco\Application\Distribuidor\DistribuidorCrudService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\DistribuidorCrudRepository;

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
    $service = new DistribuidorCrudService(
        new DistribuidorCrudRepository(new Connection($pdo))
    );

    $vacio = $service->prepararFormulario([
        'nombre' => '   ',
        'comision_pct' => '101',
    ], false);
    $check('nombre vacío conserva validación legacy', in_array('El nombre es obligatorio.', $vacio['errores'], true));
    $check('comisión fuera de rango conserva validación legacy', in_array('La comision debe estar entre 0 y 100.', $vacio['errores'], true));

    $preparado = $service->prepararFormulario([
        'nombre' => '  Distribuidor C6  ',
        'comision_pct' => '7,25',
        'contacto' => ' Contacto ',
        'telefono' => ' 099 ',
        'correo' => ' correo@example.com ',
        'observaciones' => ' Nota ',
    ], false);
    $check('normaliza nombre', $preparado['form']['nombre'] === 'Distribuidor C6');
    $check('acepta coma decimal', abs($preparado['comision'] - 7.25) < 0.005);
    $check('formulario válido sin errores', $preparado['errores'] === []);

    $id = $service->crear($preparado['form'], $preparado['comision']);
    $check('crear devuelve id', $id > 0);
    $fila = $service->obtener($id);
    $check('crear persiste nombre', $fila !== null && $fila['Nombre'] === 'Distribuidor C6');
    $check('crear deja Activo=1', $fila !== null && (int) $fila['Activo'] === 1);
    $check('crear convierte vacío a null', $fila !== null && $fila['Observaciones'] === 'Nota');

    $edicion = $service->prepararFormulario([
        'nombre' => 'Distribuidor Editado',
        'comision_pct' => '0',
        'contacto' => '',
        'telefono' => '',
        'correo' => '',
        'observaciones' => '',
    ], true);
    $service->editar($id, $edicion['form'], $edicion['comision']);
    $fila = $service->obtener($id);
    $check('editar persiste nombre', $fila !== null && $fila['Nombre'] === 'Distribuidor Editado');
    $check('editar permite comisión cero', $fila !== null && (float) $fila['ComisionPct'] === 0.0);
    $check('editar desactiva sin checkbox', $fila !== null && (int) $fila['Activo'] === 0);
    $check('editar convierte opcionales vacíos a null', $fila !== null && $fila['Contacto'] === null);
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
