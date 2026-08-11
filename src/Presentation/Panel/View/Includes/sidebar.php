<?php
$usuarioSidebar = usuarioActual();
$nombreSidebar = (string)($usuarioSidebar['NombreCompleto'] ?? $usuarioSidebar['Usuario'] ?? 'Usuario');
$rolSidebar = rolActual();
$rutaActualSidebar = (string)($_SERVER['REQUEST_URI'] ?? '');

if (!function_exists('sidebarV4Activo')) {
    function sidebarV4Activo(string $needle, string $rutaActual): string
    {
        $base = rtrim(panelBaseUrl(''), '/') . '/';
        return str_contains($rutaActual, $base . $needle) ? 'active' : '';
    }
}

if (!function_exists('panelIcon')) {
    function panelIcon(string $name): string
    {
        $icons = [
            'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            'search'     => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'bike'       => '<circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>',
            'wrench'     => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
            'users'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'plus'       => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'receipt'    => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
            'lifebuoy'   => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="m4.93 4.93 4.24 4.24M14.83 14.83l4.24 4.24M14.83 9.17l4.24-4.24M9.17 14.83l-4.24 4.24"/>',
            'trendDown'  => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
            'bar'        => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
    'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'sparkles'   => '<path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3Z"/><path d="M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14Z"/>',
            'truck'      => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'package'    => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
            'userCog'    => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h5"/><circle cx="19" cy="15" r="3"/><path d="m21.7 16.4-.9-.3M15.2 13.9l-.9-.3M16.6 18.7l.3-.9M13.8 12.2l.3-.9"/>',
            'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
            'bell'       => '<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>',
            'calendar'   => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'moon'       => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        ];
        $path = $icons[$name] ?? '';
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }
}

// Navegación según rol — refleja exactamente las opciones del lobby
if (esSuperadmin()) {
    $navOperacion = [
        ['label' => 'Dashboard',   'href' => panelBaseUrl('dashboard.php'),        'active' => sidebarV4Activo('dashboard.php', $rutaActualSidebar), 'icon' => 'dashboard'],
        ['label' => 'Comercial',   'href' => panelBaseUrl('comercial/index.php'),  'active' => sidebarV4Activo('comercial/', $rutaActualSidebar) ?: sidebarV4Activo('clientes/', $rutaActualSidebar) ?: sidebarV4Activo('busqueda/', $rutaActualSidebar) ?: sidebarV4Activo('agenda/', $rutaActualSidebar) ?: sidebarV4Activo('whatsapp/', $rutaActualSidebar), 'icon' => 'users'],
        ['label' => 'Stock',       'href' => panelBaseUrl('stock/index.php'),      'active' => sidebarV4Activo('stock/', $rutaActualSidebar) ?: sidebarV4Activo('vehiculos/', $rutaActualSidebar) ?: sidebarV4Activo('repuestos/', $rutaActualSidebar) ?: sidebarV4Activo('importaciones/', $rutaActualSidebar), 'icon' => 'bike'],
        ['label' => 'Ventas',      'href' => panelBaseUrl('operacion/index.php'),  'active' => sidebarV4Activo('operacion/', $rutaActualSidebar) ?: sidebarV4Activo('ventas/', $rutaActualSidebar) ?: sidebarV4Activo('postventa/', $rutaActualSidebar), 'icon' => 'receipt'],
        ['label' => 'Pedidos web', 'href' => panelBaseUrl('ecommerce/index.php'),  'active' => sidebarV4Activo('ecommerce/', $rutaActualSidebar), 'icon' => 'package'],
    ];
} elseif (esAdministrador()) {
    $navOperacion = [
        ['label' => 'Dashboard',   'href' => panelBaseUrl('dashboard.php'),        'active' => sidebarV4Activo('dashboard.php', $rutaActualSidebar), 'icon' => 'dashboard'],
        ['label' => 'Buscar',      'href' => panelBaseUrl('busqueda/index.php'),   'active' => sidebarV4Activo('busqueda/',     $rutaActualSidebar), 'icon' => 'search'],
        ['label' => 'Vehículos',   'href' => panelBaseUrl('vehiculos/index.php'),  'active' => sidebarV4Activo('vehiculos/',    $rutaActualSidebar), 'icon' => 'bike'],
        ['label' => 'Repuestos',   'href' => panelBaseUrl('repuestos/index.php'),  'active' => sidebarV4Activo('repuestos/',    $rutaActualSidebar), 'icon' => 'wrench'],
        ['label' => 'Clientes',    'href' => panelBaseUrl('clientes/index.php'),   'active' => sidebarV4Activo('clientes/',     $rutaActualSidebar), 'icon' => 'users'],
        ['label' => 'Ventas',      'href' => panelBaseUrl('ventas/index.php'),     'active' => sidebarV4Activo('ventas/index',  $rutaActualSidebar), 'icon' => 'receipt'],
        ['label' => 'Pedidos web', 'href' => panelBaseUrl('ecommerce/index.php'),  'active' => sidebarV4Activo('ecommerce/',     $rutaActualSidebar), 'icon' => 'package'],
        ['label' => 'Nueva venta', 'href' => panelBaseUrl('ventas/crear.php'),     'active' => sidebarV4Activo('ventas/crear',  $rutaActualSidebar), 'icon' => 'plus'],
        ['label' => 'Postventa',   'href' => panelBaseUrl('postventa/index.php'),  'active' => sidebarV4Activo('postventa/',    $rutaActualSidebar), 'icon' => 'lifebuoy'],
    ];
} elseif (esDistribuidor()) {
    // Distribuidor — solo ve "Volver al inicio" en el sidebar, sin nav
    $navOperacion = [];
} else {
    // Vendedor — solo ve "Volver al inicio" en el sidebar, sin nav
    $navOperacion = [];
}

// Administración
$navAdmin = [
    ['label' => 'Administración','href' => panelBaseUrl('administracion/index.php'), 'active' => sidebarV4Activo('administracion/', $rutaActualSidebar) ?: sidebarV4Activo('gastos/', $rutaActualSidebar) ?: sidebarV4Activo('balance/', $rutaActualSidebar) ?: sidebarV4Activo('distribuidores/', $rutaActualSidebar) ?: sidebarV4Activo('usuarios/', $rutaActualSidebar) ?: sidebarV4Activo('configuracion/', $rutaActualSidebar) ?: sidebarV4Activo('auditoria/', $rutaActualSidebar), 'icon' => 'bar', 'visible' => esAdmin()],
    ['label' => 'Automatizaciones','href' => panelBaseUrl('automatizaciones/index.php'), 'active' => sidebarV4Activo('automatizaciones/', $rutaActualSidebar) ?: sidebarV4Activo('ia/', $rutaActualSidebar) ?: sidebarV4Activo('notificaciones/', $rutaActualSidebar), 'icon' => 'sparkles', 'visible' => esAdmin()],
];

$mostrarAdmin = false;
foreach ($navAdmin as $item) { if ($item['visible']) { $mostrarAdmin = true; break; } }
?>

<aside class="sidebar-v4 sidebar">
    <a class="brand-v4 sidebar-brand" href="<?= panelBaseUrl(esSuperadmin() ? 'dashboard.php' : 'inicio.php') ?>">
        <img src="<?= panelBaseUrl('assets/img/logo.png') ?>" alt="<?= htmlspecialchars(appName(), ENT_QUOTES, 'UTF-8') ?>">
        <span class="brand-copy">
            <strong class="brand-title"><?= htmlspecialchars(appName(), ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="brand-subtitle"><?= htmlspecialchars(appTagline(), ENT_QUOTES, 'UTF-8') ?></span>
        </span>
    </a>

    <?php if (esAdministrador() || esVendedor() || esDistribuidor()): ?>
        <a class="sidebar-back-lobby" href="<?= panelBaseUrl('inicio.php') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            <span>Volver al inicio</span>
        </a>
    <?php else: ?>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Operación</div>
        <nav class="sidebar-nav-v4 sidebar-nav" aria-label="Operación">
            <?php foreach ($navOperacion as $item): ?>
                <a class="<?= htmlspecialchars($item['active'], ENT_QUOTES, 'UTF-8') ?>"
                   href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= panelIcon($item['icon']) ?>
                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="nav-dot-v4" aria-hidden="true"></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if ($mostrarAdmin): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Administración</div>
            <nav class="sidebar-nav-v4 sidebar-nav" aria-label="Administración">
                <?php foreach ($navAdmin as $item): ?>
                    <?php if (!$item['visible']) { continue; } ?>
                    <a class="<?= htmlspecialchars($item['active'], ENT_QUOTES, 'UTF-8') ?>"
                       href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= panelIcon($item['icon']) ?>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="nav-dot-v4" aria-hidden="true"></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    <?php endif; ?>
    <?php endif; // fin: Admin/Vendedor ven "Volver al inicio"; Distribuidor ve nav ?>

    <div class="session-v4 sidebar-session">
        <span class="session-v4__kicker">Sesión activa</span>
        <strong class="session-v4__name"><?= htmlspecialchars($nombreSidebar, ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="session-v4__role"><?= htmlspecialchars($rolSidebar, ENT_QUOTES, 'UTF-8') ?></span>
        <a class="session-v4__changepass" href="<?= panelBaseUrl('usuarios/mi_clave.php') ?>">
            <?= panelIcon('shield') ?>
            <span>Cambiar mi clave</span>
        </a>
        <div class="session-v4__actions">
            <button type="button" class="theme-toggle-v4" data-theme-toggle aria-label="Cambiar tema">
                <span data-theme-toggle-icon><?= panelIcon('moon') ?></span>
                <span data-theme-toggle-text>Oscuro</span>
            </button>
            <form method="POST" action="<?= panelBaseUrl('logout.php') ?>" class="session-v4__logout-form">
                <?= csrfInput() ?>
                <button type="submit" class="session-v4__logout">
                <?= panelIcon('logout') ?>
                <span>Salir</span>
                </button>
            </form>
        </div>
    </div>
</aside>
