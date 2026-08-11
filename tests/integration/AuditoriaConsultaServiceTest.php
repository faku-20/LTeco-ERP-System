<?php

declare(strict_types=1);

/**
 * Integración G1. Verifica opciones, filtros, total, orden y paginación del
 * listado de auditoría dentro de una transacción con rollback.
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

use Lteco\Application\Auditoria\AuditoriaConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\AuditoriaRepository;

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
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new AuditoriaConsultaService(new AuditoriaRepository(new Connection($pdo)));
    $check('tabla auditoria disponible', $service->tablaExiste());

    $sufijo = bin2hex(random_bytes(4));
    $usuario = 'AuditG1-' . $sufijo;
    $modulo = 'ModuloG1-' . $sufijo;
    $accion = 'ACCION_G1_' . strtoupper($sufijo);
    $detalle = 'Detalle único G1 ' . $sufijo;

    $insertar = $pdo->prepare("
        INSERT INTO auditoria
            (IdUsuario, Usuario, Rol, Accion, Modulo, Detalle, ExtraJson, Ip, UserAgent, FechaHora)
        VALUES
            (NULL, ?, 'Superadmin', ?, ?, ?, ?, '127.0.0.1', 'Integration G1', NOW())
    ");
    $insertar->execute([$usuario, $accion, $modulo, $detalle, '{"g1":true}']);
    $id = (int) $pdo->lastInsertId();

    $base = ['q' => '', 'modulo' => '', 'accion' => '', 'usuario' => '', 'desde' => '', 'hasta' => ''];
    $resultado = $service->consultar(array_merge($base, ['q' => $sufijo]), 1, 50);
    $ids = array_map(static fn(array $fila): int => (int) $fila['IdAuditoria'], $resultado['registros']);

    $check('búsqueda general encuentra el registro', in_array($id, $ids, true));
    $check('total filtrado incluye el registro', $resultado['total'] >= 1);
    $check('opciones incluye módulo', in_array($modulo, $resultado['modulos'], true));
    $check('opciones incluye acción', in_array($accion, $resultado['acciones'], true));
    $check('opciones incluye usuario', in_array($usuario, $resultado['usuarios'], true));

    $exacto = $service->consultar(array_merge($base, [
        'modulo' => $modulo,
        'accion' => $accion,
        'usuario' => $usuario,
        'desde' => date('Y-m-d'),
        'hasta' => date('Y-m-d'),
    ]), 1, 1);
    $check('filtros exactos dejan un registro', $exacto['total'] === 1);
    $check('paginación respeta límite', count($exacto['registros']) === 1);
    $check('listado devuelve columnas de la vista', isset(
        $exacto['registros'][0]['IdAuditoria'],
        $exacto['registros'][0]['Usuario'],
        $exacto['registros'][0]['Rol'],
        $exacto['registros'][0]['Accion'],
        $exacto['registros'][0]['Modulo'],
        $exacto['registros'][0]['Detalle'],
        $exacto['registros'][0]['ExtraJson'],
        $exacto['registros'][0]['Ip'],
        $exacto['registros'][0]['UserAgent'],
        $exacto['registros'][0]['FechaHora']
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
