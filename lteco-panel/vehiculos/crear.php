<?php
$pageTitle = "Nuevo vehículo | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
 
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

$puedeGestionarCatalogoWeb = esSuperadmin();
$errores = [];
$avisos = [];
$connection = \Lteco\Infrastructure\Db\Connection::desdeGlobal();
$vehiculoRepository = new \Lteco\Infrastructure\Repository\VehiculoRepository($connection);
$vehiculoCrear = new \Lteco\Application\Vehiculo\VehiculoCrearService($vehiculoRepository);
$importacionConsulta = new \Lteco\Application\Importacion\ImportacionConsultaService(
    new \Lteco\Infrastructure\Repository\ImportacionConsultaRepository($connection)
);
$configuracionService = new \Lteco\Application\Configuracion\ConfiguracionService(
    new \Lteco\Infrastructure\Repository\ConfiguracionRepository($connection)
);


$form = [
    'numero_motor' => '',
    'numero_importacion' => '',
    'modelo' => '',
    'color' => '',
    'descripcion' => '',
    'descripcion_web' => '',
    'costo' => '',
    'gasto_total' => '',
    'precio_venta' => '',
    'precio_distribuidor' => '',
    'moneda' => LTECO_DEFAULT_CURRENCY,
    'estado' => 'Disponible',
    'mostrar_en_web' => 0,
    'destacado_web' => 0,
    'orden_web' => (string)siguienteOrdenWebVehiculo($pdo),
    'texto_boton_web' => 'Consultar',
    'slug' => '',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        $errores[] = "La sesión del formulario venció. Volvé a intentar.";
    }
    foreach ($form as $campo => $valorPorDefecto) {
        if (in_array($campo, ['mostrar_en_web', 'destacado_web'], true)) {
            $form[$campo] = $puedeGestionarCatalogoWeb && isset($_POST[$campo]) ? 1 : 0;
            continue;
        }

        $form[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    if (!$puedeGestionarCatalogoWeb) {
        $form['descripcion_web'] = '';
        $form['mostrar_en_web'] = 0;
        $form['destacado_web'] = 0;
        $form['orden_web'] = '0';
        $form['texto_boton_web'] = 'Consultar';
        $form['slug'] = '';
    }

    $numeroMotor = strtoupper($form['numero_motor']);
    $modelo = trim((string)($_POST['modelo'] ?? $form['modelo'] ?? ''));
    $color = $form['color'];
    $descripcion = $form['descripcion'];
    $descripcionWeb = $form['descripcion_web'];
    $costo = decimalNoNegativo($form['costo']);
    $gastoTotal = decimalNoNegativo($form['gasto_total']);
    $precioVenta = decimalNoNegativo($form['precio_venta']);
    $precioDistribuidor = $form['precio_distribuidor'] !== '' ? decimalNoNegativo($form['precio_distribuidor']) : null;
    $moneda = in_array($form['moneda'], monedasSistema(), true) ? $form['moneda'] : LTECO_DEFAULT_CURRENCY;
    $estado = in_array($form['estado'], ['Disponible', 'Reservado', 'Vendido', 'Oculto', 'Sin stock'], true) ? $form['estado'] : 'Disponible';
    $ordenWeb = $puedeGestionarCatalogoWeb && $form['orden_web'] !== '' ? (int)$form['orden_web'] : 0;
    $textoBotonWeb = $puedeGestionarCatalogoWeb ? (limpiarTextoOpcional($form['texto_boton_web']) ?? 'Consultar') : 'Consultar';
    $numeroImportacion = !empty($form['numero_importacion']) ? (int)$form['numero_importacion'] : null;
    $tipoCambioImportacion = null;
    if ($numeroImportacion) {
        $tipoCambioImportacion = $importacionConsulta->tipoCambioActivo($numeroImportacion);
    }
    $slugManual = $puedeGestionarCatalogoWeb ? slugify($form['slug']) : '';
    $stock = stockSegunEstadoMoto($estado);
    $publicacion = vehiculoNormalizarPublicacion($estado, $stock, (int)$form['mostrar_en_web'], (int)$form['destacado_web']);
    $mostrarEnWeb = $publicacion['MostrarEnWeb'];
    $destacadoWeb = $publicacion['DestacadoWeb'];
    $empresa = $configuracionService->obtenerEmpresa();
    $empresaRut = trim((string)($empresa['RUT'] ?? '')) ?: defaultEmpresaRut();
    $hayImagenNueva = $puedeGestionarCatalogoWeb && !empty($_FILES['imagenes']['name'][0]);

    if ($hayImagenNueva) {
        $errores = array_merge($errores, validarImagenesVehiculo($_FILES['imagenes']));
    }

    $form['numero_motor'] = $numeroMotor;
    $form['slug'] = $slugManual;

    if ($numeroMotor === '') {
        $errores[] = 'El número de motor es obligatorio.';
    }

    if ($modelo === '') {
        $errores[] = 'El modelo es obligatorio.';
    }

    if ($precioVenta < 0 || $costo < 0 || $gastoTotal < 0 || ($precioDistribuidor !== null && $precioDistribuidor < 0)) {
        $errores[] = 'Los importes no pueden ser negativos.';
    }

    if ($precioVenta <= 0) {
        $errores[] = 'El precio de venta debe ser mayor a 0.';
    }

    if (!$vehiculoRepository->numeroMotorDisponible($numeroMotor)) {
        $errores[] = 'Ya existe un vehículo con ese número de motor.';
    }

    if ($puedeGestionarCatalogoWeb && $slugManual !== '' && !$vehiculoRepository->slugProductoDisponible($slugManual)) {
        $errores[] = 'Ese slug ya está en uso. Cambialo para evitar conflictos en la web.';
    }

    if ($puedeGestionarCatalogoWeb && ((int)$form['mostrar_en_web'] === 1 || (int)$form['destacado_web'] === 1)) {
        $slugPreview = $slugManual !== '' ? $slugManual : slugify(($modelo !== '' ? $modelo : 'moto') . '-v0001');
        $checklist = obtenerChecklistPublicacion([
            // LTECO:VEHICULO_CREAR_CHECKLIST_MODELO_V3
            'modelo' => $modelo,
            'Nombre' => $modelo,
            'precio_venta' => $precioVenta,
            'slug' => $slugPreview,
            'descripcion_web' => $descripcionWeb,
        ], $hayImagenNueva);

        if (!$checklist['publicable']) {
              // LTECO:VEHICULO_CREAR_PUBLICACION_NO_BLOQUEA_GUARDADO_V3
              // Guardar el vehículo interno no debe depender de que esté listo para catálogo público.
              // Si faltan datos web, se guarda como inventario interno y se desactiva publicación.
              $avisos[] = 'El vehículo se guardará internamente, pero no se publicará en web porque faltan: ' . implode(', ', $checklist['faltantes']) . '.';
              $form['mostrar_en_web'] = 0;
              $form['destacado_web'] = 0;
              $mostrarEnWeb = 0;
              $destacadoWeb = 0;
          }
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $resultado = $vehiculoCrear->crear([
                'puedeGestionarCatalogoWeb' => $puedeGestionarCatalogoWeb,
                'modelo'                    => $modelo,
                'slugManual'                => $slugManual,
                'descripcion'               => $descripcion,
                'descripcionWeb'            => $descripcionWeb,
                'costo'                     => $costo,
                'gastoTotal'                => $gastoTotal,
                'precioVenta'               => $precioVenta,
                'precioDistribuidor'        => $precioDistribuidor,
                'moneda'                    => $moneda,
                'stock'                     => $stock,
                'estado'                    => $estado,
                'mostrarEnWeb'              => $mostrarEnWeb,
                'destacadoWeb'              => $destacadoWeb,
                'ordenWeb'                  => $ordenWeb,
                'textoBotonWeb'             => $textoBotonWeb,
                'empresaRut'                => $empresaRut,
                'numeroMotor'               => $numeroMotor,
                'color'                     => $color,
                'numeroImportacion'         => $numeroImportacion,
                'tipoCambioImportacion'     => $tipoCambioImportacion,
            ]);

            $idVehiculo = $resultado['idVehiculo'];
            $idProducto = $resultado['idProducto'];

            if ($puedeGestionarCatalogoWeb && $hayImagenNueva) {
                guardarImagenesVehiculo($pdo, $idVehiculo, $_FILES['imagenes']);
            }

            $pdo->commit();
            registrarAuditoria($pdo, 'CREAR_VEHICULO', 'Vehículos', 'Vehículo ' . $idVehiculo . ' creado.', ['id_vehiculo' => $idVehiculo, 'id_producto' => $idProducto]);
            redirect(panelBaseUrl('vehiculos/editar.php?' . buildQuery([
                'id' => $idVehiculo,
                'ok' => 'creado',
            ])));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logPanelError('vehiculo_crear', $e);
            $errores[] = mensajeErrorSeguro($e, 'No se pudo guardar el vehículo.');
        }
    }
}

$slugSugerido = slugify(($form['modelo'] ?: 'moto') . '-v0001');
$importaciones = $importacionConsulta->listarActivasParaSelector();
$checklistActual = $puedeGestionarCatalogoWeb ? obtenerChecklistPublicacion([
    // LTECO:VEHICULO_CREAR_CHECKLIST_MODELO_V3
    'modelo' => $form['modelo'],
    'Nombre' => $form['modelo'],
    'precio_venta' => $form['precio_venta'] !== '' ? (float)$form['precio_venta'] : 0,
    'slug' => $form['slug'] !== '' ? $form['slug'] : $slugSugerido,
    'descripcion_web' => $form['descripcion_web'],
], !empty($_FILES['imagenes']['name'][0])) : ['publicable' => false, 'faltantes' => []];
$publicacionPrevista = vehiculoNormalizarPublicacion(
    $form['estado'],
    stockSegunEstadoMoto($form['estado']),
    (int)$form['mostrar_en_web'],
    (int)$form['destacado_web']
);

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Nuevo vehículo</h1>
            <p class="subtle">Carga de inventario interno, costos, precios y estado comercial.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <?php if ($errores): ?>
        <div class="notice notice--error">
            <strong>No se pudo guardar.</strong>
            <ul class="notice-list">
                <?php foreach ($errores as $error): ?>
                    <li><?= h($error, '') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="vehiculo-form" data-has-existing-image="0">
        <?= csrfInput() ?>
        <div class="split-grid split-grid--form">
            <section class="section-box form-section">
                <div class="section-head">
                    <div>
                        <h2>Identificaci&oacute;n</h2>
                        <p class="subtle">Datos internos de esta unidad.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ID veh&iacute;culo</label>
                        <input type="text" value="Se genera autom&aacute;ticamente al guardar" disabled>
                        <small class="field-help">Formato: V0001, V0002, V0003...</small>
                    </div>
                    <div class="form-group">
                        <label>N&uacute;mero de motor</label>
                        <input type="text" name="numero_motor" value="<?= h($form['numero_motor'], '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" name="modelo" value="<?= h($form['modelo'], '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color" value="<?= h($form['color'], '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" required>
                            <?php foreach (['Disponible', 'Reservado', 'Vendido', 'Oculto', 'Sin stock'] as $estado): ?>
                                <option value="<?= $estado ?>" <?= $form['estado'] === $estado ? 'selected' : '' ?>><?= $estado ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Moneda</label>
                        <select name="moneda" required>
                            <option value="USD" <?= $form['moneda'] === 'USD' ? 'selected' : '' ?>>D&oacute;lares (USD)</option>
                            <option value="UYU" <?= $form['moneda'] === 'UYU' ? 'selected' : '' ?>>Pesos (UYU)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Importaci&oacute;n <small class="field-help">(opcional)</small></label>
                        <select name="numero_importacion" id="numero_importacion">
                            <option value="">-- Sin importaci&oacute;n --</option>
                            <?php foreach ($importaciones as $imp): ?>
                                <option value="<?= (int)$imp['Numero'] ?>"
                                    data-tc="<?= (float)$imp['TipoCambioUSD'] ?>"
                                    <?= ((string)($form['numero_importacion'] ?? '') === (string)$imp['Numero']) ? 'selected' : '' ?>>
                                    Importaci&oacute;n <?= (int)$imp['Numero'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help" id="imp-tc-info" style="display:none;">TC de esta importaci&oacute;n: <strong id="imp-tc-valor"></strong></small>
                    </div>
                </div>
            </section>

            <section class="section-box form-section">
                <div class="section-head">
                    <div>
                        <h2>Costos y precios</h2>
                        <p class="subtle">Impacta en inventario, margen y web.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Costo</label>
                        <input type="number" step="0.01" min="0" name="costo" value="<?= h($form['costo'], '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gasto total</label>
                        <input type="number" step="0.01" min="0" name="gasto_total" value="<?= h($form['gasto_total'], '') ?>">
                        <small class="field-help">Inversi&oacute;n real de la unidad.</small>
                    </div>
                    <div class="form-group">
                        <label>Precio venta</label>
                        <input type="number" step="0.01" min="0" name="precio_venta" value="<?= h($form['precio_venta'], '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Precio distribuidor</label>
                        <input type="number" step="0.01" min="0" name="precio_distribuidor" value="<?= h($form['precio_distribuidor'], '') ?>">
                        <small class="field-help">Interno, no se muestra en web.</small>
                    </div>
                </div>
            </section>
        </div>

        <section class="section-box form-section">
            <div class="section-head">
                <div>
                    <h2>Notas internas</h2>
                    <p class="subtle">Información operativa para el equipo.</p>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group full">
                    <label>Descripci&oacute;n interna</label>
                    <textarea name="descripcion" placeholder="Notas internas, resumen comercial o detalles generales."><?= h($form['descripcion'], '') ?></textarea>
                </div>
            </div>
        </section>

        <?php if ($puedeGestionarCatalogoWeb): ?>
        <details class="section-box form-section" style="padding:0;">
            <summary style="padding:20px 24px;cursor:pointer;font-weight:500;font-size:1rem;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Cat&aacute;logo web <small style="font-weight:400;color:var(--color-text-secondary);margin-left:8px;">opcional — para publicar en la web p&uacute;blica</small></span>
                <span style="color:var(--color-text-tertiary);font-size:.85rem;">&#9660;</span>
            </summary>
            <div style="padding:0 24px 24px;">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Descripci&oacute;n web</label>
                    <textarea name="descripcion_web" placeholder="Texto pensado para el detalle p&uacute;blico del producto."><?= h($form['descripcion_web'], '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?= h($form['slug'], '') ?>" placeholder="<?= h($slugSugerido, '') ?>">
                    <small class="field-help">Vac&iacute;o = se genera autom&aacute;tico.</small>
                </div>
                <div class="form-group">
                    <label>Orden web</label>
                    <input type="number" name="orden_web" value="<?= h($form['orden_web'], '0') ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Texto del bot&oacute;n web</label>
                    <input type="text" name="texto_boton_web" value="<?= h($form['texto_boton_web'], '') ?>" maxlength="80">
                </div>
                <div class="form-group">
                    <label>Im&aacute;genes</label>
                    <input type="file" name="imagenes[]" id="imagenes-input" multiple accept="image/jpeg,image/png,image/webp">
                    <small class="field-help">JPG, PNG, WEBP. M&aacute;x 8 im&aacute;genes de 5 MB. La primera queda como principal.</small>
                    <div id="imagenes-preview" class="upload-preview-grid"></div>
                </div>
                <div class="form-group full">
                    <div class="checks-grid">
                        <label class="check-card">
                            <input type="checkbox" name="mostrar_en_web" value="1" <?= (int)$form['mostrar_en_web'] === 1 ? 'checked' : '' ?>>
                            <span>
                                <strong>Mostrar en web</strong>
                                <small>Solo activo si est&aacute; disponible y con stock.</small>
                            </span>
                        </label>
                        <label class="check-card">
                            <input type="checkbox" name="destacado_web" value="1" <?= (int)$form['destacado_web'] === 1 ? 'checked' : '' ?>>
                            <span>
                                <strong>Marcar como destacado</strong>
                                <small>Requiere que tambi&eacute;n pueda mostrarse en la web.</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            </div>
        </details>

        <section class="section-box publication-box <?= $publicacionPrevista['MostrarEnWeb'] ? 'publication-box--ok' : 'publication-box--warn' ?>" id="publication-box">
            <div class="section-head">
                <div>
                    <h2>Estado de publicación</h2>
                    <p class="subtle">Vista previa en vivo de cómo queda la unidad si guardás ahora.</p>
                </div>
            </div>

            <div class="publication-grid">
                <div>
                    <strong id="pub-main-text"><?= $publicacionPrevista['MostrarEnWeb'] ? 'Se publicará en la web' : 'No se publicará todavía' ?></strong>
                    <p>
                        Estado: <span id="pub-state-badge" class="badge <?= claseBadgeEstado($form['estado']) ?>"><?= h($form['estado'], '') ?></span>
                        · Stock resultante: <strong id="pub-stock"><?= stockSegunEstadoMoto($form['estado']) ?></strong>
                        · Slug: <strong id="pub-slug"><?= h($form['slug'] !== '' ? $form['slug'] : $slugSugerido, '') ?></strong>
                    </p>
                </div>

                <div>
                    <span id="pub-checklist" class="issue-tag <?= $checklistActual['publicable'] ? 'issue-tag--success' : '' ?>">
                        <?= $checklistActual['publicable'] ? 'Catálogo completo' : 'Faltan: ' . h(implode(', ', $checklistActual['faltantes']), '') ?>
                    </span>
                    <span id="pub-featured-warning" class="issue-tag <?= ((int)$form['destacado_web'] === 1 && !$publicacionPrevista['DestacadoWeb']) ? '' : 'is-hidden' ?>">El destacado no se activará con este estado</span>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn">Guardar vehículo</button>
            <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Cancelar</a>
        </div>
    </form>
</main>

<script nonce="<?= cspNonce() ?>">
(function () {
    const impSelect = document.getElementById('numero_importacion');
    const impTcInfo = document.getElementById('imp-tc-info');
    const impTcValor = document.getElementById('imp-tc-valor');

    if (impSelect) {
        impSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const tc = opt ? opt.dataset.tc : null;
            if (tc && parseFloat(tc) > 0) {
                impTcValor.textContent = '$ ' + parseFloat(tc).toFixed(2) + ' UYU';
                impTcInfo.style.display = 'block';
            } else {
                impTcInfo.style.display = 'none';
            }
        });
    }
    const form = document.getElementById('vehiculo-form');
    if (!form) return;

    const badgeClasses = ['badge-disponible', 'badge-reservado', 'badge-vendido', 'badge-oculto', 'badge-sin-stock', 'badge-default'];
    const stateClassMap = {
        'Disponible': 'badge-disponible',
        'Reservado': 'badge-reservado',
        'Vendido': 'badge-vendido',
        'Oculto': 'badge-oculto',
        'Sin stock': 'badge-sin-stock'
    };

    const get = (selector) => form.querySelector(selector);
    const field = (name) => form.querySelector(`[name="${name}"]`);

    function slugify(text) {
        return text
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-+/g, '-');
    }

    function updatePreview() {
        const estado = field('estado')?.value || 'Disponible';
        const mostrar = !!field('mostrar_en_web')?.checked;
        const destacado = !!field('destacado_web')?.checked;
        const precio = parseFloat(field('precio_venta')?.value || '0');
        const descripcion = (field('descripcion_web')?.value || '').trim();
        const slugManual = (field('slug')?.value || '').trim();
        const modelo = (field('modelo')?.value || '').trim();
        const hasImage = (field('imagenes[]')?.files?.length || 0) > 0;
        const slugPreview = slugManual || slugify((modelo || 'moto') + '-v0001');
        const stock = ['Vendido', 'Sin stock'].includes(estado) ? 0 : 1;
        const canShow = estado === 'Disponible' && stock > 0 && mostrar;
        const canFeature = canShow && destacado;
        const missing = [];

        if (precio <= 0) missing.push('precio de venta');
        if (!slugPreview) missing.push('slug');
        if (!descripcion) missing.push('descripción web');
        const quierePublicar = mostrar || destacado;

        if (quierePublicar && !hasImage) {
            missing.push('imagen principal');
        }

        const box = document.getElementById('publication-box');
        const mainText = document.getElementById('pub-main-text');
        const badge = document.getElementById('pub-state-badge');
        const stockNode = document.getElementById('pub-stock');
        const slugNode = document.getElementById('pub-slug');
        const checklistNode = document.getElementById('pub-checklist');
        const featuredNode = document.getElementById('pub-featured-warning');

        if (!box || !mainText || !badge || !stockNode || !slugNode || !checklistNode || !featuredNode) {
            return;
        }

        box.classList.toggle('publication-box--ok', canShow);
        box.classList.toggle('publication-box--warn', !canShow);
        mainText.textContent = canShow ? 'Se publicará en la web' : 'No se publicará todavía';
        badge.textContent = estado;
        badge.classList.remove(...badgeClasses);
        badge.classList.add(stateClassMap[estado] || 'badge-default');
        stockNode.textContent = String(stock);
        slugNode.textContent = slugPreview || '—';

        if (missing.length === 0) {
            checklistNode.textContent = 'Catálogo completo';
            checklistNode.classList.add('issue-tag--success');
        } else {
            checklistNode.textContent = 'Faltan: ' + missing.join(', ');
            checklistNode.classList.remove('issue-tag--success');
        }

        featuredNode.classList.toggle('is-hidden', !(destacado && !canFeature));
    }



    const imageInput = document.getElementById('imagenes-input');
    const previewBox = document.getElementById('imagenes-preview');

    function updateImagePreview() {
        if (!imageInput || !previewBox) return;
        previewBox.innerHTML = '';
        const files = Array.from(imageInput.files || []);

        if (!files.length) {
            previewBox.innerHTML = '<div class="thumb thumb--placeholder thumb--placeholder-full">Todavía no seleccionaste imágenes</div>';
            return;
        }

        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'upload-preview-item';

            const img = document.createElement('img');
            img.className = 'upload-preview-img';

            const label = document.createElement('small');
            label.textContent = index === 0 ? 'Principal' : 'Secundaria';

            const reader = new FileReader();
            reader.onload = (e) => { img.src = e.target?.result || ''; };
            reader.readAsDataURL(file);

            item.appendChild(img);
            item.appendChild(label);
            previewBox.appendChild(item);
        });
    }

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);
    imageInput?.addEventListener('change', updateImagePreview);
    updateImagePreview();
    updatePreview();
})();
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
