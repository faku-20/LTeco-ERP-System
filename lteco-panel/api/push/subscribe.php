<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push.php';

requiereAdmin();
header('Content-Type: application/json; charset=utf-8');
try {
    requirePost();
    $token = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($token === '' || !hash_equals(csrfToken(), $token)) {
        throw new RuntimeException('Token CSRF inválido o vencido.');
    }
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new RuntimeException('JSON inválido.');
    }
    $id = pushSaveSubscription($pdo, (int)(usuarioActual()['IdUsuario'] ?? 0), $data, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    echo json_encode(['ok' => true, 'id_subscription' => $id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
