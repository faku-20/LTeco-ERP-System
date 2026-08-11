<?php
if (!function_exists('renderFlashToast')) {
    function renderFlashToast(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Compat: setFlash() stores in $_SESSION['flash']['type'/'message']
        if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
            $_SESSION['flash_type'] = $_SESSION['flash_type'] ?? ($_SESSION['flash']['type'] ?? null);
            $_SESSION['flash_message'] = $_SESSION['flash_message'] ?? ($_SESSION['flash']['message'] ?? null);
            unset($_SESSION['flash']);
        }

        $flashTipo = $_SESSION['flash_tipo']
            ?? $_SESSION['flash_type']
            ?? $_GET['flash_tipo']
            ?? $_GET['type']
            ?? null;

        $flashMensaje = $_SESSION['flash_mensaje']
            ?? $_SESSION['flash_message']
            ?? $_GET['flash_mensaje']
            ?? $_GET['message']
            ?? null;

        unset(
            $_SESSION['flash_tipo'],
            $_SESSION['flash_type'],
            $_SESSION['flash_mensaje'],
            $_SESSION['flash_message']
        );

        if (!$flashMensaje) {
            return;
        }

        $flashTipo = strtolower((string)$flashTipo);
        $tiposPermitidos = ['success', 'ok', 'info', 'warning', 'error', 'danger'];

        if (!in_array($flashTipo, $tiposPermitidos, true)) {
            $flashTipo = 'info';
        }

        if ($flashTipo === 'ok') {
            $flashTipo = 'success';
        }

        if ($flashTipo === 'danger') {
            $flashTipo = 'error';
        }

        $titulos = [
            'success' => 'Operación realizada',
            'info' => 'Información',
            'warning' => 'Atención',
            'error' => 'No se pudo completar',
        ];

        $iconos = [
            'success' => '✓',
            'info' => 'i',
            'warning' => '!',
            'error' => '×',
        ];

        $titulo = $titulos[$flashTipo] ?? 'Información';
        $icono = $iconos[$flashTipo] ?? 'i';

        ?>
        <div class="lteco-flash lteco-flash--<?= htmlspecialchars($flashTipo, ENT_QUOTES, 'UTF-8') ?>" role="status" aria-live="polite" data-flash-autoclose="3000">
            <div class="lteco-flash__icon" aria-hidden="true">
                <?= htmlspecialchars($icono, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="lteco-flash__body">
                <strong class="lteco-flash__title">
                    <?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                </strong>

                <p class="lteco-flash__message">
                    <?= htmlspecialchars((string)$flashMensaje, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <button type="button" class="lteco-flash__close" aria-label="Cerrar aviso">
                ×
            </button>
        </div>
        <script nonce="<?= cspNonce() ?>">
            window.setTimeout(function () {
                document.querySelectorAll('[data-flash-autoclose]').forEach(function (flash) {
                    flash.remove();
                });
            }, 3000);
        </script>
        <?php
    }
}
