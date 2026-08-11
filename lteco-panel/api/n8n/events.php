<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/n8n.php';

n8nAuthorizeOrFail();
$limit = (int)($_GET['limit'] ?? 50);
n8nJson(['ok' => true, 'events' => n8nEvents($pdo, $limit)]);
