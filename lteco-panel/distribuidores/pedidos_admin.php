<?php
$pageTitle = "Pedidos pendientes | Lteco";
require_once __DIR__ . "/_common.php";

requiereAdmin();

$whatsappLinkPost = null;
$pedidoService = new \Lteco\Application\Distribuidor\DistribuidorPedidoService(
    new \Lteco\Infrastructure\Repository\DistribuidorPedidoRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    ),
    new \Lteco\Application\Distribuidor\DistribuidorStockService(
        new \Lteco\Infrastructure\Repository\DistribuidorStockRepository(
            new \Lteco\Infrastructure\Db\Connection($pdo)
        )
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();

        $accion = trim((string)($_POST['accion'] ?? ''));
        $idPedido = (int)($_POST['id_pedido'] ?? 0);

        if (!in_array($accion, ['aprobar', 'rechazar'], true) || $idPedido <= 0) {
            throw new RuntimeException('Acción no válida.');
        }

        $pedido = $pedidoService->buscarPendiente($idPedido);
        if (!$pedido) {
            throw new RuntimeException('Pedido no encontrado o ya fue procesado.');
        }

        $tipoItem = (string)$pedido['TipoItem'];
        $cantidad = (int)$pedido['Cantidad'];
        $idVehiculoPedido = $tipoItem === 'Vehiculo' ? $pedido['IdVehiculo'] : null;
        $idRepuestoPedido = $tipoItem === 'Repuesto' ? (int)$pedido['IdRepuesto'] : null;

        $pdo->beginTransaction();

        $nuevoEstado = $pedidoService->resolverPedido(
            $idPedido,
            $accion,
            (int)$pedido['IdDistribuidor'],
            $tipoItem,
            $idVehiculoPedido,
            $idRepuestoPedido,
            $cantidad,
            dbTieneTabla($pdo, 'remito')
        );

        registrarAuditoria($pdo, 'PEDIDO_DISTRIBUIDOR_' . strtoupper($accion), 'Distribuidores', 'Pedido #' . $idPedido . ' ' . $nuevoEstado, [
            'id_pedido' => $idPedido,
            'id_distribuidor' => $pedido['IdDistribuidor'],
            'estado' => $nuevoEstado,
        ]);
        $pdo->commit();

        $nombreProducto = distribuidorItemLabel($pedido);
        $mensajeWa = "Ltecobike: pedido #{$idPedido} {$nuevoEstado}.\nProducto: {$nombreProducto} · Cantidad: {$pedido['Cantidad']}.";
        $whatsappLinkPost = distribuidorWhatsappNotificar($pdo, (int)$pedido['IdDistribuidor'], $mensajeWa);

        $textoFlash = 'Pedido #' . $idPedido . ' ' . $nuevoEstado . ' correctamente.';
        if ($whatsappLinkPost) {
            $textoFlash .= ' Podés notificar al distribuidor por WhatsApp.';
            $_SESSION['wa_pedido_link'] = $whatsappLinkPost;
        }
        setFlash('success', $textoFlash);
        redirect(panelBaseUrl('distribuidores/pedidos_admin.php'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', mensajeErrorSeguro($e, 'No se pudo procesar el pedido.'));
        redirect(panelBaseUrl('distribuidores/pedidos_admin.php'));
    }
}

// Recover WhatsApp link from session if present
if (!empty($_SESSION['wa_pedido_link'])) {
    $whatsappLinkPost = $_SESSION['wa_pedido_link'];
    unset($_SESSION['wa_pedido_link']);
}

$pedidosPendientes = $pedidoService->listarPendientes();

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Pedidos pendientes</h1>
            <p class="subtle"><?= count($pedidosPendientes) ?> pedido<?= count($pedidosPendientes) !== 1 ? 's' : '' ?> esperando aprobación.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <?php if ($whatsappLinkPost): ?>
        <div class="notice notice--info" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <span>Notificar al distribuidor por WhatsApp:</span>
            <a class="btn" target="_blank" rel="noopener" href="<?= h($whatsappLinkPost, '') ?>">Abrir WhatsApp</a>
        </div>
    <?php endif; ?>

    <?php if ($pedidosPendientes): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Distribuidor</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Fecha</th>
                        <th>Observaciones</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidosPendientes as $p): ?>
                        <tr>
                            <td>#<?= (int)$p['IdPedido'] ?></td>
                            <td><strong><?= h($p['DistribuidorNombre'], '') ?></strong></td>
                            <td><?= h(distribuidorItemLabel($p), '') ?></td>
                            <td><?= h($p['TipoItem'], '') ?></td>
                            <td><?= (int)$p['Cantidad'] ?></td>
                            <td><?= h(substr((string)($p['FechaPedido'] ?? ''), 0, 16), '') ?></td>
                            <td><?= h($p['Observaciones'] ?? '-') ?></td>
                            <td>
                                <div class="actions-row" style="gap:6px;">
                                    <form method="POST" style="display:inline;" class="js-pedido-form">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="accion" value="aprobar">
                                        <input type="hidden" name="id_pedido" value="<?= (int)$p['IdPedido'] ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-small js-pedido-action"
                                            data-action="aprobar"
                                            data-pedido="#<?= (int)$p['IdPedido'] ?>"
                                            data-distribuidor="<?= h($p['DistribuidorNombre'], '') ?>"
                                            data-producto="<?= h(distribuidorItemLabel($p), '') ?>"
                                            data-tipo="<?= h($p['TipoItem'], '') ?>"
                                            data-cantidad="<?= (int)$p['Cantidad'] ?>"
                                        >Aprobar</button>
                                    </form>
                                    <form method="POST" style="display:inline;" class="js-pedido-form">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="accion" value="rechazar">
                                        <input type="hidden" name="id_pedido" value="<?= (int)$p['IdPedido'] ?>">
                                        <button
                                            type="submit"
                                            class="btn-secondary btn-small js-pedido-action"
                                            data-action="rechazar"
                                            data-pedido="#<?= (int)$p['IdPedido'] ?>"
                                            data-distribuidor="<?= h($p['DistribuidorNombre'], '') ?>"
                                            data-producto="<?= h(distribuidorItemLabel($p), '') ?>"
                                            data-tipo="<?= h($p['TipoItem'], '') ?>"
                                            data-cantidad="<?= (int)$p['Cantidad'] ?>"
                                        >Rechazar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="section-box">
            <p class="help-text" style="text-align:center;padding:24px 0;">No hay pedidos pendientes de aprobación.</p>
        </div>
    <?php endif; ?>

    <div class="lteco-modal" id="pedidoConfirmModal" aria-hidden="true">
        <div class="lteco-modal__backdrop" data-modal-close></div>
        <div class="lteco-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pedidoConfirmTitle">
            <div class="lteco-modal__header">
                <div>
                    <span class="lteco-modal__eyebrow" id="pedidoConfirmEyebrow">Confirmar acción</span>
                    <h2 id="pedidoConfirmTitle">Confirmar pedido</h2>
                </div>
                <button type="button" class="lteco-modal__close" data-modal-close aria-label="Cerrar">×</button>
            </div>

            <div class="lteco-modal__body">
                <p class="lteco-modal__lead" id="pedidoConfirmLead"></p>

                <div class="lteco-modal__summary">
                    <div>
                        <span>Pedido</span>
                        <strong id="pedidoConfirmId">-</strong>
                    </div>
                    <div>
                        <span>Distribuidor</span>
                        <strong id="pedidoConfirmDistribuidor">-</strong>
                    </div>
                    <div>
                        <span>Producto</span>
                        <strong id="pedidoConfirmProducto">-</strong>
                    </div>
                    <div>
                        <span>Cantidad</span>
                        <strong id="pedidoConfirmCantidad">-</strong>
                    </div>
                </div>

                <div class="notice notice--warning" id="pedidoConfirmWarning" style="margin-top:14px;">
                    Al aprobar, se descontará stock interno, se asignará stock al distribuidor y se generará un remito pendiente.
                </div>
            </div>

            <div class="lteco-modal__actions">
                <button type="button" class="btn-secondary" data-modal-close>Cancelar</button>
                <button type="button" class="btn" id="pedidoConfirmSubmit">Confirmar</button>
            </div>
        </div>
    </div>

    <style>
        .lteco-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .lteco-modal.is-open {
            display: flex;
        }

        .lteco-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .68);
            backdrop-filter: blur(3px);
        }

        .lteco-modal__dialog {
            position: relative;
            width: min(560px, 100%);
            border: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
            border-radius: 18px;
            background: var(--color-background-primary, #17140f);
            color: var(--color-text-primary, #fff);
            box-shadow: 0 28px 80px rgba(0,0,0,.45);
            overflow: hidden;
        }

        .lteco-modal__header,
        .lteco-modal__body,
        .lteco-modal__actions {
            padding: 18px 20px;
        }

        .lteco-modal__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
        }

        .lteco-modal__eyebrow {
            display: block;
            margin-bottom: 4px;
            color: var(--color-text-secondary, #b8ad9d);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .lteco-modal__header h2 {
            margin: 0;
            font-size: 1.35rem;
        }

        .lteco-modal__close {
            width: 36px;
            height: 36px;
            border: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
            border-radius: 12px;
            background: transparent;
            color: inherit;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
        }

        .lteco-modal__lead {
            margin: 0 0 14px;
            color: var(--color-text-secondary, #d8d0c3);
            line-height: 1.5;
        }

        .lteco-modal__summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .lteco-modal__summary div {
            padding: 11px 12px;
            border: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
            border-radius: 12px;
            background: var(--color-background-secondary, rgba(255,255,255,.04));
        }

        .lteco-modal__summary span {
            display: block;
            margin-bottom: 4px;
            color: var(--color-text-secondary, #b8ad9d);
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .lteco-modal__summary strong {
            display: block;
            word-break: break-word;
        }

        .lteco-modal__actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
        }

        .lteco-modal--danger .lteco-modal__dialog {
            border-color: rgba(239, 68, 68, .35);
        }

        .lteco-modal--danger #pedidoConfirmSubmit {
            background: var(--color-danger, #ef4444);
            border-color: var(--color-danger, #ef4444);
        }

        @media (max-width: 640px) {
            .lteco-modal__summary {
                grid-template-columns: 1fr;
            }

            .lteco-modal__actions {
                flex-direction: column-reverse;
            }

            .lteco-modal__actions .btn,
            .lteco-modal__actions .btn-secondary {
                width: 100%;
            }
        }
    </style>

    <script nonce="<?= cspNonce() ?>">
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('pedidoConfirmModal');
        const submitBtn = document.getElementById('pedidoConfirmSubmit');
        const title = document.getElementById('pedidoConfirmTitle');
        const eyebrow = document.getElementById('pedidoConfirmEyebrow');
        const lead = document.getElementById('pedidoConfirmLead');
        const warning = document.getElementById('pedidoConfirmWarning');
        const pedidoId = document.getElementById('pedidoConfirmId');
        const distribuidor = document.getElementById('pedidoConfirmDistribuidor');
        const producto = document.getElementById('pedidoConfirmProducto');
        const cantidad = document.getElementById('pedidoConfirmCantidad');

        let pendingForm = null;

        function closeModal() {
            modal.classList.remove('is-open', 'lteco-modal--danger');
            modal.setAttribute('aria-hidden', 'true');
            pendingForm = null;
        }

        function openModal(button, form) {
            const action = button.dataset.action || '';
            const isApprove = action === 'aprobar';

            pendingForm = form;

            modal.classList.toggle('lteco-modal--danger', !isApprove);
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');

            eyebrow.textContent = isApprove ? 'Aprobación de stock' : 'Rechazo de pedido';
            title.textContent = isApprove ? 'Aprobar pedido de distribuidor' : 'Rechazar pedido de distribuidor';
            lead.textContent = isApprove
                ? 'Vas a aprobar este pedido y mover stock interno hacia el distribuidor.'
                : 'Vas a rechazar este pedido. No se descontará stock interno.';
            warning.textContent = isApprove
                ? 'Al aprobar, se descontará stock interno, se asignará stock al distribuidor y se generará un remito pendiente.'
                : 'Al rechazar, el pedido quedará cerrado como rechazado y no se asignará stock.';
            submitBtn.textContent = isApprove ? 'Aprobar pedido' : 'Rechazar pedido';

            pedidoId.textContent = button.dataset.pedido || '-';
            distribuidor.textContent = button.dataset.distribuidor || '-';
            producto.textContent = button.dataset.producto || '-';
            cantidad.textContent = (button.dataset.cantidad || '-') + ' · ' + (button.dataset.tipo || '');

            setTimeout(() => submitBtn.focus(), 50);
        }

        document.querySelectorAll('.js-pedido-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const button = event.submitter || form.querySelector('.js-pedido-action');

                if (!button || button.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();
                openModal(button, form);
            });
        });

        submitBtn.addEventListener('click', function () {
            if (!pendingForm) return;

            const button = pendingForm.querySelector('.js-pedido-action');
            if (button) {
                button.dataset.confirmed = '1';
            }

            pendingForm.submit();
        });

        document.querySelectorAll('[data-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    });
    </script>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
