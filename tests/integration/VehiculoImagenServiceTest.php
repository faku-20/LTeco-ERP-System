<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Vehiculo\VehiculoImagenService.
 *
 * Igual que VehiculoEditarServiceTest: TOCA la base de datos de desarrollo pero
 * TODO ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base
 * queda intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VehiculoImagenServiceTest.php
 *
 * Congela los efectos en DB de la gestión de imágenes de
 * lteco-panel/vehiculos/editar.php que se extrajo al servicio + repositorio:
 *   - marcarPrincipal(): deja exactamente una imagen principal.
 *   - eliminar(): borra la fila, recompacta OrdenImagen 1..n, reasigna la
 *     principal si se borró la portada, y devuelve la ruta para el borrado
 *     físico (que NO ejecuta: es responsabilidad del handler).
 *   - mover(): swap de OrdenImagen con la vecina.
 *   - imagen inexistente => RuntimeException.
 *
 * NO toca archivos reales: sólo siembra filas de vehiculo_imagen con rutas
 * ficticias y verifica el estado en DB. El servicio es TRANSACTION-AGNOSTIC:
 * este test es el dueño de la transacción.
 */

// --- Autoloader PSR-4 Lteco\ -> src/ -----------------------------------------
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

use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VehiculoRepository;
use Lteco\Application\Vehiculo\VehiculoImagenService;

// --- Mini-arnés de aserciones ------------------------------------------------
$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $cond) use (&$fallos, &$ok): void {
    if ($cond) {
        $ok++;
        echo "  \xE2\x9C\x93 {$nombre}\n";
    } else {
        $fallos[] = $nombre;
        echo "  \xE2\x9C\x97 {$nombre}\n";
    }
};

// --- Conexión a la DB de desarrollo ------------------------------------------
$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$db   = getenv('LTECO_DB_NAME') ?: 'lteco_db';
$user = getenv('LTECO_DB_USER') ?: 'lteco_user';
$pass = getenv('LTECO_DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

echo "Conectado a {$db}@{$host}. Abriendo transacción (se hará ROLLBACK al final).\n\n";

$pdo->beginTransaction();
try {
    $idVehiculo = (string) $pdo->query('SELECT IdVehiculo FROM vehiculo ORDER BY IdVehiculo LIMIT 1')->fetchColumn();
    if ($idVehiculo === '') {
        throw new RuntimeException('La DB de desarrollo no tiene vehículo base para el test.');
    }

    // Limpiamos cualquier imagen previa del vehículo dentro de la transacción
    // (se revierte en el rollback) para partir de un estado conocido.
    $pdo->prepare('DELETE FROM vehiculo_imagen WHERE IdVehiculo = ?')->execute([$idVehiculo]);

    // Sembramos 3 imágenes: la #1 es la principal, órdenes 1/2/3.
    $insertar = $pdo->prepare(
        'INSERT INTO vehiculo_imagen (IdVehiculo, RutaImagen, EsPrincipal, OrdenImagen) VALUES (?, ?, ?, ?)'
    );
    $insertar->execute([$idVehiculo, 'uploads/vehiculos/test_a.jpg', 1, 1]);
    $idA = (int) $pdo->lastInsertId();
    $insertar->execute([$idVehiculo, 'uploads/vehiculos/test_b.jpg', 0, 2]);
    $idB = (int) $pdo->lastInsertId();
    $insertar->execute([$idVehiculo, 'uploads/vehiculos/test_c.jpg', 0, 3]);
    $idC = (int) $pdo->lastInsertId();

    $conn = new Connection($pdo);
    $repo = new VehiculoRepository($conn);
    $service = new VehiculoImagenService($repo);

    $imgRow = static fn (int $id): ?array => $pdo->query(
        'SELECT * FROM vehiculo_imagen WHERE IdImagen = ' . $id
    )->fetch() ?: null;
    $cuantasPrincipales = static fn (): int => (int) $pdo->query(
        "SELECT COUNT(*) FROM vehiculo_imagen WHERE IdVehiculo = " . $pdo->quote($idVehiculo) . " AND EsPrincipal = 1"
    )->fetchColumn();

    // === 1) marcarPrincipal(): mueve la portada a B ==========================
    $service->marcarPrincipal($idVehiculo, $idB);
    $check('marcarPrincipal deja exactamente una principal', $cuantasPrincipales() === 1);
    $check('marcarPrincipal pone B como principal',          (int) $imgRow($idB)['EsPrincipal'] === 1);
    $check('marcarPrincipal quita principal de A',           (int) $imgRow($idA)['EsPrincipal'] === 0);

    // === 2) mover(): A (orden 1) hacia abajo intercambia con B (orden 2) =====
    $service->mover($idVehiculo, $idA, 'down');
    $check('mover down sube el orden de A a 2',  (int) $imgRow($idA)['OrdenImagen'] === 2);
    $check('mover down baja el orden de B a 1',  (int) $imgRow($idB)['OrdenImagen'] === 1);

    // === 3) mover() en el extremo => RuntimeException ========================
    // B quedó en orden 1; moverla "up" no tiene vecina.
    $excMover = false;
    try {
        $service->mover($idVehiculo, $idB, 'up');
    } catch (RuntimeException $e) {
        $excMover = true;
    }
    $check('mover en el extremo lanza RuntimeException', $excMover);

    // === 4) eliminar() la principal (B): recompacta y reasigna portada =======
    // Estado actual de órdenes: B=1, A=2, C=3. B es principal.
    $rutaB = $service->eliminar($idVehiculo, $idB);
    $check('eliminar devuelve la ruta para borrado físico', $rutaB === 'uploads/vehiculos/test_b.jpg');
    $check('eliminar borra la fila',                        $imgRow($idB) === null);
    $check('eliminar recompacta OrdenImagen a 1..n',
        (int) $imgRow($idA)['OrdenImagen'] === 1 && (int) $imgRow($idC)['OrdenImagen'] === 2);
    $check('eliminar reasigna principal a la primera restante',
        (int) $imgRow($idA)['EsPrincipal'] === 1 && $cuantasPrincipales() === 1);

    // === 5) eliminar() imagen inexistente => RuntimeException ================
    $excEliminar = false;
    try {
        $service->eliminar($idVehiculo, 0);
    } catch (RuntimeException $e) {
        $excEliminar = true;
    }
    $check('eliminar imagen inexistente lanza RuntimeException', $excEliminar);
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado \xE2\x80\x94 la base de datos no fue modificada]\n";
}

echo "\n";
if (empty($fallos)) {
    echo sprintf("OK \xE2\x80\x94 %d aserciones de integraci\xC3\xB3n pasaron.\n", $ok);
    exit(0);
}

echo sprintf("FALL\xC3\x93 \xE2\x80\x94 %d ok, %d fallaron:\n", $ok, count($fallos));
foreach ($fallos as $f) {
    echo ' - ' . $f . "\n";
}
exit(1);
