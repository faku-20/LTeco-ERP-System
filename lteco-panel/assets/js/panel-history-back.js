(function () {
    'use strict';

    function canReturnInsidePanel() {
        if (window.history.length <= 1 || !document.referrer) {
            return false;
        }

        try {
            var previous = new URL(document.referrer);
            return previous.origin === window.location.origin
                && previous.pathname.indexOf('/lteco-panel/') !== -1;
        } catch (error) {
            return false;
        }
    }

    function createBackButton() {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn-secondary panel-history-back';
        button.setAttribute('aria-label', 'Volver a la pantalla anterior');
        button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg><span>Atrás</span>';
        button.addEventListener('click', function () {
            if (canReturnInsidePanel()) {
                window.history.back();
                return;
            }

            window.location.assign(document.body.dataset.panelBackFallback || '/lteco-panel/inicio.php');
        });
        return button;
    }

    function mount() {
        if (document.querySelector('.panel-history-back')) {
            return;
        }

        var button = createBackButton();
        var topbar = document.querySelector('.topbar, .admin-topbar, .page-header-v4, .page-head');
        if (topbar) {
            var actions = topbar.querySelector('.actions-row, .actions, .quick-actions-v4');
            if (actions) {
                actions.prepend(button);
            } else {
                topbar.append(button);
            }
            return;
        }

        var main = document.querySelector('main');
        if (main) {
            var row = document.createElement('div');
            row.className = 'panel-history-back-row';
            row.append(button);
            main.prepend(row);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
