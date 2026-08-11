<?php
$pageTitle = "Distribuidores | Lteco";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../includes/flash.php";

requiereLogin();

$consulta = new \Lteco\Application\Distribuidor\DistribuidorConsultaService(
    new \Lteco\Infrastructure\Repository\DistribuidorConsultaRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);
$tablasDistribuidorListas = $consulta->tablasDistribuidorListas();

if (esDistribuidor()) {
    $idDistribuidor = requiereDistribuidorPanel();

    if (!$tablasDistribuidorListas) {
        require_once __DIR__ . "/../includes/header.php";
        require_once __DIR__ . "/../includes/sidebar.php";
        ?>
        <main class="main">
            <div class="topbar">
                <div>
                    <h1>Panel distribuidor</h1>
                    <p class="subtle">Falta aplicar la migración de stock y pedidos.</p>
                </div>
            </div>
            <div class="notice notice--error">
                Ejecutá <code>database/migrations/2026_05_28_distribuidor_stock_pedidos.sql</code> en la base <code>lteco_db</code>.
            </div>
        </main>
        <?php
        require_once __DIR__ . "/../includes/footer.php";
        exit;
    }

    $panel = $consulta->panelDistribuidor($idDistribuidor);
    $distribuidor = $panel['distribuidor'];
    $stockTotal = $panel['stockTotal'];
    $pedidos = $panel['pedidos'];
    $postventasAbiertas = $panel['postventasAbiertas'];
    $remitosPendientes = $panel['remitosPendientes'];
    $stockItems = $panel['stockItems'];

    require_once __DIR__ . "/../includes/header.php";
    require_once __DIR__ . "/../includes/sidebar.php";
    ?>
    <main class="main">
        <div class="topbar">
            <div>
                <h1>Panel distribuidor</h1>
                <p class="subtle"><?= h($distribuidor['Nombre'] ?? 'Distribuidor', '') ?> · stock, ventas y pedidos.</p>
            </div>
            <div class="actions-row">
                <button type="button" class="btn-secondary" id="btnReportarProblema">Reportar un problema</button>
                <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/nuevo_pedido.php') ?>">Solicitar stock</a>
                <a class="btn" href="<?= panelBaseUrl('distribuidores/nueva_venta.php') ?>">+ Nueva venta</a>
            </div>
        </div>

        <?php require_once __DIR__ . "/../includes/flash.php"; ?>

        <div class="cards">
            <div class="card"><small>Stock disponible</small><strong><?= $stockTotal ?></strong></div>
            <div class="card"><small>Pedidos pendientes</small><strong><?= $pedidos['Pendiente'] ?></strong></div>
            <div class="card"><small>Pedidos aprobados</small><strong><?= $pedidos['Aprobado'] ?></strong></div>
            <div class="card"><small>Postventas abiertas</small><strong><?= $postventasAbiertas ?></strong></div>
            <div class="card<?= $remitosPendientes > 0 ? ' card--warning' : '' ?>"><small>Remitos a facturar</small><strong><?= $remitosPendientes ?></strong></div>
        </div>

        <section class="v4-card">
            <div class="list-head-v4">
                <div>
                    <h2>Mi stock y lista de precios</h2>
                    <p>Consultá las unidades que tenés asignadas, su precio de lista y registrá ventas desde tu panel.</p>
                </div>
            </div>

            <?php if ($stockItems): ?>
                <div class="stock-toolbar-v4">
                    <div>
                        <strong><?= count($stockItems) ?></strong>
                        producto<?= count($stockItems) !== 1 ? 's' : '' ?> con stock asignado
                    </div>
                    <input
                        type="search"
                        id="stockDistribuidorSearch"
                        placeholder="Buscar por modelo, repuesto, ID o motor..."
                        aria-label="Buscar en mi stock"
                    >
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="table" id="stockDistribuidorTable">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Stock</th>
                            <th>Precio lista Ltecobike</th>
                            <th>N° motor</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockItems as $item): ?>
                            <?php
                                $esVehiculo = (string)$item['TipoItem'] === 'Vehiculo';
                                $numeroMotor = trim((string)($item['VehiculoNumeroMotor'] ?? ''));
                                $busqueda = strtolower(trim(distribuidorItemLabel($item) . ' ' . ($item['TipoItem'] ?? '') . ' ' . $numeroMotor));
                            ?>
                            <tr data-stock-row data-search="<?= h($busqueda, '') ?>">
                                <td>
                                    <strong><?= h(distribuidorItemLabel($item), '') ?></strong>
                                    <?php if ($esVehiculo): ?>
                                        <div class="help-text">Vehículo asignado individualmente</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $esVehiculo ? 'badge-disponible' : 'badge-info' ?>">
                                        <?= h($item['TipoItem'], '') ?>
                                    </span>
                                </td>
                                <td><strong><?= (int)$item['Cantidad'] ?></strong></td>
                                <td><strong><?= formatearMonto((float)$item['PrecioVenta'], 'UYU') ?></strong></td>
                                <td>
                                    <?php if ($numeroMotor !== ''): ?>
                                        <code><?= h($numeroMotor, '') ?></code>
                                    <?php else: ?>
                                        <span class="help-text">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn-secondary btn-small" href="<?= panelBaseUrl('distribuidores/nueva_venta.php') ?>">
                                        Vender
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$stockItems): ?>
                            <tr>
                                <td colspan="6" class="help-text" style="text-align:center;padding:24px 0;">
                                    Todavía no tenés stock asignado.
                                    <br>
                                    <a href="<?= panelBaseUrl('distribuidores/nuevo_pedido.php') ?>">Solicitá stock a Ltecobike.</a>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr id="stockDistribuidorEmpty" style="display:none;">
                            <td colspan="6" class="help-text" style="text-align:center;padding:24px 0;">
                                No se encontraron productos con ese filtro.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <style>
            .stock-toolbar-v4 {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin: 14px 0;
                flex-wrap: wrap;
            }

            .stock-toolbar-v4 input {
                width: min(360px, 100%);
                padding: 9px 12px;
                border: 1.5px solid var(--color-border-secondary);
                border-radius: 10px;
                background: var(--color-background-primary);
                color: var(--color-text-primary);
                font-size: .95rem;
                box-sizing: border-box;
            }

            .stock-toolbar-v4 input:focus {
                outline: 2px solid rgba(34, 197, 94, .25);
                border-color: var(--color-primary, #22c55e);
            }
        </style>

        <script nonce="<?= cspNonce() ?>">
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.getElementById('stockDistribuidorSearch');
            const rows = Array.from(document.querySelectorAll('[data-stock-row]'));
            const empty = document.getElementById('stockDistribuidorEmpty');

            if (search && rows.length) {
                search.addEventListener('input', function () {
                    const q = search.value.trim().toLowerCase();
                    let visibles = 0;

                    rows.forEach(function (row) {
                        const text = row.dataset.search || '';
                        const show = !q || text.includes(q);
                        row.style.display = show ? '' : 'none';
                        if (show) visibles++;
                    });

                    if (empty) {
                        empty.style.display = visibles === 0 ? '' : 'none';
                    }
                });
            }

            // Modal de reporte de problema
            const btnAbrir = document.getElementById('btnReportarProblema');
            const modal    = document.getElementById('reporteProblemaModal');
            if (!btnAbrir || !modal) return;

            const form       = modal.querySelector('#reporteProblemaForm');
            const btnEnviar  = modal.querySelector('#reporteProblemaEnviar');
            const textarea   = modal.querySelector('#reporteMensaje');
            const fileInput  = modal.querySelector('#reporteImagen');
            const fileLabel  = modal.querySelector('#reporteImagenLabel');
            const feedback   = modal.querySelector('#reporteFeedback');
            const csrfToken  = modal.querySelector('input[name="csrf_token"]');

            function abrirModal() {
                modal.classList.add('is-open');
                modal.removeAttribute('aria-hidden');
                textarea.value = '';
                fileInput.value = '';
                fileLabel.textContent = 'Elegir imagen (opcional)';
                feedback.textContent = '';
                feedback.className = '';
                btnEnviar.disabled = false;
                textarea.focus();
            }

            function cerrarModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            }

            btnAbrir.addEventListener('click', abrirModal);

            modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
                el.addEventListener('click', cerrarModal);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) cerrarModal();
            });

            fileInput.addEventListener('change', function () {
                fileLabel.textContent = fileInput.files.length > 0
                    ? fileInput.files[0].name
                    : 'Elegir imagen (opcional)';
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const mensaje = textarea.value.trim();
                if (!mensaje) {
                    feedback.className = 'notice notice--error';
                    feedback.textContent = 'Por favor describí el problema antes de enviar.';
                    textarea.focus();
                    return;
                }

                btnEnviar.disabled = true;
                feedback.textContent = '';
                feedback.className = '';

                const data = new FormData();
                data.append('csrf_token', csrfToken.value);
                data.append('mensaje', mensaje);
                if (fileInput.files.length > 0) {
                    data.append('imagen', fileInput.files[0]);
                }

                fetch('<?= panelBaseUrl('distribuidores/reportar_problema.php') ?>', {
                    method: 'POST',
                    body: data,
                })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.ok) {
                        feedback.className = 'notice notice--success';
                        feedback.textContent = 'Reporte enviado. Lo vamos a revisar.';
                        textarea.value = '';
                        fileInput.value = '';
                        fileLabel.textContent = 'Elegir imagen (opcional)';
                        // Cerrar automáticamente luego de 2 segundos
                        setTimeout(cerrarModal, 2200);
                    } else {
                        feedback.className = 'notice notice--error';
                        feedback.textContent = json.error || 'No se pudo enviar el reporte. Intentá de nuevo.';
                        btnEnviar.disabled = false;
                    }
                })
                .catch(function () {
                    feedback.className = 'notice notice--error';
                    feedback.textContent = 'Error de conexión. Revisá tu internet e intentá de nuevo.';
                    btnEnviar.disabled = false;
                });
            });
        });
        </script>

    <!-- Modal: reportar problema -->
    <div class="lteco-modal" id="reporteProblemaModal" aria-hidden="true">
        <div class="lteco-modal__backdrop" data-modal-close></div>
        <div class="lteco-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="reporteProblemaTitle">
            <div class="lteco-modal__header">
                <div>
                    <span class="lteco-modal__eyebrow">Soporte</span>
                    <h2 id="reporteProblemaTitle">Reportar un problema</h2>
                </div>
                <button type="button" class="lteco-modal__close" data-modal-close aria-label="Cerrar">×</button>
            </div>

            <form id="reporteProblemaForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div class="lteco-modal__body" style="display:flex;flex-direction:column;gap:14px;">
                    <p style="margin:0;color:var(--color-text-secondary);">Contanos qué problema tuviste y lo revisamos a la brevedad.</p>

                    <div>
                        <label for="reporteMensaje" style="display:block;margin-bottom:6px;font-weight:600;font-size:.92rem;">
                            Descripción <span style="color:var(--color-danger,#ef4444)">*</span>
                        </label>
                        <textarea
                            id="reporteMensaje"
                            name="mensaje"
                            rows="5"
                            maxlength="3000"
                            placeholder="Describí el problema con el mayor detalle posible…"
                            required
                            style="width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid var(--color-border-secondary);border-radius:10px;background:var(--color-background-primary);color:var(--color-text-primary);font-size:.95rem;resize:vertical;font-family:inherit;"
                        ></textarea>
                    </div>

                    <div>
                        <label style="display:block;margin-bottom:6px;font-weight:600;font-size:.92rem;">
                            Imagen (opcional)
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="file" id="reporteImagen" name="imagen" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <span class="btn-secondary btn-small" style="cursor:pointer;" id="reporteImagenLabel">Elegir imagen (opcional)</span>
                        </label>
                        <p class="help-text" style="margin-top:6px;">JPG, PNG o WEBP · máx. 5 MB</p>
                    </div>

                    <div id="reporteFeedback" role="alert"></div>
                </div>

                <div class="lteco-modal__actions">
                    <button type="button" class="btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn" id="reporteProblemaEnviar">Enviar reporte</button>
                </div>
            </form>
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
            width: min(540px, 100%);
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
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
            border-radius: 12px;
            background: transparent;
            color: inherit;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lteco-modal__actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-top: 1px solid var(--color-border-secondary, rgba(255,255,255,.12));
        }
    </style>
    </main>
    <?php
    require_once __DIR__ . "/../includes/footer.php";
    exit;
}

requiereAdmin();
$distribuidores = $consulta->listarDistribuidores($tablasDistribuidorListas);

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>
<main class="main">
    <div class="topbar">
        <div>
            <h1>Distribuidores</h1>
            <p class="subtle">Gestioná distribuidores, comisiones y stock asignado.</p>
        </div>
        <div class="actions-row">
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/pedidos.php') ?>">Pedidos</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/estado_cuenta.php') ?>">Estado de cuenta</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/ventas.php') ?>">Ventas</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('distribuidores/asignar_stock.php') ?>">Asignar stock</a>
            <a class="btn" href="<?= panelBaseUrl('distribuidores/crear.php') ?>">+ Nuevo distribuidor</a>
        </div>
    </div>

    <?php require_once __DIR__ . "/../includes/flash.php"; ?>

    <?php if (!$tablasDistribuidorListas): ?>
        <div class="notice notice--error">
            Falta aplicar la migración de distribuidores. Ejecutá
            <code>database/migrations/2026_05_28_distribuidor_stock_pedidos.sql</code>
            en la base <code>lteco_db</code> para habilitar stock, pedidos y ventas propias.
        </div>
    <?php endif; ?>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Distribuidores</h2>
            </div>
            <div class="result-pill-v4"><?= count($distribuidores) ?> resultados</div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Comisión %</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Stock asignado</th>
                    <th>Estado</th>
                    <th>Alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($distribuidores as $d): ?>
                    <tr>
                        <td><strong><?= h($d['Nombre']) ?></strong></td>
                        <td><?= number_format((float)$d['ComisionPct'], 2) ?>%</td>
                        <td><?= h($d['Contacto'] ?? '-') ?></td>
                        <td><?= h($d['Telefono'] ?? '-') ?></td>
                        <td><strong><?= (int)$d['StockAsignado'] ?></strong></td>
                        <td>
                            <?php if ((int)$d['Activo'] === 1): ?>
                                <span class="badge badge-disponible">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-oculto">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(substr((string)($d['FechaAlta'] ?? ''), 0, 10)) ?></td>
                        <td>
                            <div class="actions-row" style="gap:6px;">
                                <a class="btn-secondary btn-small" href="<?= panelBaseUrl('distribuidores/editar.php?id=' . urlencode((string)$d['IdDistribuidor'])) ?>">Editar</a>
                                <a class="btn-secondary btn-small" href="<?= panelBaseUrl('distribuidores/asignar_stock.php?id=' . urlencode((string)$d['IdDistribuidor'])) ?>">Asignar stock</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$distribuidores): ?>
                    <tr><td colspan="8" class="help-text">No hay distribuidores registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
