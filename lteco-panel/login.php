<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

if (usuarioLogueado()) {
    redirect(esSuperadmin() ? panelBaseUrl('dashboard.php') : panelBaseUrl('inicio.php'));
}

$error = '';
$loginErrorGenerico = 'Credenciales inválidas. Vuelva intentarlo nuevamente.';
$seguridad = seguridadService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        $error = 'La sesión del formulario venció. Volvé a intentar.';
    }

    if ($error === '') {
        $usuarioIngresado = trim((string)($_POST['usuario'] ?? ''));
        $claveIngresada = (string)($_POST['clave'] ?? '');
        $ipLogin = ipClienteSegura();
        $bloqueadoHasta = loginBloqueadoHasta($pdo, $ipLogin, $usuarioIngresado);
        $intentosRecientes = loginIntentosFallidosRecientes($pdo, $ipLogin, $usuarioIngresado);

        if ($bloqueadoHasta !== null || $intentosRecientes >= 5) {
            $bloqueadoHasta = $bloqueadoHasta ?? date('Y-m-d H:i:s', time() + 15 * 60);
            registrarIntentoLogin($pdo, $usuarioIngresado, 'bloqueado', $intentosRecientes, $bloqueadoHasta);
            registrarAuditoria($pdo, 'LOGIN_BLOQUEADO', 'Seguridad', 'Intento de login bloqueado temporalmente', [
                'usuario' => $usuarioIngresado,
                'ip' => $ipLogin,
            ]);
            $error = $loginErrorGenerico;
        }
    }

    if ($error === '' && !validarTurnstileLogin()) {
        registrarIntentoLogin($pdo, (string)($_POST['usuario'] ?? ''), 'fallido_turnstile', 0, null);
        registrarAuditoria($pdo, 'LOGIN_FALLIDO_TURNSTILE', 'Seguridad', 'Falló la validación Turnstile del login', [
            'usuario' => trim((string)($_POST['usuario'] ?? '')),
            'ip' => ipClienteSegura(),
        ]);
        $error = $loginErrorGenerico;
    }

    if ($error === '') {
        $usuarioIngresado = trim((string)($_POST['usuario'] ?? ''));
        $claveIngresada = (string)($_POST['clave'] ?? '');
        $ipLogin = ipClienteSegura();

        $usuario = $seguridad->usuarioPorLogin($usuarioIngresado);

        if (!$usuario || (int)($usuario['Activo'] ?? 0) !== 1 || !password_verify($claveIngresada, (string)$usuario['ClaveHash'])) {
            $intentosRecientes = loginIntentosFallidosRecientes($pdo, $ipLogin, $usuarioIngresado) + 1;
            $bloqueadoHasta = $intentosRecientes >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            registrarIntentoLogin($pdo, $usuarioIngresado, 'fallido', $intentosRecientes, $bloqueadoHasta);

            registrarAuditoria($pdo, 'LOGIN_FALLIDO', 'Seguridad', 'Intento de login fallido', [
                'usuario' => $usuarioIngresado,
                'ip' => $ipLogin,
            ]);

            $error = $loginErrorGenerico;
        } else {
            session_regenerate_id(true);

            $usuarioSesion = [
                'IdUsuario' => (int)$usuario['IdUsuario'],
                'NombreCompleto' => (string)$usuario['NombreCompleto'],
                'Usuario' => (string)$usuario['Usuario'],
                'Rol' => rolNormalizado((string)$usuario['Rol']),
                'IdDistribuidor' => $usuario['IdDistribuidor'] !== null ? (int)$usuario['IdDistribuidor'] : null,
                'mfa_enabled' => (int)($usuario['mfa_enabled'] ?? 0),
                'mfa_secret' => !empty($usuario['mfa_secret']) ? 'configured' : null,
            ];

            if (usuarioRequiereMfa($usuario) && usuarioTieneMfaActivo($usuario)) {
                registrarIntentoLogin($pdo, $usuarioIngresado, 'mfa_pendiente', 0, null);
                $_SESSION['mfa_pending'] = [
                    'usuario' => $usuarioSesion,
                    'mfa_secret' => mfaSecretReveal((string)$usuario['mfa_secret']),
                    'mfa_recovery_codes' => (string)($usuario['mfa_recovery_codes'] ?? ''),
                    'created_at' => time(),
                ];

                registrarAuditoria($pdo, 'MFA_REQUERIDO', 'Seguridad', 'Segundo factor requerido para login', [
                    'usuario' => (string)$usuario['Usuario'],
                    'ip' => $ipLogin,
                ]);

                redirect(panelBaseUrl('mfa_verificar.php'));
            }

            registrarIntentoLogin($pdo, $usuarioIngresado, 'exitoso', 0, null);
            $_SESSION['usuario'] = $usuarioSesion;
            $_SESSION['created_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['mfa_verified'] = !usuarioRequiereMfa($usuarioSesion);
            $_SESSION['mfa_verified_at'] = $_SESSION['mfa_verified'] ? time() : null;

            registrarAuditoria($pdo, 'LOGIN_OK', 'Seguridad', 'Inicio de sesión correcto', [
                'usuario' => (string)$usuario['Usuario'],
                'rol' => rolNormalizado((string)$usuario['Rol']),
                'ip' => $ipLogin,
            ]);

            $rolLogin = rolNormalizado((string)$usuario['Rol']);
            $destino = usuarioMfaSetupRequired($usuario)
                ? panelBaseUrl('usuarios/mfa.php') . '?' . http_build_query(['id' => (int)$usuario['IdUsuario'], 'setup_required' => 1])
                : ($rolLogin === ROL_SUPERADMIN ? panelBaseUrl('dashboard.php') : panelBaseUrl('inicio.php'));
            redirect($destino);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars(appName()) ?></title>
    <link rel="shortcut icon" href="<?= panelBaseUrl('assets/img/logo.ico') ?>" type="image/x-icon">

    <script nonce="<?= cspNonce() ?>">
        (function () {
            try {
                var stored = localStorage.getItem('lteco-panel-theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = stored || (prefersDark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <link rel="stylesheet" href="<?= panelBaseUrl('assets/css/lteco.css') ?>?v=<?= @filemtime(__DIR__ . '/assets/css/lteco.css') ?>">
    <?php if (turnstileEnabled()): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <!-- Ltecobike PWA -->
    <link rel="manifest" href="/lteco-panel/assets/manifest.json">
    <meta name="theme-color" content="#22c55e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Ltecobike Panel">
    <script nonce="<?= cspNonce() ?>">
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/lteco-panel/sw.js?v=20260801-push-attention').catch(function (error) {
            console.warn('Ltecobike PWA SW error:', error);
          });
        });
      }
    </script>

</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-brand">
            <img src="<?= panelBaseUrl('assets/img/logo.png') ?>" alt="<?= htmlspecialchars(appName()) ?>">
            <h1><?= htmlspecialchars(appName()) ?></h1>
            <p>Panel interno · acceso restringido</p>
        </div>

        <?php if ($error): ?>
            <div class="notice notice--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" style="display:flex;flex-direction:column;gap:14px;">
            <?= csrfInput() ?>

            <div class="form-group">
                <label for="login-usuario">Usuario</label>
                <input id="login-usuario" type="text" name="usuario" autocomplete="username" required>
            </div>

            <div class="form-group">
                <label for="login-clave">Contraseña</label>
                <input id="login-clave" type="password" name="clave" autocomplete="current-password" required>
            </div>

            <?php if (turnstileEnabled()): ?>
                <div class="cf-turnstile" data-sitekey="<?= h(turnstileSiteKey(), '') ?>"></div>
            <?php endif; ?>

            <button type="submit" class="btn btn-block" style="margin-top:6px;">Ingresar</button>
        </form>
    </div>

    <script src="<?= panelBaseUrl('assets/js/ui-v4.js') ?>?v=<?= @filemtime(__DIR__ . '/assets/js/ui-v4.js') ?>"></script>
</body>
</html>
