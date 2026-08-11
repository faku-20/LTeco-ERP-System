<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/agenda.php';

requiereAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode(agendaLatestVisitAlertPayload($pdo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
