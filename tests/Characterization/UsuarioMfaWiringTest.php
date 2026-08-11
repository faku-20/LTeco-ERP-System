<?php

declare(strict_types=1);

/**
 * Wiring F3: usuarios/mfa.php delega la persistencia en UsuarioMfaService, no
 * conserva SQL inline y mantiene TODA la criptografía MFA en el handler
 * (generación de secreto, protección, recovery codes).
 */
final class UsuarioMfaWiringTest
{
    public static function run(): void
    {
        $source = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/usuarios/mfa.php');

        Assert::isTrue('Wiring MFA usuario', 'mfa.php legible', $source !== '');
        Assert::isTrue('Wiring MFA usuario', 'usa UsuarioMfaService', strpos($source, 'UsuarioMfaService') !== false);
        Assert::same('Wiring MFA usuario', 'delega la lectura', 1, substr_count($source, '->obtener('));
        Assert::same('Wiring MFA usuario', 'delega activar', 1, substr_count($source, '->activar('));
        Assert::same('Wiring MFA usuario', 'delega desactivar', 1, substr_count($source, '->desactivar('));
        Assert::same('Wiring MFA usuario', 'delega regenerar recovery', 1, substr_count($source, '->regenerarRecovery('));
        Assert::same('Wiring MFA usuario', 'sin SQL inline', 0, preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source));

        // Criptografía: debe seguir en el handler, no en el repositorio/servicio.
        Assert::isTrue('Wiring MFA usuario', 'conserva generación de secreto TOTP', strpos($source, 'totpGenerarSecreto()') !== false);
        Assert::isTrue('Wiring MFA usuario', 'conserva protección del secreto', strpos($source, 'mfaSecretProtect(') !== false);
        Assert::isTrue('Wiring MFA usuario', 'conserva generación de recovery codes', strpos($source, 'generarRecoveryCodes()') !== false);
        Assert::isTrue('Wiring MFA usuario', 'conserva hash de recovery codes', strpos($source, 'hashRecoveryCodes(') !== false);
        Assert::isTrue('Wiring MFA usuario', 'conserva CSRF', strpos($source, 'verifyCsrfOrFail()') !== false);
    }
}
