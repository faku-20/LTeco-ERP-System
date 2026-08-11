<?php

declare(strict_types=1);

/**
 * Integración C4. Verifica alta, lectura y cambios de estado de reportes dentro
 * de una transacción que siempre termina con rollback.
 */

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $relativo = substr($clase, strlen($prefijo));
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

use Lteco\Application\Distribuidor\DistribuidorReporteService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\DistribuidorReporteRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  \xE2\x9C\x93 {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  \xE2\x9C\x97 {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$db = getenv('LTECO_DB_NAME') ?: 'lteco_db';
$user = getenv('LTECO_DB_USER') ?: 'lteco_user';
$pass = getenv('LTECO_DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$tablaExiste = static function (string $tabla) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([$db, $tabla]);
    return (int) $stmt->fetchColumn() > 0;
};

if (!$tablaExiste('distribuidor_reporte_problema')) {
    fwrite(STDERR, "FALLÓ — falta la tabla distribuidor_reporte_problema.\n");
    exit(1);
}

echo "Conectado a {$db}@{$host}. Abriendo transacción (se hará ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    $idDistribuidor = (int) $pdo->query(
        'SELECT IdDistribuidor FROM distribuidor ORDER BY IdDistribuidor LIMIT 1'
    )->fetchColumn();
    $idUsuario = (int) $pdo->query(
        'SELECT IdUsuario FROM usuario ORDER BY IdUsuario LIMIT 1'
    )->fetchColumn();

    if ($idDistribuidor <= 0 || $idUsuario <= 0) {
        throw new RuntimeException('La DB no tiene distribuidor y usuario base para el test.');
    }

    $service = new DistribuidorReporteService(
        new DistribuidorReporteRepository(new Connection($pdo))
    );

    $check('tabla de reportes disponible', $service->estaDisponible());

    $idReporte = $service->crearReporte(
        $idDistribuidor,
        $idUsuario,
        '  Mensaje de integración C4  ',
        'uploads/reportes_distribuidor/prueba-c4.webp'
    );
    $check('crearReporte devuelve id', $idReporte > 0);

    $reporte = $service->obtenerReporte($idReporte);
    $check('reporte creado se puede leer', $reporte !== null);
    $check(
        'mensaje se guarda trimmeado',
        $reporte !== null && $reporte['Mensaje'] === 'Mensaje de integración C4'
    );
    $check(
        'imagen opcional se conserva',
        $reporte !== null
            && $reporte['ImagenRuta'] === 'uploads/reportes_distribuidor/prueba-c4.webp'
    );
    $check('estado inicial Nuevo', $reporte !== null && $reporte['EstadoInterno'] === 'Nuevo');

    $idSinImagen = $service->crearReporte(
        $idDistribuidor,
        $idUsuario,
        'Reporte sin imagen',
        null
    );
    $sinImagen = $service->obtenerReporte($idSinImagen);
    $check(
        'imagen es realmente opcional',
        $sinImagen !== null && $sinImagen['ImagenRuta'] === null
    );

    $service->actualizarEstado($idReporte, 'Revisado', $idUsuario);
    $revisado = $service->obtenerReporte($idReporte);
    $check('estado Revisado persistido', $revisado !== null && $revisado['EstadoInterno'] === 'Revisado');
    $check('Revisado limpia usuario resolución', $revisado !== null && $revisado['UsuarioResolucionId'] === null);
    $check('Revisado limpia fecha resolución', $revisado !== null && $revisado['FechaResolucion'] === null);

    $service->actualizarEstado($idReporte, 'Resuelto', $idUsuario);
    $resuelto = $service->obtenerReporte($idReporte);
    $check('estado Resuelto persistido', $resuelto !== null && $resuelto['EstadoInterno'] === 'Resuelto');
    $check(
        'Resuelto registra usuario',
        $resuelto !== null && (int) $resuelto['UsuarioResolucionId'] === $idUsuario
    );
    $check('Resuelto registra fecha', $resuelto !== null && $resuelto['FechaResolucion'] !== null);

    $service->actualizarEstado($idReporte, 'Nuevo', $idUsuario);
    $reabierto = $service->obtenerReporte($idReporte);
    $check('reabrir vuelve a Nuevo', $reabierto !== null && $reabierto['EstadoInterno'] === 'Nuevo');
    $check(
        'reabrir limpia usuario resolución',
        $reabierto !== null && $reabierto['UsuarioResolucionId'] === null
    );
    $check(
        'reabrir limpia fecha resolución',
        $reabierto !== null && $reabierto['FechaResolucion'] === null
    );

    $mensajeObligatorio = false;
    try {
        $service->crearReporte($idDistribuidor, $idUsuario, '   ', null);
    } catch (InvalidArgumentException $e) {
        $mensajeObligatorio = $e->getMessage() === 'El mensaje es obligatorio.';
    }
    $check('rechaza mensaje vacío con texto legacy', $mensajeObligatorio);

    $mensajeLargo = false;
    try {
        $service->crearReporte($idDistribuidor, $idUsuario, str_repeat('x', 3001), null);
    } catch (InvalidArgumentException $e) {
        $mensajeLargo = $e->getMessage() === 'El mensaje no puede superar los 3000 caracteres.';
    }
    $check('rechaza mensaje largo con texto legacy', $mensajeLargo);

    $estadoInvalido = false;
    try {
        $service->actualizarEstado($idReporte, 'Cerrado', $idUsuario);
    } catch (InvalidArgumentException $e) {
        $estadoInvalido = $e->getMessage() === 'Estado no válido.';
    }
    $check('rechaza estado inválido con texto legacy', $estadoInvalido);
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado — la base de datos no fue modificada]\n";
}

echo "\n";
if ($fallos === []) {
    echo sprintf("OK — %d aserciones de integración pasaron.\n", $ok);
    exit(0);
}

echo sprintf("FALLÓ — %d ok, %d fallaron:\n", $ok, count($fallos));
foreach ($fallos as $fallo) {
    echo " - {$fallo}\n";
}
exit(1);
