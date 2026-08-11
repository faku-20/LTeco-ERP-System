<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/agenda.php';

requiereAdmin();
requirePost();
verifyCsrfOrFail();

$limit = (int)($_POST['limite'] ?? 50);
$volver = trim((string)($_POST['volver'] ?? panelBaseUrl('ia/acciones.php')));
if (!str_starts_with($volver, panelBaseUrl(''))) {
    $volver = panelBaseUrl('ia/acciones.php');
}

try {
    $learning = aiSyncConversationExamples($pdo);
    $result = aiAnalyzeSavedConversations($pdo, $limit);
    $agendaResult = agendaAnalyzeSavedConversations($pdo, $limit);
    registrarAuditoria($pdo, 'IA_ANALIZAR_CONVERSACIONES', 'IA', 'Conversaciones analizadas para acciones sugeridas y visitas.', [
        'acciones' => $result,
        'agenda' => $agendaResult,
        'aprendizaje' => $learning,
    ]);
    setFlash('success', 'Historial aprendido: ' . ($learning['created'] + $learning['updated']) . ' respuestas de ' . $learning['total'] . ' enviadas. Conversaciones analizadas: ' . $result['total'] . '. Acciones nuevas: ' . $result['generated'] . '. Visitas agendadas: ' . $agendaResult['scheduled'] . '. Visitas pendientes: ' . $agendaResult['pending'] . '.');
} catch (Throwable $e) {
    setFlash('error', mensajeErrorSeguro($e, 'No se pudieron analizar conversaciones.'));
}

redirect($volver);
