<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/flash.php';
renderFlashToast();
?>
</div>
<script nonce="<?= cspNonce() ?>">
window.LTECO_PANEL_BASE = '<?= rtrim(panelBaseUrl(''), '/') ?>';

document.addEventListener('click', function (event) {
    const flashClose = event.target.closest('.lteco-flash__close');
    if (flashClose) {
        flashClose.closest('.lteco-flash')?.remove();
        return;
    }

    const printButton = event.target.closest('[data-print-page]');
    if (printButton) {
        window.print();
    }
});

document.addEventListener('submit', function (event) {
    const form = event.target.closest('form[data-confirm]');
    if (!form) return;

    if (!window.confirm(form.dataset.confirm || '¿Confirmás esta acción?')) {
        event.preventDefault();
    }
});
</script>
<script src="<?= panelBaseUrl('assets/js/app.js') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/js/app.js') ?>"></script>
<script src="<?= panelBaseUrl('assets/js/ui-v4.js') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/js/ui-v4.js') ?>"></script>
<?php if (function_exists('esAdmin') && esAdmin()): ?>
<script nonce="<?= cspNonce() ?>">
window.LTECO_VISIT_ALERTS = <?= json_encode([
    'enabled' => true,
    'endpoint' => panelBaseUrl('api/alerts/latest.php'),
    'agendaUrl' => panelBaseUrl('agenda/index.php'),
    'alertsUrl' => panelBaseUrl('notificaciones/index.php'),
    'iconUrl' => panelBaseUrl('assets/img/logo.png'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.LTECO_WEB_PUSH = <?= json_encode([
    'enabled' => in_array(strtolower((string)configEnv('LTECO_WEB_PUSH_ENABLED', '0')), ['1', 'true', 'yes', 'on'], true),
    'publicKey' => (string)configEnv('LTECO_WEB_PUSH_VAPID_PUBLIC_KEY', ''),
    'subscribeUrl' => panelBaseUrl('api/push/subscribe.php'),
    'unsubscribeUrl' => panelBaseUrl('api/push/unsubscribe.php'),
    'testUrl' => panelBaseUrl('api/push/test.php'),
    'csrfToken' => csrfToken(),
    'iconUrl' => panelBaseUrl('assets/img/logo.png'),
    'agendaUrl' => panelBaseUrl('agenda/index.php'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= panelBaseUrl('assets/js/visit-alerts.js') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/js/visit-alerts.js') ?>"></script>
<script src="<?= panelBaseUrl('assets/js/web-push.js') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/js/web-push.js') ?>"></script>
<?php endif; ?>
</body>
</html>
