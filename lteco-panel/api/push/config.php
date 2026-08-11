<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';

requiereAdmin();
$config = pushConfig();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok' => true, 'enabled' => pushIsConfigured(), 'public_key' => $config['public_key']], JSON_UNESCAPED_SLASHES);
