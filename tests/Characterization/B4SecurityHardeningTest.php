<?php

declare(strict_types=1);

final class B4SecurityHardeningTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $auth = (string)file_get_contents($base . '/src/Presentation/Panel/Support/auth.php');
        $login = (string)file_get_contents($base . '/lteco-panel/login.php');
        $mfaVerify = (string)file_get_contents($base . '/lteco-panel/mfa_verificar.php');
        $mfaScreen = (string)file_get_contents($base . '/lteco-panel/usuarios/mfa.php');
        $n8n = (string)file_get_contents($base . '/src/Presentation/Panel/Support/n8n.php');
        $helpers = (string)file_get_contents($base . '/src/Presentation/Panel/Support/helpers.php');

        Assert::isTrue('B4 MFA', 'requiereLogin aplica control MFA privilegiado', str_contains($auth, 'requerirMfaPrivilegiadoSiCorresponde();'));
        Assert::isTrue('B4 MFA', 'detecta setup obligatorio', str_contains($auth, 'function usuarioMfaSetupRequired'));
        Assert::isTrue('B4 MFA', 'valida sesion MFA verificada', str_contains($auth, 'function usuarioMfaVerificadoEnSesion'));
        Assert::isTrue('B4 MFA', 'redirige privilegiado sin MFA a enrollment propio', str_contains($auth, "panelBaseUrl('usuarios/mfa.php')") && str_contains($auth, 'setup_required'));
        Assert::isTrue('B4 MFA', 'privilegiado con MFA activo sin verificacion debe reloguear', str_contains($auth, 'Ingresá nuevamente y completá MFA'));
        Assert::isTrue('B4 MFA', 'login conserva challenge TOTP si MFA esta activo', str_contains($login, "mfa_pending"));
        Assert::isTrue('B4 MFA', 'sesion conserva marcador MFA configurado sin secreto TOTP', str_contains($login, "'mfa_enabled' => (int)(\$usuario['mfa_enabled'] ?? 0)") && str_contains($login, "'mfa_secret' => !empty(\$usuario['mfa_secret']) ? 'configured' : null"));
        Assert::isTrue('B4 MFA', 'login redirige setup MFA si privileged sin MFA', str_contains($login, "panelBaseUrl('usuarios/mfa.php')") && str_contains($login, 'setup_required'));
        Assert::isTrue('B4 MFA', 'verificacion TOTP marca sesion verificada', str_contains($mfaVerify, "\$_SESSION['mfa_verified'] = true"));
        Assert::isTrue('B4 MFA', 'pantalla MFA soporta setup obligatorio', str_contains($mfaScreen, '$setupRequired'));
        Assert::isTrue('B4 rate limit', 'login bloquea tras 5 intentos en 15 minutos', str_contains($login, '$intentosRecientes >= 5') && str_contains($login, '15 * 60'));
        Assert::isTrue('B4 rate limit', 'MFA corta tras 5 codigos fallidos', str_contains($mfaVerify, '$intentosFallidos >= 5') && str_contains($mfaVerify, 'Demasiados intentos fallidos'));

        Assert::isTrue('B4 n8n auth', 'usa header X-Lteco-N8n-Token', str_contains($n8n, 'HTTP_X_LTECO_N8N_TOKEN'));
        Assert::isFalse('B4 n8n auth', 'no acepta token por query string', str_contains($n8n, '$_GET[\'token\']') || str_contains($n8n, '$_GET["token"]'));
        Assert::isTrue('B4 n8n SSRF', 'valida URL al guardar settings', str_contains($n8n, 'n8nValidateWebhookUrlOrFail($url)'));
        Assert::isTrue('B4 n8n SSRF', 'valida URL antes de dispatch', str_contains($n8n, 'n8nValidateWebhookUrlOrFail((string)$setting[\'WebhookUrl\'])'));
        Assert::isTrue('B4 n8n SSRF', 'solo HTTPS', str_contains($n8n, "\$scheme !== 'https'"));
        Assert::isTrue('B4 n8n SSRF', 'allowlist de hosts', str_contains($n8n, 'function n8nAllowedHosts') && str_contains($n8n, 'LTECO_N8N_ALLOWED_HOSTS'));
        Assert::isTrue('B4 n8n SSRF', 'bloquea rangos privados/reservados', str_contains($n8n, 'FILTER_FLAG_NO_PRIV_RANGE') && str_contains($n8n, 'FILTER_FLAG_NO_RES_RANGE'));
        Assert::isTrue('B4 n8n SSRF', 'no sigue redirects', str_contains($n8n, 'CURLOPT_FOLLOWLOCATION => false') && str_contains($n8n, 'CURLOPT_MAXREDIRS => 0'));

        Assert::isTrue('B4 logs', 'logPanelError redakta secretos', str_contains($helpers, 'redactSensitiveLogValue($message)') && str_contains($helpers, 'redactSensitiveLogValue($context)'));
        Assert::isTrue('B4 logs', 'n8n redakta payload/respuesta/error', str_contains($n8n, 'redactSensitiveLogValue($responseBody)') && str_contains($n8n, 'redactSensitiveLogValue($payload)'));
        Assert::isTrue('B4 logs', 'patrones cubren authorization/token/cookie/mfa', str_contains($helpers, 'Authorization:') && str_contains($helpers, 'X-Lteco-N8n-Token') && str_contains($helpers, 'Cookie:') && str_contains($helpers, 'mfa'));
    }
}
