<?php

declare(strict_types=1);

/**
 * B4 - Hardening de seguridad sin mutar datos comerciales.
 */

$root = dirname(__DIR__, 2);

if (($argv[1] ?? '') === '--auth-worker') {
    putenv('LTECO_N8N_WEBHOOK_TOKEN=b4-secret');
    $_SERVER['HTTP_X_LTECO_N8N_TOKEN'] = '';
    $_SERVER['HTTP_AUTHORIZATION'] = '';
    $_GET = [];
    if (($argv[2] ?? '') === 'query') {
        $_GET['token'] = 'b4-secret';
    }
    if (($argv[2] ?? '') === 'header') {
        $_SERVER['HTTP_X_LTECO_N8N_TOKEN'] = 'b4-secret';
    }
    if (($argv[2] ?? '') === 'bearer') {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer b4-secret';
    }
    require_once $root . '/lteco-panel/includes/helpers.php';
    require_once $root . '/src/Presentation/Panel/Support/n8n.php';
    n8nAuthorizeOrFail();
    echo 'OK';
    exit(0);
}

require_once $root . '/lteco-panel/includes/helpers.php';
require_once $root . '/src/Presentation/Panel/Support/auth.php';
require_once $root . '/src/Presentation/Panel/Support/n8n.php';

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  OK {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  FAIL {$nombre}\n";
};

function b4ExpectThrows(string $nombre, callable $fn, callable $match, callable $check): void
{
    try {
        $fn();
        $check($nombre, false);
    } catch (Throwable $e) {
        $check($nombre, $match($e));
    }
}

function b4RunAuthWorker(string $mode): string
{
    $cmd = PHP_BINARY . ' ' . escapeshellarg(__FILE__) . ' --auth-worker ' . escapeshellarg($mode);
    return (string)shell_exec($cmd);
}

echo "B4 MFA/logging\n";
$_SESSION = [];
$check('Superadmin requiere MFA', usuarioRequiereMfa(['Rol' => 'Superadmin']));
$check('Administrador requiere MFA', usuarioRequiereMfa(['Rol' => 'Administrador']));
$check('Vendedor no requiere MFA', !usuarioRequiereMfa(['Rol' => 'Vendedor']));
$check('Privilegiado sin secreto queda en setup requerido', usuarioMfaSetupRequired(['Rol' => 'Administrador', 'mfa_enabled' => 0, 'mfa_secret' => null]));
$_SESSION['mfa_verified'] = true;
$_SESSION['mfa_verified_at'] = time();
$check('Sesion MFA verificada se reconoce', usuarioMfaVerificadoEnSesion());

$redacted = redactSensitiveLogValue([
    'Authorization' => 'Bearer secreto',
    'nested' => ['token' => 'abc123'],
    'message' => 'fallo token=abc123 Cookie: sid=xyz',
]);
$check('redacta clave Authorization', $redacted['Authorization'] === '[REDACTED]');
$check('redacta token anidado', $redacted['nested']['token'] === '[REDACTED]');
$check('redacta secretos embebidos en strings', str_contains($redacted['message'], '[REDACTED]') && !str_contains($redacted['message'], 'abc123') && !str_contains($redacted['message'], 'sid=xyz'));

echo "\nB4 n8n auth\n";
$queryOutput = b4RunAuthWorker('query');
$headerOutput = b4RunAuthWorker('header');
$bearerOutput = b4RunAuthWorker('bearer');
$check('token por querystring se rechaza', str_contains($queryOutput, '"ok":false') && str_contains($queryOutput, 'Token n8n') && !str_contains($queryOutput, 'OK'));
$check('header X-Lteco-N8n-Token se acepta', trim($headerOutput) === 'OK');
$check('Bearer header se acepta como compatibilidad segura', trim($bearerOutput) === 'OK');

echo "\nB4 n8n SSRF\n";
putenv('LTECO_N8N_ALLOWED_HOSTS=n8n.ltecobike.shop,example.com,127.0.0.1');
b4ExpectThrows(
    'rechaza HTTP plano',
    static fn() => n8nValidateWebhookUrlOrFail('http://n8n.ltecobike.shop/webhook/test'),
    static fn(Throwable $e): bool => str_contains($e->getMessage(), 'HTTPS'),
    $check
);
b4ExpectThrows(
    'rechaza host fuera de allowlist',
    static fn() => n8nValidateWebhookUrlOrFail('https://malicious.example.test/hook'),
    static fn(Throwable $e): bool => str_contains($e->getMessage(), 'no está permitido'),
    $check
);
b4ExpectThrows(
    'rechaza IP privada aunque este en allowlist',
    static fn() => n8nValidateWebhookUrlOrFail('https://127.0.0.1/hook'),
    static fn(Throwable $e): bool => str_contains($e->getMessage(), 'IP no permitida'),
    $check
);
$check('bloquea metadata cloud local', n8nIpBloqueada('169.254.169.254'));
$check('no bloquea IP publica conocida', !n8nIpBloqueada('8.8.8.8'));
n8nValidateWebhookUrlOrFail('https://example.com/webhook/test');
$check('acepta HTTPS allowlisted con resolucion publica', true);

if ($fallos !== []) {
    echo "\nFALLO B4: " . count($fallos) . " checks fallaron\n";
    foreach ($fallos as $f) {
        echo ' - ' . $f . "\n";
    }
    exit(1);
}

echo "\nOK B4: {$ok} checks pasaron\n";
