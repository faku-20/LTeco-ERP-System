<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
 
requiereLogin();
requiereAdmin();

require_once __DIR__ . "/../includes/helpers.php";


$tipoCambio = obtenerTipoCambioUSD($pdo);

$service = new \Lteco\Application\Gasto\GastoConsultaService(
    new \Lteco\Infrastructure\Repository\GastoConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$filtroCategoria = trim($_GET['categoria'] ?? '');
$filtroMetodo = trim($_GET['metodo'] ?? '');
$filtroDesde = trim($_GET['desde'] ?? '');
$filtroHasta = trim($_GET['hasta'] ?? '');

$rows = $service->listar([
    'categoria' => $filtroCategoria,
    'metodo' => $filtroMetodo,
    'desde' => $filtroDesde,
    'hasta' => $filtroHasta,
]);

$filename = 'gastos_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, [
    'IdGasto',
    'FechaGasto',
    'Categoria',
    'MetodoPago',
    'Moneda',
    'Monto',
    'Monto_UYU',
    'Concepto',
    'Observaciones'
], ';');

foreach ($rows as $row) {
    fputcsv($output, csvFilaSegura([
        $row['IdGasto'] ?? '',
        $row['FechaGasto'] ?? '',
        $row['Categoria'] ?? '',
        $row['MetodoPago'] ?? '',
        $row['Moneda'] ?? 'UYU',
        $row['Monto'] ?? 0,
        convertirAUyu((float)($row['Monto'] ?? 0), $row['Moneda'] ?? 'UYU', $tipoCambio),
        $row['Concepto'] ?? '',
        $row['Observaciones'] ?? '',
    ]), ';');
}

fclose($output);
exit;
