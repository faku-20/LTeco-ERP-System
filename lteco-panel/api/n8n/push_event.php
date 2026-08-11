<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/n8n.php';
require_once __DIR__ . '/../../includes/push.php';

n8nAuthorizeOrFail();
$id = 0;
try {
    $data = n8nRequestJson();
    $id = (int)($data['id_event'] ?? $data['id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Evento inválido.');
    }
    $result = pushDispatchAutomationEvent($pdo, $id);
    if ($result['ok']) {
        n8nAcknowledgeEvent($pdo, $id, 'processed');
    }
    n8nJson($result, $result['ok'] ? 200 : 409);
} catch (Throwable $e) {
    if ($id > 0) {
        pushReleaseAutomationEvent($pdo, $id, $e->getMessage());
    }
    n8nJson(['ok' => false, 'error' => $e->getMessage()], 400);
}
