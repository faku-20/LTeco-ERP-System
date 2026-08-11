<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$tipoCambio = obtenerTipoCambioUSD($pdo);

$filtros = \Lteco\Application\Venta\VentaListadoFiltros::desdeInput($_GET);
$repository = new \Lteco\Infrastructure\Repository\VentaListadoRepository(
    new \Lteco\Infrastructure\Db\Connection($pdo)
);
$rows = $repository->listarExportacion($filtros);

$filename = 'ventas_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'IdVenta',
    'FechaVenta',
    'Cliente',
    'TipoFiscal',
    'Telefono',
    'Correo',
    'Cedula',
    'Direccion',
    'RUT',
    'TipoCliente',
    'Moneda',
    'Total',
    'Total_UYU',
    'MontoPagado',
    'SaldoPendiente',
    'GananciaEstimada',
    'MetodoPago',
    'TipoTarjeta',
    'MarcaTarjeta',
    'CuotasTarjeta',
    'EstadoVenta',
    'Observaciones'
], ';');

foreach ($rows as $row) {
    fputcsv($output, csvFilaSegura([
        $row['IdVenta'] ?? '',
        $row['FechaVenta'] ?? '',
        $row['NombreApellido'] ?? '',
        $row['TipoFiscal'] ?? '',
        $row['Telefono'] ?? '',
        $row['Correo'] ?? '',
        $row['Cedula'] ?? '',
        $row['Direccion'] ?? '',
        $row['RUT'] ?? '',
        $row['TipoCliente'] ?? '',
        $row['Moneda'] ?? 'UYU',
        $row['Total'] ?? 0,
        convertirMontoVentaAUyu((float)($row['Total'] ?? 0), $row['Moneda'] ?? 'UYU', $row, $tipoCambio),
        $row['MontoPagado'] ?? 0,
        $row['SaldoPendiente'] ?? 0,
        $row['GananciaEstimada'] ?? 0,
        $row['MetodoPago'] ?? '',
        $row['TipoTarjeta'] ?? '',
        $row['MarcaTarjeta'] ?? '',
        $row['CuotasTarjeta'] ?? '',
        $row['EstadoVenta'] ?? '',
        $row['Observaciones'] ?? '',
    ]), ';');
}

fclose($output);
exit;
