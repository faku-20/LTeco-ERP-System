<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Cliente\ClienteConsultaService(
    new \Lteco\Infrastructure\Repository\ClienteConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$tipoCambio = obtenerTipoCambioUSD($pdo);
$filtroCliente = trim((string)($_GET['cliente'] ?? ''));

$rows = $service->listarParaExport($filtroCliente, (float)$tipoCambio);

$filename = 'clientes_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, [
    'IdCliente',
    'NombreApellido',
    'TipoFiscal',
    'Telefono',
    'Correo',
    'Cedula',
    'Direccion',
    'RUT',
    'FechaCliente',
    'Compras',
    'TotalGastado_UYU',
    'SaldoPendiente_UYU',
    'UltimaCompra'
], ';');

foreach ($rows as $row) {
    fputcsv($output, csvFilaSegura([
        $row['IdCliente'] ?? '',
        $row['NombreApellido'] ?? '',
        $row['TipoFiscal'] ?? '',
        $row['Telefono'] ?? '',
        $row['Correo'] ?? '',
        $row['Cedula'] ?? '',
        $row['Direccion'] ?? '',
        $row['RUT'] ?? '',
        $row['FechaCliente'] ?? '',
        $row['Compras'] ?? 0,
        round((float)($row['TotalGastadoUYU'] ?? 0), 2),
        round((float)($row['SaldoPendienteUYU'] ?? 0), 2),
        $row['UltimaCompra'] ?? '',
    ]), ';');
}

fclose($output);
exit;
