<?php
$pageTitle = "MFA de usuario | ERP";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereGestionUsuarios();
require_once __DIR__ . "/../includes/helpers.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario inválido.');
}

$mfaService = new \Lteco\Application\Usuario\UsuarioMfaService(
    new \Lteco\Infrastructure\Repository\UsuarioRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$usuarioData = $mfaService->obtener($id);

if (!$usuarioData) {
    redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'error', 'Usuario no encontrado.');
}

$usuarioData['Rol'] = rolNormalizado($usuarioData['Rol'] ?? ROL_VENDEDOR);
$idActual = (int)(usuarioActual()['IdUsuario'] ?? 0);
$puedeGestionarMfa = esSuperadmin() || ((int)$usuarioData['IdUsuario'] === $idActual && esAdministrador());
if (!$puedeGestionarMfa) {
    denegarAcceso('No tenés permisos para gestionar MFA de ese usuario.');
}

$puedeUsarMfa = usuarioRequiereMfa($usuarioData);
$recoveryCodesPlanos = [];
$setupRequired = !empty($_GET['setup_required']) && (int)$usuarioData['IdUsuario'] === $idActual && usuarioMfaSetupRequired($usuarioData);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();
        $accion = trim((string)($_POST['accion'] ?? ''));

        if (!$puedeUsarMfa) {
            throw new RuntimeException('MFA solo está habilitado para Superadmin y Administrador.');
        }

        if ($accion === 'activar') {
            $secret = totpGenerarSecreto();
            $recoveryCodesPlanos = generarRecoveryCodes();
            $mfaService->activar($id, mfaSecretProtect($secret), hashRecoveryCodes($recoveryCodesPlanos));
            registrarAuditoria($pdo, 'MFA_ACTIVADO', 'Usuarios', 'MFA activado para usuario: ' . (string)$usuarioData['Usuario'], ['id_usuario' => $id]);
            $usuarioData['mfa_enabled'] = 1;
            $usuarioData['mfa_secret'] = $secret;
            if ((int)$usuarioData['IdUsuario'] === $idActual) {
                $_SESSION['usuario']['mfa_enabled'] = 1;
                $_SESSION['usuario']['mfa_secret'] = 'configured';
                unset($_SESSION['mfa_verified'], $_SESSION['mfa_verified_at']);
            }
        } elseif ($accion === 'desactivar') {
            $mfaService->desactivar($id);
            registrarAuditoria($pdo, 'MFA_DESACTIVADO', 'Usuarios', 'MFA desactivado para usuario: ' . (string)$usuarioData['Usuario'], ['id_usuario' => $id]);
            redirectWithFlash(panelBaseUrl('usuarios/index.php'), 'success', 'MFA desactivado correctamente.');
        } elseif ($accion === 'regenerar_recovery') {
            $recoveryCodesPlanos = generarRecoveryCodes();
            $mfaService->regenerarRecovery($id, hashRecoveryCodes($recoveryCodesPlanos));
            registrarAuditoria($pdo, 'MFA_RECOVERY_REGENERADOS', 'Usuarios', 'Recovery codes regenerados para usuario: ' . (string)$usuarioData['Usuario'], ['id_usuario' => $id]);
        }
    } catch (Throwable $e) {
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo actualizar MFA.'));
    }
}

$secretVisible = !empty($usuarioData['mfa_secret']) ? mfaSecretReveal((string)$usuarioData['mfa_secret']) : '';
if ($secretVisible === '' && !empty($usuarioData['mfa_secret']) && !str_starts_with((string)$usuarioData['mfa_secret'], 'enc:v1:')) {
    $secretVisible = (string)$usuarioData['mfa_secret'];
}
$otpauth = $secretVisible !== ''
    ? totpProvisioningUri((string)$usuarioData['Usuario'], $secretVisible)
    : '';

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>MFA de usuario</h1>
            <p class="subtle"><?= h($usuarioData['NombreCompleto'], '') ?> · <?= h($usuarioData['Rol'], '') ?></p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('usuarios/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="v4-card">
        <?php if (!$puedeUsarMfa): ?>
            <div class="notice notice--warning">MFA por ahora se aplica solo a Superadmin y Administrador.</div>
        <?php else: ?>
            <?php if ($setupRequired): ?>
                <div class="notice notice--warning">Tu rol requiere MFA. Activá el segundo factor, guardá los recovery codes y volvé a iniciar sesión para verificarlo.</div>
            <?php endif; ?>
            <p><strong>Estado:</strong> <?= !empty($usuarioData['mfa_enabled']) ? 'Activo' : 'Inactivo' ?></p>

            <?php if (!empty($usuarioData['mfa_enabled']) && $otpauth !== ''): ?>
                <div class="mfa-setup-grid">
                    <div class="mfa-setup-col">
                        <div id="mfa-qr" class="mfa-qr"></div>
                    </div>

                    <div class="mfa-setup-col">
                        <p class="mfa-secret-line">
                            <strong>Secreto:</strong>
                            <code><?= h($secretVisible, '') ?></code>
                        </p>
                    </div>
                </div>

                <script src="<?= panelBaseUrl('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
                <script nonce="<?= cspNonce() ?>">
                    new QRCode(document.getElementById('mfa-qr'), {
                        text: <?= json_encode($otpauth) ?>,
                        width: 220,
                        height: 220
                    });
                </script>
            <?php endif; ?>

            <?php if ($recoveryCodesPlanos): ?>
                <div class="notice notice--warning">
                    Guardá estos códigos de recuperación ahora. No se vuelven a mostrar.
                    <pre style="white-space:pre-wrap;margin-top:10px;"><?= h(implode("\n", $recoveryCodesPlanos), '') ?></pre>
                </div>
            <?php endif; ?>

            <div class="actions-row">
                <?php if (empty($usuarioData['mfa_enabled'])): ?>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <input type="hidden" name="accion" value="activar">
                        <button type="submit" class="btn">Activar MFA</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <input type="hidden" name="accion" value="regenerar_recovery">
                        <button type="submit" class="btn-secondary">Regenerar recovery codes</button>
                    </form>
                    <form method="POST" data-confirm="¿Desactivar MFA para este usuario?">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <input type="hidden" name="accion" value="desactivar">
                        <button type="submit" class="btn-danger-outline">Desactivar MFA</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
