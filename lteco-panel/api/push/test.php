<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';

requiereAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    requirePost();
    $token = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }
    $last = (int)($_SESSION['push_test_at'] ?? 0);
    if ($last > 0 && time() - $last < 60) {
        throw new RuntimeException('Esperá unos segundos antes de enviar otra prueba.');
    }
    $_SESSION['push_test_at'] = time();
    $idUsuario = (int)(usuarioActual()['IdUsuario'] ?? 0);
    $idEvent = pushCreateTestEvent($pdo, $idUsuario);
    $result = pushDispatchAutomationEvent($pdo, $idEvent);
    if (!empty($result['ok'])) {
        $pdo->prepare("UPDATE automation_event SET Status='processed',FechaProcesado=NOW(),ErrorMessage=NULL WHERE IdEvent=?")->execute([$idEvent]);
    }
    echo json_encode(['ok' => !empty($result['ok']), 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
