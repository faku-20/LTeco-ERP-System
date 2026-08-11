<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/n8n.php';

n8nAuthorizeOrFail();

try {
    $id = n8nStoreClassification($pdo, n8nRequestJson());
    n8nJson(['ok' => true, 'id_accion' => $id]);
} catch (Throwable $e) {
    n8nJson(['ok' => false, 'error' => $e->getMessage()], 400);
}
