<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();

$estado = trim((string)($_GET['estado'] ?? ''));
$tipo = trim((string)($_GET['tipo'] ?? ''));
$rows = aiSuggestedActionsContactsRows($pdo, $estado, $tipo);

registrarAuditoria($pdo, 'IA_EXPORTAR_CONTACTOS', 'IA', 'Contactos de acciones IA exportados a CSV.', [
    'estado' => $estado,
    'tipo' => $tipo,
    'cantidad' => count($rows),
]);

$filename = 'contactos_ia_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

foreach ($rows as $row) {
    fputcsv($output, csvFilaSegura([$row['ClienteTelefono'] ?? '']), ';');
}

fclose($output);
exit;
