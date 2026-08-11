<?php
require_once __DIR__ . '/../../shared/app_config.php';

if (!isset($pageTitle)) {
    $pageTitle = appName();
}
if (!isset($empresaPublica)) {
    $empresaPublica = [];
}

function resolverLogoPublico(array $empresaPublica): string
{
    $fallback = publicBaseUrl('assets/img/logo-header-cropped.png');
    $logo = trim((string)($empresaPublica['Logo'] ?? ''));

    if ($logo === '') {
        return $fallback;
    }

    if (preg_match('#^(https?:)?//#i', $logo)) {
        return $logo;
    }

    $logo = str_replace('\\', '/', $logo);
    $logo = ltrim($logo, '/');

    if (str_starts_with($logo, 'public-web/')) {
        return '/' . $logo;
    }

    $basenameLogo = strtolower(basename($logo));
    if (in_array($basenameLogo, ['logo.png', 'logosfondo.png', 'logolteco.jpg'], true)) {
        return $fallback;
    }

    if (str_starts_with($logo, 'assets/')) {
        return publicBaseUrl($logo);
    }

    if (str_starts_with($logo, 'uploads/') || str_starts_with($logo, 'lteco-panel/')) {
        return panelBaseUrl($logo);
    }

    return publicBaseUrl('assets/img/' . basename($logo));
}

$logoFallback = publicBaseUrl('assets/img/logo-header-cropped.png');
$logoPublico = resolverLogoPublico($empresaPublica);
$storefrontBase = rtrim((string)configEnv('LTECO_STOREFRONT_PUBLIC_URL', publicBaseUrl('')), '/');
$stylesPath = dirname(__DIR__) . '/assets/css/styles.css';
$stylesVersion = is_file($stylesPath) ? (string) filemtime($stylesPath) : '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= publicBaseUrl('assets/css/styles.css') ?>?v=<?= rawurlencode($stylesVersion) ?>">
    <link rel="shortcut icon" href="<?= publicBaseUrl('assets/img/logo.ico') ?>" type="image/x-icon">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a href="<?= publicBaseUrl('index.php') ?>" class="brand">
            <img
                id="public-brand-logo"
                src="<?= htmlspecialchars($logoPublico) ?>"
                alt="<?= htmlspecialchars($empresaPublica['Nombre'] ?? appName()) ?>"
                class="brand-logo"
                data-fallback-src="<?= htmlspecialchars($logoFallback) ?>"
            >
        </a>

        <nav class="main-nav">
            <a class="<?= rutaActivaPublic('index.php') ?>" href="<?= publicBaseUrl('index.php') ?>">Inicio</a>
            <a class="<?= rutaActivaPublic('catalogo.php') ?>" href="<?= publicBaseUrl('catalogo.php') ?>">Catálogo</a>
            <a class="<?= rutaActivaPublic('terminos.php') ?>" href="<?=publicBaseUrl('terminos.php')?>">Cómo comprar</a>
            <a href="<?=publicBaseUrl('index.php')?>#service">Service</a>
            <a class="<?= rutaActivaPublic('contacto.php') ?>" href="<?= publicBaseUrl('contacto.php') ?>">Ayuda</a>
            <a class="<?= rutaActivaPublic('cuenta.php') ?>" href="<?= publicBaseUrl('cuenta.php') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Mi cuenta
            </a>
            <a class="nav-cart <?= rutaActivaPublic('carrito.php') ?>" href="<?= htmlspecialchars($storefrontBase . '/carrito', ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                Carrito
            </a>
        </nav>
    </div>
</header>
<script nonce="<?= cspNonce() ?>">
document.getElementById('public-brand-logo')?.addEventListener('error', function () {
    const fallback = this.dataset.fallbackSrc || '';
    if (fallback && this.src !== fallback) {
        this.src = fallback;
    }
}, { once: true });
</script>
