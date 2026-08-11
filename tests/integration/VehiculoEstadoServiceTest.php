<?php

declare(strict_types=1);

/**
 * Test de INTEGRACIÓN de Lteco\Application\Vehiculo\VehiculoEstadoService.
 *
 * Igual que VentaRepositoryTest: TOCA la base de datos de desarrollo pero TODO
 * ocurre dentro de UNA transacción que SIEMPRE hace ROLLBACK, así la base queda
 * intacta. Pensado para correr DENTRO del contenedor:
 *
 *   docker exec ltecobike_panel php /var/www/html/tests/integration/VehiculoEstadoServiceTest.php
 *
 * Congela los efectos en DB de las acciones simples de vehículos (S1) que se
 * extrajeron desde lteco-panel/vehiculos/{toggle_web,cambiar_estado,reservar,
 * eliminar}.php hacia el servicio + VehiculoRepository:
 *   - cambiarEstado: producto (Estado/Stock/web) + sincronización del vehiculo.
 *   - reservar:      producto 'Reservado' + datos de reserva del vehiculo.
 *   - toggleWeb:     alterna visibilidad y respeta la regla de publicabilidad.
 *   - ocultar:       soft-delete del producto.
 *
 * El servicio es TRANSACTION-AGNOSTIC: este test es el dueño de la transacción.
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

// Las reglas de publicación viven en funciones puras de shared/ (sin DB ni app).
require_once dirname(__DIR__, 2) . '/shared/vehiculo_logic.php';

use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VehiculoRepository;
use Lteco\Application\Vehiculo\VehiculoEstadoService;

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
    // Un vehículo real con su producto. Lo dejamos en un estado "publicable"
    // controlado para poder ejercitar toggleWeb sin depender del dato actual.
    $fila = $pdo->query(
        'SELECT v.IdVehiculo, v.IdProducto
         FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto
         ORDER BY v.IdVehiculo LIMIT 1'
    )->fetch();
    $clienteId = (int) $pdo->query('SELECT IdCliente FROM cliente ORDER BY IdCliente LIMIT 1')->fetchColumn();

    if (!$fila || $clienteId === 0) {
        throw new RuntimeException('La DB de desarrollo no tiene vehículo/cliente base para el test.');
    }

    $idVehiculo = (string) $fila['IdVehiculo'];
    $idProducto = (int) $fila['IdProducto'];

    $conn = new Connection($pdo);
    $repo = new VehiculoRepository($conn);
    $service = new VehiculoEstadoService($repo);

    $prodRow = static fn (): array => $pdo->query('SELECT * FROM producto WHERE IdProducto = ' . $idProducto)->fetch();
    $vehRow  = static fn (): array => $pdo->query("SELECT * FROM vehiculo WHERE IdVehiculo = " . $pdo->quote($idVehiculo))->fetch();

    // === 1) cambiarEstado('Vendido') =========================================
    $r = $service->cambiarEstado($idVehiculo, ['IdProducto' => $idProducto, 'MostrarEnWeb' => 1, 'DestacadoWeb' => 1], 'Vendido');
    $p = $prodRow();
    $v = $vehRow();
    $check("cambiarEstado('Vendido') deja Estado=Vendido", $p['Estado'] === 'Vendido');
    $check("cambiarEstado('Vendido') pone Stock=0",        (int) $p['Stock'] === 0);
    $check("cambiarEstado('Vendido') saca de web",         (int) $p['MostrarEnWeb'] === 0 && (int) $p['DestacadoWeb'] === 0);
    $check("cambiarEstado('Vendido') setea FechaVenta",    $v['FechaVenta'] !== null);
    $check("cambiarEstado('Vendido') limpia reserva",      $v['ClienteReservaId'] === null && $v['FechaReserva'] === null && $v['SeniaReserva'] === null);
    $check("cambiarEstado devuelve stock calculado",       $r['stock'] === 0);

    // === 2) cambiarEstado('Disponible') ======================================
    $service->cambiarEstado($idVehiculo, ['IdProducto' => $idProducto, 'MostrarEnWeb' => 0, 'DestacadoWeb' => 0], 'Disponible');
    $p = $prodRow();
    $v = $vehRow();
    $check("cambiarEstado('Disponible') deja Estado=Disponible", $p['Estado'] === 'Disponible');
    $check("cambiarEstado('Disponible') pone Stock=1",           (int) $p['Stock'] === 1);
    $check("cambiarEstado('Disponible') limpia FechaVenta",      $v['FechaVenta'] === null);

    // === 3) reservar() =======================================================
    $r = $service->reservar($idVehiculo, ['IdProducto' => $idProducto, 'MostrarEnWeb' => 0, 'DestacadoWeb' => 0], $clienteId, 5000.0);
    $p = $prodRow();
    $v = $vehRow();
    $check('reservar() deja producto Estado=Reservado', $p['Estado'] === 'Reservado');
    $check('reservar() guarda ClienteReservaId',        (int) $v['ClienteReservaId'] === $clienteId);
    $check('reservar() guarda SeniaReserva',            abs((float) $v['SeniaReserva'] - 5000.0) < 0.005);
    $check('reservar() setea FechaReserva',             $v['FechaReserva'] !== null);
    $check('reservar() limpia FechaVenta',              $v['FechaVenta'] === null);

    // === 4) toggleWeb(): publicar y despublicar ==============================
    // Lo dejamos publicable: Disponible + Stock + datos completos.
    $service->cambiarEstado($idVehiculo, ['IdProducto' => $idProducto, 'MostrarEnWeb' => 0, 'DestacadoWeb' => 0], 'Disponible');
    $pdo->prepare("UPDATE producto SET PrecioVenta = 1000, Slug = 'slug-test', DescripcionWeb = 'desc test', MostrarEnWeb = 0, DestacadoWeb = 0 WHERE IdProducto = ?")
        ->execute([$idProducto]);
    $base = $prodRow();
    $base['Modelo'] = 'Modelo Test';
    $base['TieneImagen'] = 1;

    $r = $service->toggleWeb($base);
    $check('toggleWeb publicable -> success', $r['success'] === true);
    $check('toggleWeb publicable -> nuevoValor=1', $r['nuevoValor'] === 1);
    $check('toggleWeb activa MostrarEnWeb', (int) $prodRow()['MostrarEnWeb'] === 1);

    $base2 = $prodRow();
    $base2['Modelo'] = 'Modelo Test';
    $base2['TieneImagen'] = 1;
    $r = $service->toggleWeb($base2);
    $check('toggleWeb estando visible -> oculta', $r['nuevoValor'] === 0);
    $check('toggleWeb desactiva MostrarEnWeb', (int) $prodRow()['MostrarEnWeb'] === 0);

    // toggleWeb sobre un producto NO publicable debe bloquear sin tocar DB.
    $pdo->prepare("UPDATE producto SET PrecioVenta = 0, MostrarEnWeb = 0 WHERE IdProducto = ?")->execute([$idProducto]);
    $noPublicable = $prodRow();
    $noPublicable['Modelo'] = 'Modelo Test';
    $noPublicable['TieneImagen'] = 1;
    $r = $service->toggleWeb($noPublicable);
    $check('toggleWeb no publicable -> success=false', $r['success'] === false);
    $check('toggleWeb no publicable -> accion=toggle_web', ($r['accion'] ?? '') === 'toggle_web');
    $check('toggleWeb no publicable -> no toca MostrarEnWeb', (int) $prodRow()['MostrarEnWeb'] === 0);

    // === 5) ocultar() ========================================================
    $service->ocultar($idProducto);
    $p = $prodRow();
    $check('ocultar() deja Estado=Oculto', $p['Estado'] === 'Oculto');
    $check('ocultar() saca de web',        (int) $p['MostrarEnWeb'] === 0 && (int) $p['DestacadoWeb'] === 0);

    // === 6) moverOrdenWeb() =================================================
    $vecino = $pdo->query(
        "SELECT v.IdVehiculo, v.IdProducto
         FROM vehiculo v
         INNER JOIN producto p ON p.IdProducto = v.IdProducto
         WHERE v.IdVehiculo <> " . $pdo->quote($idVehiculo) . "
           AND p.TipoProducto = 'Moto'
         ORDER BY v.IdVehiculo
         LIMIT 1"
    )->fetch();
    if ($vecino) {
        $pdo->prepare('UPDATE producto SET OrdenWeb = 10 WHERE IdProducto = ?')->execute([$idProducto]);
        $pdo->prepare('UPDATE producto SET OrdenWeb = 20 WHERE IdProducto = ?')->execute([(int) $vecino['IdProducto']]);

        $movido = $service->moverOrdenWeb($idVehiculo, 'down');
        $ordenActual = (int) $pdo->query('SELECT OrdenWeb FROM producto WHERE IdProducto = ' . $idProducto)->fetchColumn();
        $ordenVecino = (int) $pdo->query('SELECT OrdenWeb FROM producto WHERE IdProducto = ' . (int) $vecino['IdProducto'])->fetchColumn();
        $check('moverOrdenWeb devuelve true cuando hay vecino', $movido === true);
        $check('moverOrdenWeb intercambia orden actual', $ordenActual === 20);
        $check('moverOrdenWeb intercambia orden vecino', $ordenVecino === 10);
    } else {
        echo "  (solo hay un vehículo: se omiten aserciones de orden web)\n";
    }
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
