<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/agenda.php';

requiereNoDistribuidor();
requirePost();
verifyCsrfOrFail();

$id = (int)($_POST['id'] ?? 0);
$status = trim((string)($_POST['estado'] ?? ''));
$updated = $id > 0 && agendaUpdateAlertStatus($pdo, $id, $status, usuarioActual() ?: []);
setFlash($updated ? 'success' : 'error', $updated ? 'Alerta actualizada.' : 'No se pudo actualizar la alerta.');
redirect(panelBaseUrl('notificaciones/index.php'));
