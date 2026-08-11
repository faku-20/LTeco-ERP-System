/* =========================================================
   LTECOBIKE PANEL — UI runtime
   - Tema light/dark con persistencia
   - Drawer mobile (sidebar)
   - Atajos: Cmd/Ctrl+J = toggle tema, / = focus buscador
   ========================================================= */
(function () {
  'use strict';

  var THEME_KEY = 'lteco-panel-theme';

  // ---------- ICONS (SVG inline) ----------
  var iconMoon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
  var iconSun  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>';
  var iconMenu = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
  var iconClose = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

  // ---------- TEMA ----------
  function getStoredTheme() {
    try {
      var v = localStorage.getItem(THEME_KEY);
      if (v === 'dark' || v === 'light') return v;
    } catch (e) {}
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    return prefersDark ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    var t = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem(THEME_KEY, t); } catch (e) {}

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      var iconEl = btn.querySelector('[data-theme-toggle-icon]');
      var textEl = btn.querySelector('[data-theme-toggle-text]');
      if (iconEl) iconEl.innerHTML = t === 'dark' ? iconSun : iconMoon;
      if (textEl) textEl.textContent = t === 'dark' ? 'Claro' : 'Oscuro';
      var label = t === 'dark' ? 'Activar modo claro' : 'Activar modo oscuro';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
    });
  }

  function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  }

  function bindThemeButtons() {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      if (btn.dataset.boundTheme === '1') return;
      btn.dataset.boundTheme = '1';
      btn.addEventListener('click', function (e) { e.preventDefault(); toggleTheme(); });
    });
  }

  // ---------- MOBILE DRAWER ----------
  function updateMobileMenuButton() {
    var btn = document.querySelector('.mobile-menu-toggle');
    if (!btn) return;

    var isOpen = document.body.classList.contains('sidebar-open');
    btn.innerHTML = isOpen ? iconClose : iconMenu;
    btn.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function setSidebarOpen(open) {
    document.body.classList.toggle('sidebar-open', !!open);
    updateMobileMenuButton();
  }

  function ensureMobileMenu() {
    if (!document.body) return;
    if (!document.querySelector('.sidebar, .sidebar-v4, .admin-sidebar')) return;

    if (!document.querySelector('.mobile-menu-toggle')) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mobile-menu-toggle';
      btn.setAttribute('aria-label', 'Abrir menú');
      btn.setAttribute('aria-expanded', 'false');
      btn.innerHTML = iconMenu;
      btn.addEventListener('click', function () {
        setSidebarOpen(!document.body.classList.contains('sidebar-open'));
      });
      document.body.appendChild(btn);
    }

    if (!document.querySelector('.mobile-sidebar-overlay')) {
      var overlay = document.createElement('div');
      overlay.className = 'mobile-sidebar-overlay';
      overlay.addEventListener('click', function () {
        setSidebarOpen(false);
      });
      document.body.appendChild(overlay);
    }

    // Cerrar drawer al navegar
    document.querySelectorAll('.sidebar a, .sidebar-v4 a').forEach(function (a) {
      if (a.dataset.boundClose === '1') return;
      a.dataset.boundClose = '1';
      a.addEventListener('click', function () {
        setSidebarOpen(false);
      });
    });

    updateMobileMenuButton();
  }

  // ---------- ATAJOS DE TECLADO ----------
  function bindShortcuts() {
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
        setSidebarOpen(false);
        return;
      }

      // Cmd/Ctrl + J → toggle tema
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'j') {
        e.preventDefault();
        toggleTheme();
        return;
      }
      // / → enfocar el buscador si existe
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
      var active = document.activeElement;
      var typing = active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
      if (typing) return;
      var search = document.querySelector(
        'input[type="search"], input[name="q"], input[name="buscar"], input[placeholder*="Buscar"], input[placeholder*="buscar"]'
      );
      if (search) { e.preventDefault(); search.focus(); }
    });
  }

  // ---------- INIT ----------
  function init() {
    applyTheme(getStoredTheme());
    bindThemeButtons();
    ensureMobileMenu();
    bindShortcuts();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Marcador para página de mantenimiento (preservado del JS anterior)
  try {
    var path = window.location.pathname || '';
    var base = (window.LTECO_PANEL_BASE || '/lteco-panel').replace(/\/+$/, '');
    if (path.indexOf(base + '/configuracion/mantenimiento/') !== -1) {
      document.documentElement.classList.add('page-mantenimiento');
      if (document.body) document.body.classList.add('page-mantenimiento');
    }
  } catch (e) {}
})();
