<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once LTECO_SHARED_DIR . '/app_config.php';

if (!isset($pageTitle)) {
    $pageTitle = panelName();
}

$bodyClass = $bodyClass ?? 'lteco-ui-v4';
$identidadPanelHeader = [
    'favicon' => panelBaseUrl('assets/img/logo.ico'),
    'color_primario' => '#22c55e',
];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $configuracionHeader = new \Lteco\Application\Configuracion\ConfiguracionService(
            new \Lteco\Infrastructure\Repository\ConfiguracionRepository(
                new \Lteco\Infrastructure\Db\Connection($pdo)
            )
        );
        $empresaHeader = $configuracionHeader->obtenerEmpresa();
        if ($empresaHeader) {
            $faviconHeader = trim((string)($empresaHeader['Favicon'] ?? ''));
            $colorPrimarioHeader = trim((string)($empresaHeader['ColorPrimario'] ?? ''));
            $identidadPanelHeader['favicon'] = $faviconHeader !== '' ? $faviconHeader : $identidadPanelHeader['favicon'];
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $colorPrimarioHeader) === 1) {
                $identidadPanelHeader['color_primario'] = strtolower($colorPrimarioHeader);
            }
        }
    }
} catch (Throwable) {
    $identidadPanelHeader = [
        'favicon' => panelBaseUrl('assets/img/logo.ico'),
        'color_primario' => '#22c55e',
    ];
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($identidadPanelHeader['favicon'], ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">

    <script nonce="<?= cspNonce() ?>">
        // Aplica el tema guardado lo antes posible para evitar flash blanco
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

    <link rel="stylesheet" href="<?= panelBaseUrl('assets/css/lteco.css') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/css/lteco.css') ?>">

    <script src="<?= panelBaseUrl('assets/js/ui-v4-alerts.js') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/js/ui-v4-alerts.js') ?>"></script>
    <script defer src="<?= panelBaseUrl('assets/js/panel-history-back.js') ?>?v=<?= @filemtime(LTECO_PANEL_PUBLIC_DIR . '/assets/js/panel-history-back.js') ?>"></script>
    <!-- Panel PWA -->
    <link rel="manifest" href="/lteco-panel/assets/manifest.json">
    <meta name="theme-color" content="<?= htmlspecialchars($identidadPanelHeader['color_primario'], ENT_QUOTES, 'UTF-8') ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?= htmlspecialchars(panelName(), ENT_QUOTES, 'UTF-8') ?>">
    <script nonce="<?= cspNonce() ?>">
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/lteco-panel/sw.js?v=20260801-push-attention').catch(function (error) {
            console.warn('Panel PWA SW error:', error);
          });
        });
      }
    </script>

</head>
<body class="<?= htmlspecialchars($bodyClass) ?>" data-panel-back-fallback="<?= htmlspecialchars(panelBaseUrl('inicio.php'), ENT_QUOTES, 'UTF-8') ?>">
<div class="app shell-v4">
