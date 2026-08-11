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
$hour = trim((string)($_POST['hora'] ?? ''));
$updated = $id > 0 && agendaConfirmVisitHour($pdo, $id, $hour, usuarioActual() ?: []);
setFlash($updated ? 'success' : 'error', $updated ? 'Hora de la visita confirmada.' : 'No se pudo confirmar la hora de la visita.');
redirect(panelBaseUrl('agenda/index.php'));
