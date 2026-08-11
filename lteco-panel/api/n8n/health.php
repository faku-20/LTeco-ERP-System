<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/n8n.php';

n8nAuthorizeOrFail();
n8nJson(n8nHealth($pdo));
