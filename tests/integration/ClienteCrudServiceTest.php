<?php

declare(strict_types=1);

/**
 * Integración D3. Verifica alta, edición y unicidad de clientes dentro de una
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

use Lteco\Application\Cliente\ClienteCrudService;
use Lteco\Infrastructure\Db\Connection;
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
    $service = new ClienteCrudService(new ClienteCrudRepository(new Connection($pdo)));

    $sufijo = bin2hex(random_bytes(4));
    $tel = '099' . substr($sufijo, 0, 6);
    $correo = 'd3_' . $sufijo . '@example.com';

    // --- crear ---
    $idCliente = $service->crear([
        'nombre_apellido' => 'Cliente D3 ' . $sufijo,
        'telefono' => $tel,
        'correo' => $correo,
        'tipo_fiscal' => 'Empresa/RUT',
        'cedula' => '1234567',
        'direccion' => 'Calle falsa 123',
        'rut' => '210000000017',
    ]);
    $check('crear devuelve id', $idCliente > 0);

    $fila = $service->obtener($idCliente);
    $check('crear persiste nombre', $fila !== null && $fila['NombreApellido'] === 'Cliente D3 ' . $sufijo);
    $check('crear persiste tipo fiscal', $fila !== null && $fila['TipoFiscal'] === 'Empresa/RUT');
    $check('crear persiste rut', $fila !== null && $fila['RUT'] === '210000000017');
    $check('crear persiste telefono', $fila !== null && $fila['Telefono'] === $tel);

    // --- unicidad ---
    $check('telefono ocupado no disponible', $service->telefonoDisponible($tel, null) === false);
    $check('telefono nuevo disponible', $service->telefonoDisponible('0991112223', null) === true);
    $check('telefono propio disponible al excluirse', $service->telefonoDisponible($tel, $idCliente) === true);
    $check('correo ocupado no disponible', $service->correoDisponible($correo, null) === false);
    $check('correo propio disponible al excluirse', $service->correoDisponible($correo, $idCliente) === true);
    $check('telefono vacío siempre disponible', $service->telefonoDisponible('', null) === true);
    $check('correo vacío siempre disponible', $service->correoDisponible('', null) === true);

    // --- editar ---
    $service->editar($idCliente, [
        'nombre_apellido' => 'Cliente D3 Editado',
        'telefono' => null,
        'correo' => null,
        'tipo_fiscal' => 'Consumidor final',
        'cedula' => null,
        'direccion' => null,
        'rut' => null,
    ]);
    $editado = $service->obtener($idCliente);
    $check('editar persiste nombre', $editado !== null && $editado['NombreApellido'] === 'Cliente D3 Editado');
    $check('editar persiste tipo fiscal', $editado !== null && $editado['TipoFiscal'] === 'Consumidor final');
    $check('editar limpia opcionales a null', $editado !== null && $editado['Telefono'] === null && $editado['RUT'] === null);

    $check('obtener devuelve null si no existe', $service->obtener(0) === null);
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
