<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

if (usuarioLogueado()) {
    redirect(panelBaseUrl(esSuperadmin() ? 'dashboard.php' : 'inicio.php'));
}

$pendiente = $_SESSION['mfa_pending'] ?? null;
if (!is_array($pendiente) || empty($pendiente['usuario']) || (time() - (int)($pendiente['created_at'] ?? 0)) > 300) {
    unset($_SESSION['mfa_pending']);
    redirectWithFlash(panelBaseUrl('login.php'), 'error', 'La verificación MFA venció. Volvé a ingresar.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();

        $codigo = trim((string)($_POST['codigo'] ?? ''));
        $usuarioPendiente = $pendiente['usuario'];
        $idUsuario = (int)($usuarioPendiente['IdUsuario'] ?? 0);
        $ok = totpVerificarCodigo((string)$pendiente['mfa_secret'], $codigo)
            || consumirRecoveryCode($pdo, $idUsuario, $codigo, (string)($pendiente['mfa_recovery_codes'] ?? ''));

        if (!$ok) {
            $intentosFallidos = (int)($pendiente['mfa_intentos'] ?? 0) + 1;
            registrarAuditoria($pdo, 'MFA_FALLIDO', 'Seguridad', 'Código MFA inválido', [
                'id_usuario' => $idUsuario,
                'ip' => obtenerIpCliente(),
                'intentos' => $intentosFallidos,
            ]);
            if ($intentosFallidos >= 5) {
                unset($_SESSION['mfa_pending']);
                redirectWithFlash(panelBaseUrl('login.php'), 'error', 'Demasiados intentos fallidos. Volvé a ingresar con tus credenciales.');
            }
            $_SESSION['mfa_pending']['mfa_intentos'] = $intentosFallidos;
            throw new RuntimeException('Código inválido.');
        }

        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuarioPendiente;
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['mfa_verified'] = true;
        $_SESSION['mfa_verified_at'] = time();
        unset($_SESSION['mfa_pending']);

        registrarAuditoria($pdo, 'MFA_OK', 'Seguridad', 'Segundo factor validado', [
            'id_usuario' => $idUsuario,
            'ip' => obtenerIpCliente(),
        ]);
        registrarAuditoria($pdo, 'LOGIN_OK', 'Seguridad', 'Inicio de sesión correcto con MFA', [
            'usuario' => (string)($usuarioPendiente['Usuario'] ?? ''),
            'rol' => rolNormalizado((string)($usuarioPendiente['Rol'] ?? ROL_VENDEDOR)),
            'ip' => obtenerIpCliente(),
        ]);

        redirect(panelBaseUrl(rolNormalizado((string)$usuarioPendiente['Rol']) === ROL_SUPERADMIN ? 'dashboard.php' : 'inicio.php'));
    } catch (Throwable $e) {
        $error = 'Código inválido o vencido.';
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA | <?= h(appName(), '') ?></title>
    <link rel="stylesheet" href="<?= panelBaseUrl('assets/css/lteco.css') ?>?v=<?= @filemtime(__DIR__ . '/assets/css/lteco.css') ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-brand">
            <img src="<?= panelBaseUrl('assets/img/logo.png') ?>" alt="<?= h(appName(), '') ?>">
            <h1>Verificación MFA</h1>
            <p>Ingresá el código de tu app autenticadora o un código de recuperación.</p>
        </div>

        <?php if ($error): ?>
            <div class="notice notice--error"><?= h($error, '') ?></div>
        <?php endif; ?>

        <form method="POST" style="display:flex;flex-direction:column;gap:14px;">
            <?= csrfInput() ?>
            <div class="form-group">
                <label for="codigo">Código</label>
                <input id="codigo" type="text" name="codigo" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            </div>
            <button type="submit" class="btn btn-block">Verificar</button>
        </form>
    </div>
</body>
</html>
