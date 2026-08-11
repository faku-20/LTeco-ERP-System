<?php
$pageTitle = "Configuración | Ltecobike";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/../includes/whatsapp.php";

$esSuperadminConfig = function_exists('esSuperadmin') && esSuperadmin();

$waCfg = [
    'enabled' => false,
    'phone_id' => '',
    'token' => '',
    'tpl_venta' => '',
    'tpl_service' => '',
];

if ($esSuperadminConfig) {
    // Asegurar columnas de WhatsApp en configuracion y tabla de notificaciones
    whatsappEnsureColumnas($pdo);
    whatsappEnsureTabla($pdo);
    $waCfg = whatsappObtenerConfig($pdo);
}

$configService = new \Lteco\Application\Configuracion\ConfiguracionService(
    new \Lteco\Infrastructure\Repository\ConfiguracionRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

try {
    $config = $configService->obtenerConfiguracion() ?? false;

    if (!$config) {
        $configService->crearConfiguracionDefault();
        $config = $configService->obtenerConfiguracion() ?? false;
    }

    $empresa = $configService->obtenerEmpresa() ?? false;
} catch (Throwable $e) {
    $config = [];
    $empresa = false;
    logPanelError('configuracion_cargar', $e);
    setFlash('error', mensajeErrorSeguro($e, 'No se pudo cargar la configuración.'));
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Configuración</h1>

<?php if ($esSuperadminConfig): ?>
    <div class="lteco-config-header-actions">
            <a class="btn btn-secondary" href="<?= panelBaseUrl('configuracion/mantenimiento/index.php') ?>">
                Mantenimiento
            </a>
            <a class="btn btn-secondary" href="<?= panelBaseUrl('configuracion/notificaciones.php') ?>">
                Notificaciones
            </a>
    </div>
<?php endif; ?>

            <p class="subtle">Datos públicos de <?= htmlspecialchars(appName()) ?> y parámetros de negocio usados por el panel.</p>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <div class="section-box">
        <form method="POST" action="<?= panelBaseUrl('configuracion/guardar.php') ?>">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre de la empresa</label>
                    <input type="text" name="nombre_empresa" value="<?= h($empresa['Nombre'] ?? $config['NombreEmpresa'] ?? appName(), '') ?>" required>
                </div>

                <div class="form-group">
                    <label>RUT</label>
                    <input type="text" name="rut" value="<?= h($empresa['RUT'] ?? defaultEmpresaRut(), '') ?>" maxlength="40">
                </div>

                <div class="form-group">
                    <label>Razón social</label>
                    <input type="text" name="razon_social" value="<?= h($empresa['RazonSocial'] ?? '', '') ?>" maxlength="160">
                </div>

                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" value="<?= h($empresa['Correo'] ?? '', '') ?>">
                </div>

                <div class="form-group">
                    <label>Teléfono / WhatsApp</label>
                    <input type="text" name="telefono" value="<?= h($empresa['Telefono'] ?? $empresa['WhatsApp'] ?? '', '') ?>">
                    <small class="field-help">Se usa como teléfono principal y para mensajes automáticos.</small>
                </div>

                <div class="form-group full">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?= h($empresa['Direccion'] ?? '', '') ?>" maxlength="255">
                </div>


                <div class="form-group full">
                    <label>Descripción pública</label>
                    <textarea name="descripcion"><?= h($empresa['Descripcion'] ?? '', '') ?></textarea>
                </div>
            </div>

            <?php if ($esSuperadminConfig): ?>
            <hr style="margin:2rem 0;">

            <details style="border:1px solid var(--border-color, #ddd); border-radius:14px; padding:1rem; margin-bottom:1.5rem;">
                <summary style="cursor:pointer; font-weight:700;">
                    WhatsApp Cloud API
                    <small class="muted" style="display:block; font-weight:400;">
                        Integración directa con Meta — solo Superadmin. Cerrado por seguridad.
                    </small>
                </summary>

                <p class="subtle" style="margin:1rem 0 1.5rem;">
                    Con <strong>Activado = No</strong> ningún mensaje se envía aunque el resto esté configurado.
                    El token guardado no se muestra; escribí uno nuevo solo si querés reemplazarlo.
                </p>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Activado</label>
                        <select name="wa_enabled">
                            <option value="0" <?= !$waCfg['enabled'] ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= $waCfg['enabled'] ? 'selected' : '' ?>>Sí</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Phone Number ID <small class="muted">(de Meta for Developers)</small></label>
                        <input type="text" name="wa_phone_id"
                               value="<?= h($waCfg['phone_id'], '') ?>"
                               placeholder="123456789012345" maxlength="80">
                    </div>

                    <div class="form-group full">
                        <label>Access Token <small class="muted">(token permanente de sistema)</small></label>
                        <input type="password" name="wa_token"
                               value=""
                               placeholder="<?= !empty($waCfg['token']) ? 'Token guardado. Escribí uno nuevo para reemplazarlo.' : 'EAAxxxxxxx...' ?>"
                               autocomplete="new-password">
                        <small class="muted">Si dejás el campo vacío, se conserva el token guardado anteriormente.</small>
                    </div>

                    <div class="form-group">
                        <label>Template confirmación de venta</label>
                        <input type="text" name="wa_tpl_venta"
                               value="<?= h($waCfg['tpl_venta'], '') ?>"
                               placeholder="ltecobike_confirmacion_venta" maxlength="100">
                        <small class="muted">Variables: {{1}} nombre, {{2}} modelo, {{3}} nº motor, {{4}} fecha</small>
                    </div>

                    <div class="form-group">
                        <label>Template recordatorio de service</label>
                        <input type="text" name="wa_tpl_service"
                               value="<?= h($waCfg['tpl_service'], '') ?>"
                               placeholder="ltecobike_recordatorio_service" maxlength="100">
                        <small class="muted">Variables: {{1}} nombre, {{2}} modelo, {{3}} fecha service, {{4}} nº service</small>
                    </div>
                </div>
            </details>
<?php endif; ?>

              <div class="form-actions">
                <button type="submit" class="btn">Guardar configuración</button>
            </div>
        </form>
    </div>
</main>

<?php
require_once __DIR__ . "/../includes/footer.php"; ?>
