<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();

$id = (int)($_POST['id'] ?? 0);
$accion = trim((string)($_POST['accion'] ?? ''));
if ($id <= 0 || !in_array($accion, ['confirmar', 'rechazar', 'ejecutar'], true)) {
    setFlash('error', 'Acción inválida.');
    redirect(panelBaseUrl('ia/acciones.php'));
}

try {
    $resultado = aiProcessSuggestedAction($pdo, $id, $accion);
    registrarAuditoria($pdo, 'IA_ACCION_' . strtoupper($accion), 'IA', 'Acción IA #' . $id . ' procesada.', ['id_accion' => $id, 'accion' => $accion]);
    setFlash('success', $resultado);
} catch (Throwable $e) {
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo procesar la acción IA.'));
}

redirect(panelBaseUrl('ia/acciones.php'));
