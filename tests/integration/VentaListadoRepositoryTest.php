<?php

declare(strict_types=1);

use Lteco\Application\Venta\VentaListadoFiltros;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\VentaListadoRepository;

require_once dirname(__DIR__, 2) . '/shared/db.php';

$repository = new VentaListadoRepository(new Connection($pdo));
$filtros = VentaListadoFiltros::desdeInput([]);

$legacyPantalla = $pdo->query("
    SELECT
        v.IdVenta,
        v.FechaVenta,
        v.Total,
        v.GananciaEstimada,
        v.Moneda,
        v.TipoCliente,
        v.Observaciones,
        v.MetodoPago,
        v.TipoTarjeta,
        v.MarcaTarjeta,
        v.CuotasTarjeta,
        v.EstadoVenta,
        v.MontoPagado,
        v.SaldoPendiente,
        v.TipoCambioAplicado,
        v.TotalSinIVA,
        c.NombreApellido,
        c.TipoFiscal,
        c.Cedula,
        c.RUT,
        dvend.Nombre AS DistribuidorVendedorNombre,
        uvend.Usuario AS UsuarioVendedorNombre
    FROM venta v
    LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
    LEFT JOIN distribuidor dvend ON dvend.IdDistribuidor = v.DistribuidorVendedorId
    LEFT JOIN usuario uvend ON uvend.IdUsuario = v.UsuarioVendedorId
    ORDER BY v.IdVenta DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$legacyExportacion = $pdo->query("
    SELECT
        v.IdVenta,
        v.FechaVenta,
        c.NombreApellido,
        c.Telefono,
        c.Correo,
        c.TipoFiscal,
        c.Cedula,
        c.Direccion,
        c.RUT,
        v.TipoCliente,
        v.Moneda,
        v.Total,
        v.MontoPagado,
        v.SaldoPendiente,
        v.GananciaEstimada,
        v.MetodoPago,
        v.TipoTarjeta,
        v.MarcaTarjeta,
        v.CuotasTarjeta,
        v.EstadoVenta,
        v.Observaciones,
        v.TipoCambioAplicado
    FROM venta v
    LEFT JOIN cliente c ON c.IdCliente = v.Cliente_IdCliente
    ORDER BY v.IdVenta DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$actualPantalla = $repository->listarPantalla($filtros, null);
$actualExportacion = $repository->listarExportacion($filtros);

$fallos = [];
$assert = static function (bool $condition, string $message) use (&$fallos): void {
    echo ($condition ? '  ✓ ' : '  ✗ ') . $message . "\n";
    if (!$condition) {
        $fallos[] = $message;
    }
};

echo "Comparando VentaListadoRepository con las consultas legacy.\n\n";
$assert($actualPantalla === $legacyPantalla, 'listado de pantalla coincide exactamente');
$assert($actualExportacion === $legacyExportacion, 'exportación coincide exactamente');

$primerVendedor = $pdo->query(
    'SELECT UsuarioVendedorId FROM venta WHERE UsuarioVendedorId IS NOT NULL LIMIT 1'
)->fetchColumn();
if ($primerVendedor !== false) {
    $idVendedor = (int) $primerVendedor;
    $stmtIds = $pdo->prepare(
        'SELECT IdVenta FROM venta WHERE UsuarioVendedorId = ? ORDER BY IdVenta DESC'
    );
    $stmtIds->execute([$idVendedor]);
    $actuales = $repository->listarPantalla($filtros, $idVendedor);
    $idsEsperados = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $idsActuales = array_map('intval', array_column($actuales, 'IdVenta'));
    $assert($idsActuales === $idsEsperados, 'alcance por vendedor conserva IDs y orden');
} else {
    echo "  - alcance por vendedor omitido: no hay ventas con UsuarioVendedorId.\n";
}

// Filtro por cliente: la condición arma 6 campos con el placeholder :cliente.
// Reusar un placeholder nombrado rompe con prepares nativos (HY093). Debe
// ejecutar sin romper y tratar la entrada como texto plano.
$filtrosCliente = VentaListadoFiltros::desdeInput(['cliente' => 'a']);
$assert(is_array($repository->listarPantalla($filtrosCliente, null)), 'filtro por cliente no explota (HY093)');
$assert(is_array($repository->listarExportacion($filtrosCliente)), 'export con filtro por cliente no explota');

$filtrosXss = VentaListadoFiltros::desdeInput(['cliente' => "<script>alert('XSS')</script>"]);
$assert(is_array($repository->listarPantalla($filtrosXss, null)), 'filtro cliente con <script> no explota y se trata como texto');

if ($fallos !== []) {
    fwrite(STDERR, "\nFALLÓ — " . count($fallos) . " verificaciones.\n");
    exit(1);
}

echo "\nOK — integración de listado pasó.\n";
