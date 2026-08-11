<?php
require_once dirname(__DIR__) . '/shared/db.php';
require_once __DIR__ . '/includes/ecommerce.php';
$pageTitle = 'Catálogo | ' . appName();
ecommerceMetrica($pdo,'catalogo_visto');

$empresaPublica = obtenerEmpresaPublica($pdo);
$configPublica = obtenerConfiguracionPublica($pdo);
$tieneSlug = dbTieneColumnaPublic($pdo, 'producto', 'Slug');
$tieneDestacado = dbTieneColumnaPublic($pdo, 'producto', 'DestacadoWeb');
$tieneOrden = dbTieneColumnaPublic($pdo, 'producto', 'OrdenWeb');
$tieneDescripcionWeb = dbTieneColumnaPublic($pdo, 'producto', 'DescripcionWeb');
$tieneTextoBoton = dbTieneColumnaPublic($pdo, 'producto', 'TextoBotonWeb');

$selectExtras = [];
if ($tieneSlug) $selectExtras[] = 'p.Slug';
if ($tieneDestacado) $selectExtras[] = 'p.DestacadoWeb';
if ($tieneOrden) $selectExtras[] = 'p.OrdenWeb';
if ($tieneDescripcionWeb) $selectExtras[] = 'p.DescripcionWeb';
if ($tieneTextoBoton) $selectExtras[] = 'p.TextoBotonWeb';

function motoPublicableCatalogo(array $moto): bool
{
    $payload = $moto;
    $payload['TieneImagen'] = !empty($moto['ImagenPrincipal']);
    return vehiculoAnalizarPublicacion($payload)['visible_real'];
}

$sql = "
    SELECT
        v.IdVehiculo,
        v.Modelo,
        v.CapacidadBateriaAh,
        v.Color,
        v.NumeroMotor,
        p.Descripcion,
        p.PrecioVenta,
        p.Moneda,
        p.Estado,
        p.Stock,
        p.MostrarEnWeb,
        (
            SELECT vi.RutaImagen
            FROM vehiculo_imagen vi
            WHERE vi.IdVehiculo = v.IdVehiculo
            ORDER BY vi.EsPrincipal DESC, vi.OrdenImagen ASC
            LIMIT 1
        ) AS ImagenPrincipal" . ($selectExtras ? ",
        " . implode(",
        ", $selectExtras) : '') . "
    FROM vehiculo v
    INNER JOIN producto p ON p.IdProducto = v.IdProducto
    WHERE " . sqlMotoVisibleEnWeb('p') . "
    ORDER BY " . ($tieneDestacado ? 'p.DestacadoWeb DESC, ' : '') . ($tieneOrden ? 'p.OrdenWeb ASC, ' : '') . "v.IdVehiculo DESC
";

$motosRaw = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$motos = array_values(array_filter($motosRaw, static function (array $moto): bool {
    return motoPublicableCatalogo($moto);
}));

$whatsapp = whatsappEmpresaPublica($empresaPublica);

require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>Elegí tu modelo ideal</h2>
        <p class="section-intro">Compará potencia, batería, comodidad y características para encontrar la opción que mejor se adapte a tu movilidad diaria.</p>

        <div class="grid-cards grid-cards-catalog">
            <?php if ($motos): ?>
                <?php foreach ($motos as $moto): ?>
                    <?php
                        $descripcion = $moto['DescripcionWeb'] ?? $moto['Descripcion'] ?? '';
                        $urlDetalle = publicBaseUrl('detalle.php') . '?' . ($tieneSlug && !empty($moto['Slug']) ? 'slug=' . urlencode($moto['Slug']) : 'id=' . urlencode($moto['IdVehiculo']));
                        $textoBoton = $moto['TextoBotonWeb'] ?? 'Consultar precio';
                        $mensajeWhatsapp = 'Hola, quiero consultar por la moto ' . $moto['Modelo'] . (!empty($moto['CapacidadBateriaAh']) ? ' de ' . $moto['CapacidadBateriaAh'] . 'Ah' : '') . (!empty($moto['Color']) ? ' color ' . $moto['Color'] : '') . '.';
                        $urlWhatsapp = armarLinkWhatsapp($whatsapp, $mensajeWhatsapp);
                    ?>
                    <article class="card card-vehiculo">
                        <div class="card-media">
                            <img src="<?= htmlspecialchars(rutaImagenMoto($moto['ImagenPrincipal'] ?? null)) ?>" alt="<?= htmlspecialchars($moto['Modelo']) ?>">
                        </div>
                        <div class="card-body">
                            <div class="card-badges">
                                <span class="badge"><?= htmlspecialchars($moto['Estado']) ?></span>
                                <?php if (!empty($moto['DestacadoWeb'])): ?><span class="badge badge-destacado-web">Destacada</span><?php endif; ?>
                            </div>
                            <h3><?= htmlspecialchars($moto['Modelo']) ?></h3>
                            <?php if (!empty($moto['CapacidadBateriaAh'])): ?><p class="card-meta"><strong>Batería:</strong> <?= (int)$moto['CapacidadBateriaAh'] ?>Ah</p><?php endif; ?>
                            <p class="card-meta"><strong>Color:</strong> <?= htmlspecialchars($moto['Color'] ?: 'A confirmar') ?></p>
                            <p><?= htmlspecialchars(textoResumen($descripcion !== '' ? $descripcion : 'Consultanos por más información sobre este modelo.')) ?></p>
                            <div class="card-price"><?= htmlspecialchars(formatoPrecioWeb((float)$moto['PrecioVenta'], $moto['Moneda'])) ?></div>
                            <div class="card-actions">
                                <a class="btn-secondary" href="<?= htmlspecialchars($urlDetalle) ?>">Ver detalle</a>
                                <a class="btn" href="<?= htmlspecialchars($urlWhatsapp) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($textoBoton) ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay motos visibles en este momento</h3>
                    <p>Pronto vamos a publicar nuevos modelos.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
