<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/n8n.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();

try {
    n8nUpdateSettings($pdo, $_POST['settings'] ?? []);
    $eventKey = trim((string)($_POST['event_key'] ?? ''));
    if ($eventKey === '') {
        throw new RuntimeException('Evento inválido.');
    }

    $result = n8nDispatch($pdo, $eventKey, [
        'test' => true,
        'triggered_by' => usuarioActual()['Usuario'] ?? 'panel',
        'message' => 'Prueba n8n desde panel V2',
    ], 'panel_test', null);

    setFlash($result['ok'] ? 'success' : 'warning', $result['ok'] ? 'Webhook enviado a n8n.' : 'Prueba registrada: ' . (string)($result['message'] ?? $result['status']));
} catch (Throwable $e) {
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo probar n8n.'));
}

redirect(panelBaseUrl('n8n/index.php'));
