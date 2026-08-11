<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai.php';

requiereNoDistribuidor();
requirePost();
verifyCsrfOrFail();

$scope = trim((string)($_POST['scope'] ?? 'general'));
$question = trim((string)($_POST['question'] ?? ''));
$scopes = aiScopesForCurrentUser();

if ($question === '' || mb_strlen($question) < 3 || !array_key_exists($scope, $scopes)) {
    setFlash('error', 'Ingresá una pregunta válida.');
    redirect(panelBaseUrl('ia/index.php'));
}

try {
    aiEnforceDailyLimit($pdo, 'ia.preguntar');
    $answer = aiPanelAnswer($pdo, $scope, mb_substr($question, 0, 1200));
    aiLogUsage($pdo, 'ia.preguntar', 'panel_answer', 'success', mb_strlen($question));

    $_SESSION['ai_scope'] = $scope;
    $_SESSION['ai_question'] = $question;
    $_SESSION['ai_answer'] = $answer;
    setFlash('success', 'Respuesta IA generada.');
} catch (Throwable $e) {
    aiLogUsage($pdo, 'ia.preguntar', 'panel_answer', 'error', mb_strlen($question));
    $_SESSION['ai_scope'] = $scope;
    $_SESSION['ai_question'] = $question;
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo consultar la IA.'));
}

redirect(panelBaseUrl('ia/index.php'));
