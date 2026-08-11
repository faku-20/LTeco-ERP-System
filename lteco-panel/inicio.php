<?php
$pageTitle = 'Inicio | ' . 'Ltecobike';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requiereLogin();

if (esSuperadmin()) {
    redirect(panelBaseUrl('dashboard.php'));
}

$usuario  = usuarioActual();
$nombre   = explode(' ', trim($usuario['NombreCompleto'] ?? 'Usuario'))[0];
$esAdmin  = esAdmin();
$esSup    = esSuperadmin();
$esDist   = esDistribuidor();

$opciones = [];

if ($esDist) {
    $opciones = [
        ['url' => panelBaseUrl('distribuidores/index.php'),        'icon' => 'bar-chart',   'label' => 'Panel distribuidor', 'desc' => 'Resumen de stock, ventas y pedidos', 'color' => 'brand'],
        ['url' => panelBaseUrl('distribuidores/nueva_venta.php'),  'icon' => 'plus-circle', 'label' => 'Nueva venta',        'desc' => 'Vender desde tu stock asignado',     'color' => 'neutral'],
        ['url' => panelBaseUrl('distribuidores/nuevo_pedido.php'), 'icon' => 'package',     'label' => 'Solicitar stock',    'desc' => 'Pedir unidades a Ltecobike',         'color' => 'neutral'],
        ['url' => panelBaseUrl('distribuidores/busqueda.php'),     'icon' => 'search',      'label' => 'Buscar',             'desc' => 'Buscar en tu stock, ventas y clientes', 'color' => 'neutral'],
    ];
} elseif ($esAdmin) {
    // Administrador: secciones agrupadas para evitar módulos repetidos.
    $opciones = [
        ['url' => panelBaseUrl('comercial/index.php'),       'icon' => 'users',       'label' => 'Comercial',        'desc' => 'WhatsApp, agenda y alertas',                      'color' => 'neutral'],
        ['url' => panelBaseUrl('stock/index.php'),           'icon' => 'bike',        'label' => 'Stock',            'desc' => 'Vehículos, repuestos e importaciones',            'color' => 'neutral'],
        ['url' => panelBaseUrl('operacion/index.php'),       'icon' => 'receipt',     'label' => 'Ventas',           'desc' => 'Ventas, nueva venta, clientes y postventa',       'color' => 'neutral'],
        ['url' => panelBaseUrl('administracion/index.php'),  'icon' => 'bar-chart',   'label' => 'Administración',   'desc' => 'Balance, gastos, usuarios y configuración',       'color' => 'neutral'],
        ['url' => panelBaseUrl('automatizaciones/index.php'),'icon' => 'sparkles',    'label' => 'Automatizaciones', 'desc' => 'Asistente IA, acciones, base comercial y n8n',     'color' => 'neutral'],
    ];
} else {
    // Vendedor
    $opciones = [
        ['url' => panelBaseUrl('ventas/crear.php'),    'icon' => 'plus-circle', 'label' => 'Nueva venta', 'desc' => 'Registrar una venta de vehículo o accesorio', 'color' => 'brand'],
        ['url' => panelBaseUrl('ia/index.php'),        'icon' => 'sparkles',    'label' => 'Asistente IA', 'desc' => 'Consultar tareas y seguimiento comercial',      'color' => 'neutral'],
        ['url' => panelBaseUrl('agenda/index.php'),    'icon' => 'calendar',    'label' => 'Agenda',      'desc' => 'Visitas showroom confirmadas',                 'color' => 'brand'],
        ['url' => panelBaseUrl('notificaciones/index.php'), 'icon' => 'bell',   'label' => 'Alertas',     'desc' => 'Avisos de visitas asignadas',                   'color' => 'neutral'],
        ['url' => panelBaseUrl('busqueda/index.php'),  'icon' => 'search',      'label' => 'Buscar',      'desc' => 'Buscar vehículos, clientes o ventas',         'color' => 'neutral'],
        ['url' => panelBaseUrl('postventa/index.php') . '?estado_service=Pendiente', 'icon' => 'wrench', 'label' => 'Servicios pendientes', 'desc' => 'Ver servicios pendientes', 'color' => 'neutral'],
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
    <script defer src="<?= panelBaseUrl('assets/js/panel-history-back.js') ?>?v=<?= @filemtime(__DIR__ . '/assets/js/panel-history-back.js') ?>"></script>
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

    <style>
        /* ---- lobby layout ---- */
        .lobby-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--paper);
        }

        /* top bar */
        .lobby-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 28px;
            border-bottom: 1px solid var(--line);
            background: var(--surface);
            gap: 12px;
        }

        .lobby-header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .lobby-header-brand img {
            height: 32px;
            width: auto;
        }

        .lobby-header-brand span {
            font-size: 17px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.02em;
        }

        .lobby-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lobby-user-chip {
            font-size: 13px;
            color: var(--ink-2);
            background: var(--paper-2);
            border: 1px solid var(--line);
            border-radius: var(--r-full);
            padding: 4px 12px;
            line-height: 1.4;
        }

        /* main area */
        .lobby-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 24px 64px;
        }

        .lobby-greeting {
            text-align: center;
            margin-bottom: 40px;
        }

        .lobby-greeting h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .lobby-greeting p {
            color: var(--ink-2);
            font-size: 15px;
        }

        /* card grid */
        .lobby-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 280px));
            gap: 20px;
            width: 100%;
            max-width: 960px;
            justify-content: center;
        }

        .lobby-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            text-decoration: none;
            padding: 36px 24px 32px;
            background: var(--surface);
            border: 1.5px solid var(--line);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-md);
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            cursor: pointer;
            color: var(--ink);
            gap: 0;
        }

        .lobby-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--brand);
            text-decoration: none;
        }

        .lobby-card:active {
            transform: translateY(0);
        }

        .lobby-card--accent:hover {
            border-color: var(--accent);
        }

        .lobby-card-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--r-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            flex-shrink: 0;
        }

        .lobby-card-icon--brand {
            background: var(--brand-soft);
            color: var(--brand);
        }

        .lobby-card-icon--neutral {
            background: var(--paper-2);
            color: var(--ink-2);
        }

        .lobby-card-icon--accent {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .lobby-card-icon svg {
            width: 32px;
            height: 32px;
            stroke-width: 1.75;
        }

        .lobby-card h2 {
            font-size: 17px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .lobby-card p {
            font-size: 13px;
            color: var(--ink-3);
            line-height: 1.45;
        }

        /* theme toggle btn */
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--r-sm);
            background: var(--paper-2);
            border: 1px solid var(--line);
            color: var(--ink-2);
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }

        .btn-icon:hover { background: var(--line); color: var(--ink); }
        .btn-icon svg { width: 18px; height: 18px; }

        /* logout btn */
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-2);
            background: var(--paper-2);
            border: 1px solid var(--line);
            border-radius: var(--r-sm);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }

        .btn-logout:hover {
            background: var(--danger-soft);
            color: var(--danger);
            border-color: var(--danger);
            text-decoration: none;
        }

        .btn-logout svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* responsive */
        @media (max-width: 540px) {
            .lobby-header { padding: 12px 16px; }
            .lobby-user-chip { display: none; }
            .lobby-body { padding: 32px 16px 48px; }
            .lobby-greeting h1 { font-size: 22px; }
            .lobby-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
            .lobby-card { padding: 24px 16px 20px; }
            .lobby-card-icon { width: 52px; height: 52px; margin-bottom: 14px; }
            .lobby-card-icon svg { width: 26px; height: 26px; }
            .lobby-card h2 { font-size: 15px; }
        }
    </style>
</head>
<body class="lobby-page" data-panel-back-fallback="<?= htmlspecialchars(panelBaseUrl('inicio.php'), ENT_QUOTES, 'UTF-8') ?>">

    <!-- Header -->
    <header class="lobby-header">
        <a class="lobby-header-brand" href="<?= panelBaseUrl('inicio.php') ?>">
            <img src="<?= panelBaseUrl('assets/img/logo.png') ?>" alt="<?= htmlspecialchars(appName()) ?>">
            <span><?= htmlspecialchars(appName()) ?></span>
        </a>

        <div class="lobby-header-right">
            <span class="lobby-user-chip"><?= h($usuario['NombreCompleto']) ?> &middot; <?= h(rolActual()) ?></span>

            <button class="btn-icon" id="lobby-theme-toggle" title="Cambiar tema" aria-label="Cambiar tema">
                <svg id="lobby-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <svg id="lobby-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>

            <form method="POST" action="<?= panelBaseUrl('logout.php') ?>" style="margin:0;">
                <?= csrfInput() ?>
                <button type="submit" class="btn-logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Salir
                </button>
            </form>
        </div>
    </header>

    <!-- Body -->
    <main class="lobby-body">
        <div class="lobby-greeting">
            <h1>Hola, <?= h($nombre) ?></h1>
            <p>¿Qué querés hacer hoy?</p>
        </div>

        <div class="lobby-grid">
            <?php foreach ($opciones as $op): ?>
                <?php
                    $iconoSvg = match ($op['icon']) {
                        'plus-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
                        'search'      => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
                        'wrench'      => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
                        'bar-chart'   => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
                        'package'     => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
                        'bike'        => '<circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>',
                        'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                        'receipt'     => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
                        'trend-down'  => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
                        'bar'         => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
                        'bell'        => '<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>',
                        'calendar'    => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
                        'sparkles'    => '<path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3Z"/><path d="M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14Z"/>',
                        'user-cog'    => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h5"/><circle cx="19" cy="15" r="3"/><path d="m21.7 16.4-.9-.3M15.2 13.9l-.9-.3M16.6 18.7l.3-.9M13.8 12.2l.3-.9"/>',
                        'truck'       => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
                        default       => '<circle cx="12" cy="12" r="10"/>',
                    };
                    $colorClass = match ($op['color']) {
                        'brand'   => 'lobby-card-icon--brand',
                        'accent'  => 'lobby-card-icon--accent',
                        default   => 'lobby-card-icon--neutral',
                    };
                    $cardAccent = $op['color'] === 'accent' ? ' lobby-card--accent' : '';
                ?>
                <a href="<?= htmlspecialchars($op['url']) ?>" class="lobby-card<?= $cardAccent ?>">
                    <div class="lobby-card-icon <?= $colorClass ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconoSvg ?></svg>
                    </div>
                    <h2><?= h($op['label']) ?></h2>
                    <p><?= h($op['desc']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <script nonce="<?= cspNonce() ?>">
        (function () {
            var btn = document.getElementById('lobby-theme-toggle');
            var sun = document.getElementById('lobby-icon-sun');
            var moon = document.getElementById('lobby-icon-moon');

            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                sun.style.display  = theme === 'dark' ? 'block' : 'none';
                moon.style.display = theme === 'dark' ? 'none'  : 'block';
            }

            var current = localStorage.getItem('lteco-panel-theme') ||
                          (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            applyTheme(current);

            btn.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                localStorage.setItem('lteco-panel-theme', next);
                applyTheme(next);
            });
        })();
    </script>
<?php if (esAdmin()): ?>
<script nonce="<?= cspNonce() ?>">
window.LTECO_VISIT_ALERTS = <?= json_encode([
    'enabled' => true,
    'endpoint' => panelBaseUrl('api/alerts/latest.php'),
    'agendaUrl' => panelBaseUrl('agenda/index.php'),
    'alertsUrl' => panelBaseUrl('notificaciones/index.php'),
    'iconUrl' => panelBaseUrl('assets/img/logo.png'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= panelBaseUrl('assets/js/visit-alerts.js') ?>?v=<?= @filemtime(__DIR__ . '/assets/js/visit-alerts.js') ?>"></script>
<?php endif; ?>
</body>
</html>
