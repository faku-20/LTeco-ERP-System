<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/n8n.php';

n8nAuthorizeOrFail();

try {
    n8nJson(n8nIngestInbox($pdo, n8nRequestJson()));
} catch (Throwable $e) {
    n8nJson(['ok' => false, 'error' => $e->getMessage()], 400);
}
