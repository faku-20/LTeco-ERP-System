<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/n8n.php';

n8nAuthorizeOrFail();

try {
    $data = n8nRequestJson();
    $id = (int)($data['id_event'] ?? $data['id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Evento inválido.');
    }
    n8nAcknowledgeEvent($pdo, $id, (string)($data['status'] ?? 'processed'), isset($data['error']) ? (string)$data['error'] : null);
    n8nJson(['ok' => true]);
} catch (Throwable $e) {
    n8nJson(['ok' => false, 'error' => $e->getMessage()], 400);
}
