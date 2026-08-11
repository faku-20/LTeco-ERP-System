<?php
declare(strict_types=1);

$pageTitle = 'Notificaciones | ERP';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/push.php';

requiereAdmin();
$usuario = usuarioActual() ?: [];
$config = pushConfig();
$devices = pushListSubscriptions($pdo, (int)($usuario['IdUsuario'] ?? 0));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main" data-web-push-settings>
    <header class="topbar">
        <div>
            <p class="eyebrow">Configuración</p>
            <h1>Notificaciones push</h1>
            <p>Configurá este dispositivo para recibir nuevas ventas del ecommerce.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('configuracion/index.php') ?>">Volver a configuración</a>
    </header>

    <section class="section-box">
        <div id="web-push-status" class="notice notice--info" role="status">Comprobando compatibilidad del navegador…</div>
        <?php if (!$config['enabled'] || !$config['public_key'] || !$config['private_key']): ?>
            <div class="notice notice--warning">Web Push no está configurado completamente en el servidor.</div>
        <?php endif; ?>
        <div class="form-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" class="btn" data-web-push-activate>Activar notificaciones</button>
            <button type="button" class="btn-secondary" data-web-push-deactivate hidden>Desactivar este dispositivo</button>
            <button type="button" class="btn-secondary" data-web-push-test disabled>Enviar notificación de prueba</button>
        </div>
        <p class="field-help" data-web-push-help></p>
    </section>

    <section class="section-box">
        <h2>Dispositivos de <?= h((string)($usuario['NombreCompleto'] ?? $usuario['Usuario'] ?? 'este usuario')) ?></h2>
        <p class="subtle">Solo se muestran datos operativos; los endpoints y claves de suscripción nunca se exponen.</p>
        <div class="table-wrap mobile-cards">
            <table><thead><tr><th>Dispositivo</th><th>Estado</th><th>Último envío</th><th>Alta</th></tr></thead><tbody>
            <?php foreach ($devices as $device): ?>
                <tr><td data-label="Dispositivo"><?= h(mb_substr((string)($device['UserAgent'] ?? 'Navegador'), 0, 100)) ?></td><td data-label="Estado"><?= (int)$device['Activa'] === 1 ? 'Activo' : 'Inactivo' ?></td><td data-label="Último envío"><?= h((string)($device['UltimoEnvio'] ?? 'Nunca')) ?></td><td data-label="Alta"><?= h((string)$device['FechaAlta']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$devices): ?><tr><td colspan="4">Todavía no hay dispositivos registrados.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="section-box section-box--soft">
        <h2>En teléfonos</h2>
        <p><strong>Android:</strong> usá Chrome, permití notificaciones y configurá el canal de ERP con importancia <strong>Alta</strong> o <strong>Urgente</strong>. Activá sonido, vibración, mostrar como emergente y mostrar en pantalla bloqueada.</p>
        <p>La ruta habitual es <strong>Ajustes del teléfono → Notificaciones → Chrome → ERP</strong> (o el nombre de la PWA) → activar <strong>Permitir sonido</strong>, <strong>Vibración</strong>, <strong>Mostrar como emergente</strong> y <strong>Pantalla bloqueada</strong>. Los nombres pueden variar según fabricante y versión de Android.</p>
        <p><strong>iPhone/iPad:</strong> abrí el panel en Safari, elegí “Agregar a pantalla de inicio”, abrí la PWA instalada y recién ahí activá las notificaciones.</p>
        <p class="field-help">Web Push no puede crear ni elevar por sí mismo la importancia de un canal Android como una aplicación nativa. No molestar, Focus, ahorro de batería y los ajustes del sistema pueden limitar sonido, vibración o encendido de pantalla.</p>
    </section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
