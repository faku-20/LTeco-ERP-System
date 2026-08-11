<?php

declare(strict_types=1);

/**
 * Integración D4. Verifica el listado de clientes con agregados de ventas, el
 * alcance por vendedor y las lecturas de detalle, dentro de una transacción que
 * siempre termina con rollback.
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

use Lteco\Application\Cliente\ClienteConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\ClienteConsultaRepository;
use Lteco\Infrastructure\Repository\ClienteCrudRepository;
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
    $service = new ClienteConsultaService(new ClienteConsultaRepository($connection));
    $crud = new ClienteCrudRepository($connection);
    $repuestos = new RepuestoCrudRepository($connection);

    $tipoCambio = 40.0;
    $vendedorId = (int) $pdo->query("SELECT IdUsuario FROM usuario ORDER BY IdUsuario ASC LIMIT 1")->fetchColumn();
    $empresaRut = $pdo->query("SELECT Empresa_RUT FROM producto WHERE Empresa_RUT IS NOT NULL LIMIT 1")->fetchColumn();
    $empresaRut = $empresaRut !== false ? (string) $empresaRut : null;

    $sufijo = bin2hex(random_bytes(4));
    $nombre = 'ConsultaD4 ' . $sufijo;
    $idCliente = $crud->crear([
        'nombre_apellido' => $nombre, 'telefono' => '099' . substr($sufijo, 0, 6), 'correo' => null,
        'tipo_fiscal' => 'Consumidor final', 'cedula' => null, 'direccion' => null, 'rut' => null,
    ]);

    $idProducto = $repuestos->crear([
        'nombre' => 'RepD4 ' . $sufijo, 'descripcion' => '', 'costo' => 1.0, 'gasto_total' => 0.0,
        'precio_venta' => 500.0, 'precio_distribuidor' => null, 'moneda' => 'UYU', 'stock' => 5,
        'estado' => 'Disponible', 'numero_importacion' => null, 'empresa_rut' => $empresaRut,
    ]);

    // Venta confirmada del vendedor; venta anulada sin vendedor (debe excluirse de agregados).
    $insVenta = $pdo->prepare("INSERT INTO venta (Cliente_IdCliente, Total, Moneda, SaldoPendiente, MontoPagado, GananciaEstimada, TipoCambioAplicado, EstadoVenta, MetodoPago, FechaVenta, UsuarioVendedorId)
        VALUES (?, ?, 'UYU', ?, ?, ?, 0, ?, 'Efectivo', NOW(), ?)");
    $insVenta->execute([$idCliente, 1000.0, 200.0, 800.0, 300.0, 'Confirmada', $vendedorId]);
    $idVenta = (int) $pdo->lastInsertId();
    $insVenta->execute([$idCliente, 5000.0, 0.0, 0.0, 0.0, 'Anulada', null]);

    $insDetalle = $pdo->prepare("INSERT INTO venta_detalle (Venta_IdVenta, Producto_IdProducto, Cantidad, PrecioUnitario, Subtotal, Moneda) VALUES (?, ?, 2, 500.0, 1000.0, 'UYU')");
    $insDetalle->execute([$idVenta, $idProducto]);

    // --- listar (admin) ---
    $busca = static function (array $rows) use ($idCliente): ?array {
        foreach ($rows as $r) {
            if ((int) $r['IdCliente'] === $idCliente) {
                return $r;
            }
        }
        return null;
    };

    $fila = $busca($service->listar($nombre, 0, $tipoCambio));
    $check('listar encuentra el cliente por texto', $fila !== null);
    $check('compras cuenta solo ventas activas', $fila !== null && (int) $fila['Compras'] === 1);
    $check('total gastado en UYU correcto', $fila !== null && abs((float) $fila['TotalGastadoUYU'] - 1000.0) < 0.01);
    $check('saldo pendiente en UYU correcto', $fila !== null && abs((float) $fila['SaldoPendienteUYU'] - 200.0) < 0.01);

    // --- búsqueda multi-campo (caso bug Ola D: :cliente reusado => HY093) ---
    // Cliente con todos los campos para buscar por teléfono / correo / documento.
    $telefono2 = '098' . substr($sufijo, 0, 6);
    $correo2 = 'd4_' . $sufijo . '@example.com';
    $cedula2 = '4' . substr($sufijo, 0, 7);
    $rut2 = '21' . substr($sufijo, 0, 10);
    $idCliente2 = $crud->crear([
        'nombre_apellido' => 'OtroD4 ' . $sufijo, 'telefono' => $telefono2, 'correo' => $correo2,
        'tipo_fiscal' => 'Empresa', 'cedula' => $cedula2, 'direccion' => 'Av Siempreviva ' . $sufijo, 'rut' => $rut2,
    ]);
    $buscaId = static function (array $rows, int $id): bool {
        foreach ($rows as $r) {
            if ((int) $r['IdCliente'] === $id) {
                return true;
            }
        }
        return false;
    };

    $check('busca por teléfono', $buscaId($service->listar($telefono2, 0, $tipoCambio), $idCliente2));
    $check('busca por correo', $buscaId($service->listar($correo2, 0, $tipoCambio), $idCliente2));
    $check('busca por documento (cédula)', $buscaId($service->listar($cedula2, 0, $tipoCambio), $idCliente2));
    $check('busca por RUT', $buscaId($service->listar($rut2, 0, $tipoCambio), $idCliente2));
    $check('busca por dirección', $buscaId($service->listar('Av Siempreviva ' . $sufijo, 0, $tipoCambio), $idCliente2));

    // El input <script> debe tratarse como texto normal: sin error, sin resultados.
    $xss = $service->listar("<script>alert('XSS')</script>", 0, $tipoCambio);
    $check('búsqueda con <script> no explota y devuelve lista', is_array($xss));

    // Búsqueda sin resultados => lista vacía, no error.
    $check('búsqueda sin resultados devuelve lista vacía', $service->listar('NO_EXISTE_' . $sufijo, 0, $tipoCambio) === []);

    // Export también reusaba :cliente: debe filtrar sin romper.
    $check('listarParaExport con filtro no explota', $buscaId($service->listarParaExport($correo2, $tipoCambio), $idCliente2));

    // --- alcance por vendedor ---
    $filaVendedor = $busca($service->listar('', $vendedorId, $tipoCambio));
    $check('vendedor dueño ve el cliente', $filaVendedor !== null);
    $filaOtro = $busca($service->listar('', 999999, $tipoCambio));
    $check('vendedor ajeno no ve el cliente', $filaOtro === null);

    // --- filtro en memoria por tipo ---
    $conCompras = $service->aplicarFiltroTipo([$fila], 'con_compras');
    $check('filtro con_compras conserva cliente con compras', count($conCompras) === 1);
    $sinCompras = $service->aplicarFiltroTipo([$fila], 'sin_compras');
    $check('filtro sin_compras descarta cliente con compras', $sinCompras === []);
    $conSaldo = $service->aplicarFiltroTipo([$fila], 'saldo');
    $check('filtro saldo conserva cliente con saldo', count($conSaldo) === 1);

    // --- conteos ---
    $conteos = $service->conteos(0, $tipoCambio);
    $check('conteos trae claves', isset($conteos['total'], $conteos['conCompras'], $conteos['conSaldo'], $conteos['sinCompras']));
    $check('conteos incluye al menos nuestro cliente con compras', $conteos['conCompras'] >= 1 && $conteos['conSaldo'] >= 1);

    // --- detalle ---
    $clienteBasico = $service->cliente($idCliente);
    $check('cliente() devuelve la ficha', $clienteBasico !== null && $clienteBasico['NombreApellido'] === $nombre);
    $check('cliente() devuelve null si no existe', $service->cliente(0) === null);

    $ventas = $service->ventas($idCliente, 0);
    $check('ventas() sin alcance trae ambas', count($ventas) === 2);
    $ventasVendedor = $service->ventas($idCliente, $vendedorId);
    $check('ventas() con alcance del vendedor trae solo la suya', count($ventasVendedor) === 1);

    $detalles = $service->detallesPorVenta($idCliente, 0);
    $check('detallesPorVenta agrupa por venta', isset($detalles[$idVenta]) && count($detalles[$idVenta]) === 1);
    $check('detalle trae el producto', isset($detalles[$idVenta][0]) && (int) $detalles[$idVenta][0]['Cantidad'] === 2);
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
