<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Lteco\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use Lteco\Application\Venta\VentaAnulacionService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaAnulacionRepository;

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

$rows = static function (PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

$venta = $pdo->query("
    SELECT IdVenta, EstadoVenta, MotivoAnulacion, MontoPagado, SaldoPendiente
    FROM venta
    WHERE COALESCE(EstadoVenta, 'Confirmada') <> 'Anulada'
    ORDER BY IdVenta
    LIMIT 1
")->fetch();

if (!$venta) {
    echo "SKIP - no hay venta activa para probar rollback.\n";
    exit(0);
}

$idVenta = (int) $venta['IdVenta'];
$detalles = $rows(
    $pdo,
    'SELECT Producto_IdProducto, Cantidad FROM venta_detalle WHERE Venta_IdVenta = ? ORDER BY IdVentaDetalle',
    [$idVenta]
);
$idsProducto = array_values(array_unique(array_map(
    static fn (array $detalle): int => (int) $detalle['Producto_IdProducto'],
    $detalles
)));
$marcadoresProducto = implode(',', array_fill(0, count($idsProducto), '?'));
$idDistribuidor = (int) ($pdo->query(
    'SELECT DistribuidorVendedorId FROM venta WHERE IdVenta = ' . $idVenta
)->fetchColumn() ?: 0);

$snapshot = static function () use (
    $pdo,
    $rows,
    $idVenta,
    $idDistribuidor,
    $idsProducto,
    $marcadoresProducto
): array {
    $estado = [
        'venta' => $rows($pdo, 'SELECT * FROM venta WHERE IdVenta = ?', [$idVenta]),
        'producto' => [],
        'vehiculo' => [],
        'distribuidor_stock' => [],
        'remito' => $rows($pdo, 'SELECT * FROM remito WHERE IdVenta = ? ORDER BY IdRemito', [$idVenta]),
        'distribuidor_comision' => $rows(
            $pdo,
            'SELECT * FROM distribuidor_comision WHERE IdVenta = ? ORDER BY IdComision',
            [$idVenta]
        ),
        'gasto' => $rows($pdo, 'SELECT * FROM gasto WHERE IdVenta = ? ORDER BY IdGasto', [$idVenta]),
        'garantia' => $rows($pdo, 'SELECT * FROM garantia WHERE IdVenta = ? ORDER BY IdGarantia', [$idVenta]),
        'service_vehiculo' => $rows(
            $pdo,
            'SELECT * FROM service_vehiculo WHERE IdVenta = ? ORDER BY IdService',
            [$idVenta]
        ),
    ];

    if ($idsProducto !== []) {
        $estado['producto'] = $rows(
            $pdo,
            "SELECT * FROM producto WHERE IdProducto IN ({$marcadoresProducto}) ORDER BY IdProducto",
            $idsProducto
        );
        $estado['vehiculo'] = $rows(
            $pdo,
            "SELECT * FROM vehiculo WHERE IdProducto IN ({$marcadoresProducto}) ORDER BY IdVehiculo",
            $idsProducto
        );
    }

    if ($idDistribuidor > 0) {
        $estado['distribuidor_stock'] = $rows(
            $pdo,
            'SELECT * FROM distribuidor_stock WHERE IdDistribuidor = ? ORDER BY IdStock',
            [$idDistribuidor]
        );
    }

    return $estado;
};

$antes = $snapshot();
$service = new VentaAnulacionService(
    new VentaAnulacionRepository(new Connection($pdo))
);

$pdo->beginTransaction();
try {
    $resultado = $service->anular($idVenta, 'TEST ROLLBACK POO', 0, '');
    $durante = $pdo->query(
        'SELECT IdVenta, EstadoVenta, MotivoAnulacion, MontoPagado, SaldoPendiente ' .
        'FROM venta WHERE IdVenta = ' . $idVenta
    )->fetch();

    if (($durante['EstadoVenta'] ?? null) !== 'Anulada') {
        throw new RuntimeException('El servicio no anuló la venta dentro de la transacción.');
    }
    if ($resultado->productosRevertidos !== count($detalles)) {
        throw new RuntimeException('El conteo de productos revertidos no coincide con venta_detalle.');
    }

    $duranteSnapshot = $snapshot();
    $filasCambiadas = static function (array $antesTabla, array $duranteTabla): int {
        $cambios = 0;
        foreach ($antesTabla as $indice => $fila) {
            if (($duranteTabla[$indice] ?? null) !== $fila) {
                $cambios++;
            }
        }

        return $cambios;
    };
    $conteos = [
        'comisiones' => [
            $resultado->comisionesAnuladas,
            $filasCambiadas($antes['distribuidor_comision'], $duranteSnapshot['distribuidor_comision']),
        ],
        'gastos' => [
            $resultado->gastosComisionInactivados,
            $filasCambiadas($antes['gasto'], $duranteSnapshot['gasto']),
        ],
        'garantías' => [
            $resultado->garantiasAnuladas,
            $filasCambiadas($antes['garantia'], $duranteSnapshot['garantia']),
        ],
        'services' => [
            $resultado->servicesCancelados,
            $filasCambiadas($antes['service_vehiculo'], $duranteSnapshot['service_vehiculo']),
        ],
    ];
    foreach ($conteos as $nombre => [$reportado, $observado]) {
        if ($reportado !== $observado) {
            throw new RuntimeException(
                "El conteo de {$nombre} reportado ({$reportado}) no coincide con filas cambiadas ({$observado})."
            );
        }
    }

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$despues = $snapshot();
if ($despues !== $antes) {
    $tablasDistintas = [];
    foreach ($antes as $tabla => $filas) {
        if (($despues[$tabla] ?? null) !== $filas) {
            $tablasDistintas[] = $tabla;
        }
    }
    fwrite(
        STDERR,
        "FALLO - rollback no restauró venta #{$idVenta}: " . implode(', ', $tablasDistintas) . ".\n"
    );
    exit(1);
}

echo "OK - servicio anuló venta #{$idVenta} y rollback restauró todas las filas afectadas.\n";
