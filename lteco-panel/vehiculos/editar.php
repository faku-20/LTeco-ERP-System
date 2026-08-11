<?php
$pageTitle = "Editar vehículo | ERP";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereLogin();
requiereAdmin();
require_once __DIR__ . "/../includes/helpers.php";

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$puedeGestionarCatalogoWeb = esSuperadmin();

if (!isset($_GET['id']) || trim((string)$_GET['id']) === '') {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'ID de vehículo no recibido.');
}

$idVehiculo = trim((string) $_GET['id']);

if ($idVehiculo === '') {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'ID de vehículo inválido.');
}

$errores = [];
$avisos = [];

$vehiculoRepository = new \Lteco\Infrastructure\Repository\VehiculoRepository(
    \Lteco\Infrastructure\Db\Connection::desdeGlobal()
);
$vehiculoEditar = new \Lteco\Application\Vehiculo\VehiculoEditarService($vehiculoRepository);
$importacionConsulta = new \Lteco\Application\Importacion\ImportacionConsultaService(
    new \Lteco\Infrastructure\Repository\ImportacionConsultaRepository(
        \Lteco\Infrastructure\Db\Connection::desdeGlobal()
    )
);
$datosEdicion = $vehiculoEditar->cargar($idVehiculo);
$vehiculo = $datosEdicion['vehiculo'];

if (!$vehiculo) {
    redirectWithFlash(panelBaseUrl('vehiculos/index.php'), 'error', 'Vehículo no encontrado.');
}


/*
 |--------------------------------------------------------------------------
 | V3.3.1 - Garantía vigente
 |--------------------------------------------------------------------------
 */
$garantia = $datosEdicion['garantia'];


/*
 |--------------------------------------------------------------------------
 | V3.3.2 - Services programados
 |--------------------------------------------------------------------------
 */
$servicesVehiculo = $datosEdicion['services'];

$recargarImagenes = static function () use ($vehiculoEditar, $idVehiculo): array {
    return $vehiculoEditar->imagenes($idVehiculo);
};

$imagenes = $datosEdicion['imagenes'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        redirectWithFlash(
            panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)),
            'error',
            'La sesión del formulario venció o el envío no es válido. Volvé a intentar.'
        );
    }
    $accionImagen = $_POST['accion'] ?? '';

    if (in_array($accionImagen, ['set_principal', 'delete_image', 'move_image'], true)) {
        if (!$puedeGestionarCatalogoWeb) {
            redirectWithFlash(
                panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)),
                'error',
                'Solo el Superadmin puede gestionar imágenes y catálogo web.'
            );
        }

        $idImagen = (int)($_POST['id_imagen'] ?? 0);

        if ($idImagen <= 0) {
            $errores[] = 'No se recibió correctamente la imagen a modificar.';
        } else {
            try {
                $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
                $repo = new Lteco\Infrastructure\Repository\VehiculoRepository($conexion);
                $imagenService = new Lteco\Application\Vehiculo\VehiculoImagenService($repo);

                $pdo->beginTransaction();

                if ($accionImagen === 'set_principal') {
                    $imagenService->marcarPrincipal($idVehiculo, $idImagen);
                    $okMessage = 'Imagen principal actualizada.';
                } elseif ($accionImagen === 'delete_image') {
                    $rutaEliminar = $imagenService->eliminar($idVehiculo, $idImagen);

                    registrarAuditoria(

                        $pdo,

                        'ELIMINAR_IMAGEN_VEHICULO',

                        'Vehículos',

                        'Imagen eliminada del vehículo: ' . (string)($vehiculo['Modelo'] ?? $idVehiculo),

                        [

                            'id_vehiculo' => $idVehiculo,

                            'id_producto' => (int)($vehiculo['IdProducto'] ?? 0),

                            'id_imagen' => $idImagen ?? null,

                            'ruta' => $rutaEliminar ?? null,

                        ]

                    );

                    
                    $pdo->commit();
                    eliminarArchivoProyecto($rutaEliminar);
                    redirectWithFlash(
                        panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)),
                        'success',
                        'Imagen eliminada correctamente.'
                    );
                } elseif ($accionImagen === 'move_image') {
                    $direccion = ($_POST['direccion'] ?? '') === 'up' ? 'up' : 'down';
                    $imagenService->mover($idVehiculo, $idImagen, $direccion);
                    $okMessage = 'Orden de imágenes actualizado.';
                }

                if ($pdo->inTransaction()) {
                    $accionAuditoriaImagen = ($accionImagen === 'set_principal')
                        ? 'CAMBIAR_IMAGEN_PRINCIPAL_VEHICULO'
                        : 'MOVER_IMAGEN_VEHICULO';

                    $detalleAuditoriaImagen = ($accionImagen === 'set_principal')
                        ? 'Imagen principal actualizada para vehículo: '
                        : 'Orden de imágenes actualizado para vehículo: ';

                    registrarAuditoria(
                        $pdo,
                        $accionAuditoriaImagen,
                        'Vehículos',
                        $detalleAuditoriaImagen . (string)($vehiculo['Modelo'] ?? $idVehiculo),
                        [
                            'id_vehiculo' => $idVehiculo,
                            'id_producto' => (int)($vehiculo['IdProducto'] ?? 0),
                            'id_imagen' => $idImagen ?? null,
                            'accion_imagen' => $accionImagen ?? null,
                            'direccion' => $direccion ?? null,
                        ]
                    );
                                        $pdo->commit();
                }

                redirectWithFlash(
                    panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)),
                    'success',
                    $okMessage ?? 'Imagen principal actualizada.'
                );
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errores[] = match ($accionImagen) {
                    'set_principal' => mensajeErrorSeguro($e, 'No se pudo cambiar la imagen principal.'),
                    'delete_image' => mensajeErrorSeguro($e, 'No se pudo eliminar la imagen.'),
                    'move_image' => mensajeErrorSeguro($e, 'No se pudo reordenar la imagen.'),
                    default => mensajeErrorSeguro($e, 'No se pudo modificar la imagen.'),
                };
            }
        }
    }
}

$numeroMotorQr = trim((string)($vehiculo['NumeroMotor'] ?? ''));
$qrVehiculoPayload = $numeroMotorQr !== ''
    ? 'LTECO|' . trim((string)$idVehiculo) . '|' . $numeroMotorQr
    : '';

$form = [
    'numero_motor' => (string)($vehiculo['NumeroMotor'] ?? ''),
    'modelo' => (string)($vehiculo['Modelo'] ?? ''),
    'color' => (string)($vehiculo['Color'] ?? ''),
    'descripcion' => (string)($vehiculo['Descripcion'] ?? ''),
    'descripcion_web' => (string)($vehiculo['DescripcionWeb'] ?? ''),
    'costo' => (string)($vehiculo['Costo'] ?? ''),
    'gasto_total' => (string)($vehiculo['GastoTotal'] ?? ''),
    'precio_venta' => (string)($vehiculo['PrecioVenta'] ?? ''),
    'precio_distribuidor' => (string)($vehiculo['PrecioDistribuidor'] ?? ''),
    'moneda' => (string)($vehiculo['Moneda'] ?? 'USD'),
    'estado' => (string)($vehiculo['Estado'] ?? 'Disponible'),
    'mostrar_en_web' => (int)($vehiculo['MostrarEnWeb'] ?? 0),
    'destacado_web' => (int)($vehiculo['DestacadoWeb'] ?? 0),
    'orden_web' => (string)($vehiculo['OrdenWeb'] ?? '0'),
    'texto_boton_web' => (string)($vehiculo['TextoBotonWeb'] ?? 'Consultar'),
    'slug' => (string)($vehiculo['Slug'] ?? ''),
    'numero_importacion' => (string)($vehiculo['NumeroImportacion'] ?? ''),
];
$importaciones = $importacionConsulta->listarActivasParaSelector();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array(($_POST['accion'] ?? ''), ['set_principal', 'delete_image', 'move_image'], true)) {
    try {
        requirePost();
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        redirectWithFlash(
            panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)),
            'error',
            'La sesión del formulario venció o el envío no es válido. Volvé a intentar.'
        );
    }
    foreach ($form as $campo => $valorPorDefecto) {
        if (in_array($campo, ['mostrar_en_web', 'destacado_web'], true)) {
            $form[$campo] = $puedeGestionarCatalogoWeb && isset($_POST[$campo]) ? 1 : 0;
            continue;
        }

        $form[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    if (!$puedeGestionarCatalogoWeb) {
        $form['descripcion_web'] = (string)($vehiculo['DescripcionWeb'] ?? '');
        $form['mostrar_en_web'] = (int)($vehiculo['MostrarEnWeb'] ?? 0);
        $form['destacado_web'] = (int)($vehiculo['DestacadoWeb'] ?? 0);
        $form['orden_web'] = (string)($vehiculo['OrdenWeb'] ?? '0');
        $form['texto_boton_web'] = (string)($vehiculo['TextoBotonWeb'] ?? 'Consultar');
        $form['slug'] = (string)($vehiculo['Slug'] ?? '');
    }

    $numeroMotor = strtoupper($form['numero_motor']);
    $modelo = $form['modelo'];
    $color = $form['color'];
    $descripcion = $form['descripcion'];
    $descripcionWeb = $form['descripcion_web'];
    $costo = decimalNoNegativo($form['costo']);
    $gastoTotal = decimalNoNegativo($form['gasto_total']);
    $precioVenta = decimalNoNegativo($form['precio_venta']);
    $precioDistribuidor = $form['precio_distribuidor'] !== '' ? decimalNoNegativo($form['precio_distribuidor']) : null;
    $moneda = in_array($form['moneda'], ['USD', 'UYU'], true) ? $form['moneda'] : 'USD';
    $estado = in_array($form['estado'], ['Disponible', 'Reservado', 'Vendido', 'Oculto', 'Sin stock'], true) ? $form['estado'] : 'Disponible';
    $ordenWeb = $puedeGestionarCatalogoWeb && $form['orden_web'] !== '' ? (int)$form['orden_web'] : (int)($vehiculo['OrdenWeb'] ?? 0);
    $textoBotonWeb = $puedeGestionarCatalogoWeb ? (limpiarTextoOpcional($form['texto_boton_web']) ?? 'Consultar') : (string)($vehiculo['TextoBotonWeb'] ?? 'Consultar');
    $numeroImportacion = !empty($form['numero_importacion']) ? (int)$form['numero_importacion'] : null;
    $tipoCambioImportacion = null;
    if ($numeroImportacion) {
        $tipoCambioImportacion = $importacionConsulta->tipoCambioActivo($numeroImportacion);
    }
    $slug = $puedeGestionarCatalogoWeb ? slugify($form['slug'] !== '' ? $form['slug'] : ($modelo . '-' . $idVehiculo)) : (string)($vehiculo['Slug'] ?? '');
    $stock = stockSegunEstadoMoto($estado);
    $publicacion = vehiculoNormalizarPublicacion($estado, $stock, (int)$form['mostrar_en_web'], (int)$form['destacado_web']);
    $mostrarEnWeb = $publicacion['MostrarEnWeb'];
    $destacadoWeb = $publicacion['DestacadoWeb'];
    $hayImagenNueva = $puedeGestionarCatalogoWeb && !empty($_FILES['imagenes']['name'][0]);
    if ($hayImagenNueva) {
        $errores = array_merge($errores, validarImagenesVehiculo($_FILES['imagenes']));
    }
    $hayImagenPrincipal = false;

    foreach ($imagenes as $img) {
        if ((int)$img['EsPrincipal'] === 1) {
            $hayImagenPrincipal = true;
            break;
        }
    }

    $form['numero_motor'] = $numeroMotor;
    $form['slug'] = $slug;

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

    if (!$vehiculoRepository->numeroMotorDisponible($numeroMotor, $idVehiculo)) {
        $errores[] = 'Ya existe otro vehículo con ese número de motor.';
    }

    if ($puedeGestionarCatalogoWeb && $slug !== '' && !$vehiculoRepository->slugProductoDisponibleExcepto($slug, (int)$vehiculo['IdProducto'])) {
        $errores[] = 'Ese slug ya está en uso. Cambialo para evitar conflictos en la web.';
    }

    $analisisEdicion = $puedeGestionarCatalogoWeb ? vehiculoAnalizarPublicacion([
        'Estado' => $estado,
        'Stock' => $stock,
        'PrecioVenta' => $precioVenta,
        'Slug' => $slug,
        'DescripcionWeb' => $descripcionWeb,
        'Modelo' => $modelo,
        'TieneImagen' => ($hayImagenPrincipal || $hayImagenNueva) ? 1 : 0,
        'MostrarEnWeb' => (int)$form['mostrar_en_web'],
        'DestacadoWeb' => (int)$form['destacado_web'],
    ]) : ['publicable' => false, 'puede_destacarse' => false, 'motivo_publicacion' => '', 'motivo_destacado' => ''];

    if ($puedeGestionarCatalogoWeb && (int)$form['mostrar_en_web'] === 1 && !$analisisEdicion['publicable']) {
        $avisos[] = $analisisEdicion['motivo_publicacion'] . ' Se guardará como no visible en la web.';
    }

    if ($puedeGestionarCatalogoWeb && (int)$form['destacado_web'] === 1 && !$analisisEdicion['puede_destacarse']) {
        $avisos[] = $analisisEdicion['motivo_destacado'] . ' Se guardará sin destacado.';
    }

    if (empty($errores)) {
        try {
            $conexion = Lteco\Infrastructure\Db\Connection::desdeGlobal();
            $repo = new Lteco\Infrastructure\Repository\VehiculoRepository($conexion);
            $service = new Lteco\Application\Vehiculo\VehiculoEditarService($repo);

            $pdo->beginTransaction();

            $service->editar([
                'idVehiculo'            => $idVehiculo,
                'idProducto'            => (int) $vehiculo['IdProducto'],
                'modelo'                => $modelo,
                'slug'                  => $slug,
                'descripcion'           => $descripcion,
                'descripcionWeb'        => $descripcionWeb,
                'costo'                 => $costo,
                'gastoTotal'            => $gastoTotal,
                'precioVenta'           => $precioVenta,
                'precioDistribuidor'    => $precioDistribuidor,
                'moneda'                => $moneda,
                'stock'                 => $stock,
                'estado'                => $estado,
                'mostrarEnWeb'          => $mostrarEnWeb,
                'destacadoWeb'          => $destacadoWeb,
                'ordenWeb'              => $ordenWeb,
                'textoBotonWeb'         => $textoBotonWeb,
                'numeroMotor'           => $numeroMotor,
                'color'                 => $color,
                'numeroImportacion'     => $numeroImportacion,
                'tipoCambioImportacion' => $tipoCambioImportacion,
                'fechaVentaActual'      => $vehiculo['FechaVenta'] ?? null,
            ]);

            if ($hayImagenNueva) {
                guardarImagenesVehiculo($pdo, $idVehiculo, $_FILES['imagenes']);
            }

            registrarAuditoria(

                $pdo,

                'EDITAR_VEHICULO',

                'Vehículos',

                'Vehículo actualizado: ' . (string)($form['modelo'] ?? $vehiculo['Modelo'] ?? $idVehiculo),

                [

                    'id_vehiculo' => $idVehiculo,

                    'id_producto' => (int)($vehiculo['IdProducto'] ?? 0),

                    'modelo_anterior' => $vehiculo['Modelo'] ?? null,

                    'modelo_nuevo' => $form['modelo'] ?? null,

                    'estado_anterior' => $vehiculo['Estado'] ?? null,

                    'estado_nuevo' => $estado ?? null,

                    'stock_anterior' => isset($vehiculo['Stock']) ? (int)$vehiculo['Stock'] : null,

                    'stock_nuevo' => $stock ?? null,

                    'mostrar_en_web' => $publicacion['MostrarEnWeb'] ?? null,

                    'destacado_web' => $publicacion['DestacadoWeb'] ?? null,

                    'imagen_nueva' => !empty($hayImagenNueva),

                ]

            );

            
            $pdo->commit();
            $mensajeExito = 'Vehículo actualizado correctamente.';
            if ($avisos) {
                $mensajeExito .= ' Ajustes automáticos: ' . implode(' ', $avisos);
            }

            redirectWithFlash(
                panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)),
                'success',
                $mensajeExito
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logPanelError('vehiculo_editar', $e);
            $errores[] = mensajeErrorSeguro($e, 'No se pudo actualizar el vehículo.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['accion'] ?? '') === 'set_principal') {
    $imagenes = $recargarImagenes();
}

$slugSugerido = slugify(($form['modelo'] ?: 'moto') . '-' . $idVehiculo);
$hayImagenPrincipal = false;
foreach ($imagenes as $img) {
    if ((int)$img['EsPrincipal'] === 1) {
        $hayImagenPrincipal = true;
        break;
    }
}
$analisisActual = vehiculoAnalizarPublicacion([
    'Estado' => $form['estado'],
    'Stock' => stockSegunEstadoMoto($form['estado']),
    'PrecioVenta' => $form['precio_venta'] !== '' ? (float)$form['precio_venta'] : 0,
    'Slug' => $form['slug'] !== '' ? $form['slug'] : $slugSugerido,
    'DescripcionWeb' => $form['descripcion_web'],
    'Modelo' => $form['modelo'],
    'TieneImagen' => ($hayImagenPrincipal || !empty($_FILES['imagenes']['name'][0])) ? 1 : 0,
    'MostrarEnWeb' => (int)$form['mostrar_en_web'],
    'DestacadoWeb' => (int)$form['destacado_web'],
]);
$checklistActual = [
    'publicable' => $analisisActual['publicable'],
    'faltantes' => $analisisActual['faltantes'],
];
$publicacionPrevista = vehiculoNormalizarPublicacion(
    $form['estado'],
    stockSegunEstadoMoto($form['estado']),
    (int)$form['mostrar_en_web'],
    (int)$form['destacado_web']
);

$qrVehiculoSrc = panelBaseUrl('vehiculos/qr.php?id=' . urlencode((string)$vehiculo['IdVehiculo']));

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main vehicle-edit-page">
    <div class="topbar vehicle-edit-topbar">
        <div>
            <span class="section-kicker">Ficha de inventario</span>
            <h1>Editar vehículo</h1>
            <p class="subtle">Ajustá la unidad, el catálogo y la imagen principal sin salir de la ficha.</p>
        </div>
        <div class="vehicle-edit-topbar__actions">
            <a class="btn" href="<?= panelBaseUrl('vehiculos/crear.php') ?>">+ Nuevo veh&iacute;culo</a>
            <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Volver</a>
            <a class="btn-secondary" target="_blank" rel="noopener" href="<?= panelBaseUrl('vehiculos/etiqueta_multi.php?ids=' . urlencode((string)$vehiculo['IdVehiculo'])) ?>">QR x2</a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <?php if ($qrVehiculoSrc !== ''): ?>
        <section class="section-box vehicle-qr-box vehicle-edit-qr">
            <div class="vehicle-qr-info">
                <span class="section-kicker">Identificación interna</span>
                <h2>QR interno del vehículo</h2>
                <p>
                    Código operativo para identificar la unidad en el taller, depósito o postventa.
                </p>
                <div class="vehicle-qr-meta-grid">
                    <div class="vehicle-qr-meta">
                        <strong>ID</strong>
                        <span><?= h((string)$vehiculo['IdVehiculo']) ?></span>
                    </div>
                    <div class="vehicle-qr-meta">
                        <strong>Motor</strong>
                        <span><?= htmlspecialchars($numeroMotorQr !== '' ? $numeroMotorQr : '-') ?></span>
                    </div>
                </div>

                <div class="form-actions u-mt-1">
                    <a class="btn-secondary" target="_blank" rel="noopener" href="<?= panelBaseUrl('vehiculos/etiqueta_multi.php?ids=' . urlencode((string)$vehiculo['IdVehiculo'])) ?>">
                        Imprimir QR x2
                    </a>
                </div>
            </div>

            <div class="vehicle-qr-code">
                <?php if ($qrVehiculoPayload !== ''): ?>
                    <div id="qr-editar-vehiculo" class="qr-local" data-qr="<?= h($qrVehiculoPayload) ?>"></div>
                <?php else: ?>
                    <div class="vehicle-qr-empty">Sin motor</div>
                <?php endif; ?>
                <small>Uso interno ERP</small>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="notice notice--error">
            <strong>No se pudieron guardar los cambios.</strong>
            <ul class="notice-list">
                <?php foreach ($errores as $error): ?>
                    <li><?= h($error, '') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($garantia): ?>
    <?php
        $estadoGarantia = (string)($garantia['Estado'] ?? 'Vigente');
        $claseGarantia = match ($estadoGarantia) {
            'Vigente' => 'vehicle-garantia-box--ok',
            'Vencida' => 'vehicle-garantia-box--warn',
            'Anulada' => 'vehicle-garantia-box--danger',
            default => '',
        };
        $clienteGarantia = trim((string)($garantia['NombreApellido'] ?? ''));
    ?>
    <section class="section-box vehicle-garantia-box <?= h($claseGarantia) ?>">
        <div class="vehicle-garantia-box__header">
            <div>
                <span class="section-kicker">Postventa</span>
                <h2>Garantía del vehículo</h2>
            </div>
            <span class="vehicle-garantia-badge"><?= h($estadoGarantia) ?></span>
        </div>
        <div class="vehicle-garantia-grid">
            <article>
                <span>Inicio</span>
                <strong><?= h(!empty($garantia['FechaInicio']) ? date('d/m/Y', strtotime((string)$garantia['FechaInicio'])) : '-') ?></strong>
            </article>
            <article>
                <span>Fin</span>
                <strong><?= h(!empty($garantia['FechaFin']) ? date('d/m/Y', strtotime((string)$garantia['FechaFin'])) : '-') ?></strong>
            </article>
            <article>
                <span>Venta asociada</span>
                <strong>#<?= h((string)($garantia['IdVenta'] ?? '-')) ?></strong>
            </article>
            <article>
                <span>Cliente</span>
                <strong><?= h($clienteGarantia !== '' ? $clienteGarantia : 'Sin cliente') ?></strong>
            </article>
        </div>
        <?php if (!empty($garantia['Observaciones'])): ?>
            <div class="vehicle-garantia-note"><?= nl2br(h((string)$garantia['Observaciones'])) ?></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="vehiculo-form" data-has-existing-image="<?= $hayImagenPrincipal ? '1' : '0' ?>">
        <?= csrfInput() ?>
        <?php if (!empty($servicesVehiculo)): ?>
        <section class="section-box vehicle-services-box">
            <div class="vehicle-garantia-box__header">
                <div>
                    <span class="section-kicker">Postventa</span>
                    <h2>Services programados</h2>
                </div>
            </div>

            <div class="vehicle-services-grid">
                <?php foreach ($servicesVehiculo as $service): ?>
                    <?php
                        $estadoService = (string)($service['Estado'] ?? 'Pendiente');
                        $fechaProgramada = !empty($service['FechaProgramada'])
                            ? date('d/m/Y', strtotime((string)$service['FechaProgramada']))
                            : '-';

                        $fechaRealizada = !empty($service['FechaRealizada'])
                            ? date('d/m/Y', strtotime((string)$service['FechaRealizada']))
                            : 'Pendiente';

                        $claseService = match ($estadoService) {
                            'Realizado' => 'service-card--ok',
                            'Vencido' => 'service-card--warn',
                            'Cancelado' => 'service-card--danger',
                            default => '',
                        };
                    ?>

                    <article class="service-card <?= h($claseService) ?>">
                        <div class="service-card__top">
                            <strong>Service <?= h((string)($service['NumeroService'] ?? '-')) ?></strong>
                            <span><?= h($estadoService) ?></span>
                        </div>

                        <div class="service-card__meta">
                            <p>
                                <span>Programado</span>
                                <strong><?= h($fechaProgramada) ?></strong>
                            </p>

                            <p>
                                <span>Realizado</span>
                                <strong><?= h($fechaRealizada) ?></strong>
                            </p>
                        </div>

                        <?php if (!empty($service['Observaciones'])): ?>
                            <div class="service-card__note">
                                <?= nl2br(h((string)$service['Observaciones'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($estadoService === 'Pendiente'): ?>
                            <div class="service-card__actions">
                                <input
                                    type="hidden"
                                    name="id_vehiculo"
                                    value="<?= h($idVehiculo) ?>"
                                >

                                <textarea
                                    name="observaciones_service[<?= h((string)$service['IdService']) ?>]"
                                    rows="2"
                                    placeholder="Observaciones del service..."
                                ></textarea>

                                <button
                                    type="submit"
                                    name="id_service"
                                    value="<?= h((string)$service['IdService']) ?>"
                                    formaction="<?= h(panelBaseUrl('vehiculos/service_realizar.php')) ?>"
                                    class="btn btn-primary"
                                >
                                    Marcar realizado
                                </button>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="split-grid split-grid--form">
            <section class="section-box form-section">
                <div class="section-head">
                    <div>
                        <h2>Identificación</h2>
                        <p class="subtle">Datos internos y estado operativo.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>ID vehículo</label>
                        <input type="text" value="<?= h($vehiculo['IdVehiculo'], '') ?>" disabled>
                        <small class="field-help">La clave interna se mantiene fija.</small>
                    </div>

                    <div class="form-group"><label>N&uacute;mero de motor</label><input type="text" name="numero_motor" value="<?= h($form['numero_motor'], '') ?>" required></div>
                    <div class="form-group"><label>Modelo</label><input type="text" name="modelo" value="<?= h($form['modelo'], '') ?>" required></div>
                    <div class="form-group"><label>Color</label><input type="text" name="color" value="<?= h($form['color'], '') ?>"></div>

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
                        <select name="numero_importacion" id="numero_importacion_edit">
                            <option value="">-- Sin importaci&oacute;n --</option>
                            <?php foreach ($importaciones as $imp): ?>
                                <option value="<?= (int)$imp['Numero'] ?>"
                                    data-tc="<?= (float)$imp['TipoCambioUSD'] ?>"
                                    <?= ((string)($form['numero_importacion'] ?? '') === (string)$imp['Numero']) ? 'selected' : '' ?>>
                                    Importaci&oacute;n <?= (int)$imp['Numero'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-help" id="imp-tc-info-edit" style="<?= !empty($form['numero_importacion']) ? '' : 'display:none;' ?>">
                            TC: <strong id="imp-tc-valor-edit"><?= $vehiculo['TipoCambioImportacion'] ?? '' ?></strong> UYU
                        </small>
                    </div>
                </div>
            </section>

            <section class="section-box form-section">
                <div class="section-head">
                    <div>
                        <h2>Costos y precios</h2>
                        <p class="subtle">Queda alineado con dashboard, inventario y ventas.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group"><label>Costo</label><input type="number" step="0.01" min="0" name="costo" value="<?= h($form['costo'], '') ?>"></div>
                    <div class="form-group"><label>Gasto total</label><input type="number" step="0.01" min="0" name="gasto_total" value="<?= h($form['gasto_total'], '') ?>"></div>
                    <div class="form-group"><label>Precio venta</label><input type="number" step="0.01" min="0" name="precio_venta" value="<?= h($form['precio_venta'], '') ?>"></div>
                    <div class="form-group"><label>Precio distribuidor</label><input type="number" step="0.01" min="0" name="precio_distribuidor" value="<?= h($form['precio_distribuidor'], '') ?>"></div>
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
                <div class="form-group full"><label>Descripción interna</label><textarea name="descripcion"><?= h($form['descripcion'], '') ?></textarea></div>
            </div>
        </section>

        <?php if ($puedeGestionarCatalogoWeb): ?>
        <details class="section-box form-section vehicle-web-details">
            <summary class="vehicle-web-summary">
                <span>Cat&aacute;logo web <small style="font-weight:400;color:var(--ink-3);margin-left:8px;">opcional &mdash; para publicar en la web p&uacute;blica</small></span>
                <span class="vehicle-web-summary__chevron">&#9660;</span>
            </summary>
            <div class="vehicle-web-details__body">
                <div class="form-grid">
                <div class="form-group full"><label>Descripción web</label><textarea name="descripcion_web"><?= h($form['descripcion_web'], '') ?></textarea></div>

                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?= h($form['slug'], '') ?>" placeholder="<?= h($slugSugerido, '') ?>">
                    <small class="field-help">Si lo dejás vacío, se regenera automáticamente con modelo + ID.</small>
                </div>

                <div class="form-group"><label>Orden web</label><input type="number" name="orden_web" value="<?= h($form['orden_web'], '0') ?>" min="0"></div>
                <div class="form-group"><label>Texto del botón web</label><input type="text" name="texto_boton_web" value="<?= h($form['texto_boton_web'], '') ?>" maxlength="80"></div>

                <div class="form-group">
                    <label>Agregar nuevas imágenes</label>
                    <input id="imagenes-input" type="file" name="imagenes[]" multiple accept="image/jpeg,image/png,image/webp">
                    <small class="field-help">Las nuevas quedan al final. Podés elegir la principal abajo.</small>
                    <div id="imagenes-preview" class="imagenes-preview"></div>
                </div>

                <div class="form-group full">
                    <div class="checks-grid">
                        <label class="check-card">
                            <input type="checkbox" name="mostrar_en_web" value="1" <?= (int)$form['mostrar_en_web'] === 1 ? 'checked' : '' ?>>
                            <span>
                                <strong>Mostrar en web</strong>
                                <small>Solo queda activo si la unidad está disponible y con stock.</small>
                            </span>
                        </label>

                        <label class="check-card">
                            <input type="checkbox" name="destacado_web" value="1" <?= (int)$form['destacado_web'] === 1 ? 'checked' : '' ?>>
                            <span>
                                <strong>Destacado</strong>
                                <small>Depende de que también pueda publicarse.</small>
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
                    <p class="subtle">Vista previa en vivo de cómo queda esta ficha después de guardar.</p>
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
            <button type="submit" class="btn">Guardar cambios</button>
            <a class="btn-secondary" href="<?= panelBaseUrl('vehiculos/index.php') ?>">Volver al listado</a>
        </div>
    </form>

    <?php if ($puedeGestionarCatalogoWeb): ?>
    <section class="section-box">
        <div class="section-head">
            <div>
                <h2>Imágenes actuales</h2>
                <p class="subtle">Elegí cuál es la principal para el catálogo y la ficha pública.</p>
            </div>
        </div>

        <?php if ($imagenes): ?>
            <?php $totalImagenes = count($imagenes); ?>
            <div class="media-grid">
                <?php foreach ($imagenes as $indice => $img): ?>
                    <article class="media-card">
                        <img src="<?= h(panelBaseUrl((string)$img['RutaImagen']), '') ?>" alt="Imagen vehículo" class="media-card__img">
                        <div class="media-card__body">
                            <div class="media-card__meta">
                                <span class="badge <?= (int)$img['EsPrincipal'] === 1 ? 'badge-disponible' : 'badge-default' ?>">
                                    <?= (int)$img['EsPrincipal'] === 1 ? 'Principal' : 'Secundaria' ?>
                                </span>
                                <span class="results-count">Orden <?= (int)$img['OrdenImagen'] ?></span>
                            </div>

                            <div class="media-card__actions">
                                <?php if ((int)$img['EsPrincipal'] !== 1): ?>
                                    <form method="POST">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="accion" value="set_principal">
                                        <input type="hidden" name="id_imagen" value="<?= (int)$img['IdImagen'] ?>">
                                        <button type="submit" class="btn-secondary btn-small">Usar como principal</button>
                                    </form>
                                <?php else: ?>
                                    <span class="issue-tag issue-tag--success">Es la portada actual</span>
                                <?php endif; ?>

                                <div class="media-card__order-actions">
                                    <?php if ($indice > 0): ?>
                                        <form method="POST">
                                        <?= csrfInput() ?>
                                            <input type="hidden" name="accion" value="move_image">
                                            <input type="hidden" name="direccion" value="up">
                                            <input type="hidden" name="id_imagen" value="<?= (int)$img['IdImagen'] ?>">
                                            <button type="submit" class="btn-small btn-warning">Mover arriba</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($indice < $totalImagenes - 1): ?>
                                        <form method="POST">
                                        <?= csrfInput() ?>
                                            <input type="hidden" name="accion" value="move_image">
                                            <input type="hidden" name="direccion" value="down">
                                            <input type="hidden" name="id_imagen" value="<?= (int)$img['IdImagen'] ?>">
                                            <button type="submit" class="btn-small btn-secondary">Mover abajo</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" action="<?= panelBaseUrl('vehiculos/editar.php?id=' . urlencode($idVehiculo)) ?>" class="js-confirm-form" data-confirm-message="¿Seguro que querés eliminar esta imagen?">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="accion" value="delete_image">
                                    <input type="hidden" name="id_imagen" value="<?= (int)$img['IdImagen'] ?>">
                                    <button type="submit" class="btn-danger btn-small">Eliminar imagen</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Este vehículo todavía no tiene imágenes cargadas.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<script nonce="<?= cspNonce() ?>">
(function () {
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
    const hasExistingImage = form.dataset.hasExistingImage === '1';

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
        const hasImage = hasExistingImage || ((field('imagenes[]')?.files?.length || 0) > 0);
        const slugPreview = slugManual || slugify((modelo || 'moto') + '-<?= addslashes($idVehiculo) ?>');
        const stock = ['Vendido', 'Sin stock'].includes(estado) ? 0 : 1;
        const canShow = estado === 'Disponible' && stock > 0 && mostrar;
        const canFeature = canShow && destacado;
        const missing = [];

        if (precio <= 0) missing.push('precio de venta');
        if (!slugPreview) missing.push('slug');
        if (!descripcion) missing.push('descripción web');
        if (!hasImage) missing.push('imagen principal');

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
            previewBox.innerHTML = '<div class="thumb thumb--placeholder thumb--placeholder-full">No agregaste imágenes nuevas</div>';
            return;
        }

        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'upload-preview-item';

            const img = document.createElement('img');
            img.className = 'upload-preview-img';

            const label = document.createElement('small');
            label.textContent = index === 0 && !hasExistingImage ? 'Principal nueva' : 'Nueva imagen';

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

<?php if ($qrVehiculoPayload !== ''): ?>
<script src="<?= h(panelBaseUrl('assets/vendor/qrcodejs/qrcode.min.js')) ?>"></script>
<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    const qrNode = document.getElementById('qr-editar-vehiculo');
    if (!qrNode || typeof QRCode === 'undefined') return;

    new QRCode(qrNode, {
        text: qrNode.dataset.qr || '',
        width: 148,
        height: 148,
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
<?php endif; ?>

<div id="confirmModal" class="modal-overlay is-hidden" aria-hidden="true">
    <div class="modal-box">
        <h3 class="modal-title">Confirmar acción</h3>
        <p id="confirmModalText" class="modal-copy">
            ¿Seguro que querés continuar?
        </p>

        <div class="form-actions">
            <button type="button" class="btn-secondary" id="confirmCancelBtn">
                Cancelar
            </button>

            <button type="button" class="btn-danger" id="confirmAcceptBtn">
                Sí, confirmar
            </button>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("confirmModal");
    const modalText = document.getElementById("confirmModalText");
    const cancelBtn = document.getElementById("confirmCancelBtn");
    const acceptBtn = document.getElementById("confirmAcceptBtn");

    let formPendiente = null;

    document.querySelectorAll(".js-confirm-form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            event.preventDefault();

            formPendiente = form;

            if (modalText) {
                modalText.textContent = form.dataset.confirmMessage || "¿Seguro que querés continuar?";
            }

            modal.classList.remove("is-hidden");
            modal.setAttribute("aria-hidden", "false");
        });
    });

    cancelBtn?.addEventListener("click", () => {
        formPendiente = null;
        modal.classList.add("is-hidden");
        modal.setAttribute("aria-hidden", "true");
    });

    acceptBtn?.addEventListener("click", () => {
        if (formPendiente) {
            formPendiente.submit();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            formPendiente = null;
            modal.classList.add("is-hidden");
            modal.setAttribute("aria-hidden", "true");
        }
    });

    modal?.addEventListener("click", (event) => {
        if (event.target === modal) {
            formPendiente = null;
            modal.classList.add("is-hidden");
            modal.setAttribute("aria-hidden", "true");
        }
    });
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
