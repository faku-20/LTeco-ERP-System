<?php
require_once dirname(__DIR__) . '/shared/db.php';
require_once __DIR__ . '/includes/ecommerce.php';
require_once __DIR__ . '/includes/contenido-modelos.php';
ecommerceSessionStart();
$pageTitle = 'Detalle | ' . appName();
ecommerceMetrica($pdo,'producto_visto');

$empresaPublica = obtenerEmpresaPublica($pdo);
$configPublica = obtenerConfiguracionPublica($pdo);
$tieneSlug = dbTieneColumnaPublic($pdo, 'producto', 'Slug');
$tieneDescripcionWeb = dbTieneColumnaPublic($pdo, 'producto', 'DescripcionWeb');
$tieneTextoBoton = dbTieneColumnaPublic($pdo, 'producto', 'TextoBotonWeb');

$where = '';
$param = null;

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($tieneSlug && $slug !== '') {
    $where = 'p.Slug = ?';
    $param = $slug;

} elseif ($id !== '') {
    $where = 'v.IdVehiculo = ?';
    $param = $id;

} else {
    publicRedirect(publicBaseUrl('catalogo.php'));
}

$sql = "SELECT v.IdVehiculo, v.Modelo, v.CapacidadBateriaAh, v.Color, v.NumeroMotor, p.Descripcion, p.PrecioVenta, p.Moneda, p.Estado" . ($tieneSlug ? ', p.Slug' : '') . ($tieneDescripcionWeb ? ', p.DescripcionWeb' : '') . ($tieneTextoBoton ? ', p.TextoBotonWeb' : '') . " FROM vehiculo v INNER JOIN producto p ON p.IdProducto = v.IdProducto WHERE {$where} AND " . sqlMotoVisibleEnWeb('p');
$stmt = $pdo->prepare($sql);
$stmt->execute([$param]);
$moto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$moto) {
    publicRedirect(publicBaseUrl('catalogo.php'));
}

$stmtVariantes=$pdo->prepare("SELECT v.IdVehiculo,v.CapacidadBateriaAh,v.Color,p.PrecioVenta,p.Moneda".($tieneSlug?',p.Slug':'')." FROM vehiculo v JOIN producto p ON p.IdProducto=v.IdProducto WHERE v.Modelo=? AND p.Estado='Disponible' AND p.Stock>0 AND p.MostrarEnWeb=1 ORDER BY v.CapacidadBateriaAh,v.Color,v.IdVehiculo");
$stmtVariantes->execute([$moto['Modelo']]);$variantes=$stmtVariantes->fetchAll(PDO::FETCH_ASSOC);

$stmtImgs = $pdo->prepare('SELECT RutaImagen, EsPrincipal FROM vehiculo_imagen WHERE IdVehiculo = ? ORDER BY EsPrincipal DESC, OrdenImagen ASC');
$stmtImgs->execute([$moto['IdVehiculo']]);
$imagenes = $stmtImgs->fetchAll(PDO::FETCH_ASSOC) ?: [];

$whatsapp = whatsappEmpresaPublica($empresaPublica);
$mensajeWhatsapp = 'Hola, quiero consultar por la moto ' . $moto['Modelo'] . (!empty($moto['CapacidadBateriaAh']) ? ' de ' . $moto['CapacidadBateriaAh'] . 'Ah' : '') . (!empty($moto['Color']) ? ' color ' . $moto['Color'] : '') . '.';
$urlWhatsapp = armarLinkWhatsapp($whatsapp, $mensajeWhatsapp);
$descripcion = $moto['DescripcionWeb'] ?? $moto['Descripcion'] ?? '';
$textoBoton = $moto['TextoBotonWeb'] ?? 'Consultar por WhatsApp';
$contenidoModelo = contenidoModeloPublico((string)$moto['Modelo']);

$imagenesGaleria = [];
foreach ($imagenes as $img) {
    $url = rutaImagenMoto($img['RutaImagen'] ?? null);
    if ($url !== '') {
        $imagenesGaleria[] = $url;
    }
}

if (!$imagenesGaleria) {
    $imagenesGaleria[] = rutaImagenMoto(null);
}

$imagenPrincipal = $imagenesGaleria[0];
$galeriaJson = htmlspecialchars(json_encode($imagenesGaleria, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/includes/header.php';
?>

<script type="application/ld+json" nonce="<?= cspNonce() ?>"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $moto['Modelo'] . (!empty($moto['CapacidadBateriaAh']) ? ' ' . $moto['CapacidadBateriaAh'] . 'Ah' : ''),
    'description' => $descripcion,
    'image' => $imagenesGaleria,
    'sku' => $moto['IdVehiculo'],
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => $moto['Moneda'],
        'price' => number_format((float)$moto['PrecioVenta'], 2, '.', ''),
        'availability' => 'https://schema.org/InStock',
        'url' => requestBaseUrl() . ($_SERVER['REQUEST_URI'] ?? ''),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

<section class="section">
    <div class="container detail-layout">
        <div class="detail-gallery" data-gallery='<?= $galeriaJson ?>'>
            <button type="button" class="detail-main-image detail-main-image-button" id="galleryMainButton" aria-label="Abrir imagen ampliada">
                <img id="galleryMainImage" src="<?= htmlspecialchars($imagenPrincipal) ?>" alt="<?= htmlspecialchars($moto['Modelo']) ?>">
            </button>

            <?php if (count($imagenesGaleria) > 1): ?>
                <div class="thumb-list" aria-label="Imágenes del modelo">
                    <?php foreach ($imagenesGaleria as $index => $imagenUrl): ?>
                        <button type="button" class="thumb-button <?= $index === 0 ? 'is-active' : '' ?>" data-gallery-index="<?= (int)$index ?>" aria-label="Ver imagen <?= (int)$index + 1 ?>">
                            <img src="<?= htmlspecialchars($imagenUrl) ?>" alt="Imagen <?= (int)$index + 1 ?> de <?= htmlspecialchars($moto['Modelo']) ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <span class="badge"><?= htmlspecialchars($moto['Estado']) ?></span>
            <h1><?= htmlspecialchars($moto['Modelo']) ?></h1>
            <p class="detail-meta"><?= htmlspecialchars($descripcion !== '' ? $descripcion : 'Consultanos para recibir más información sobre este modelo.') ?></p>

            <div class="card-price"><?= htmlspecialchars(formatoPrecioWeb((float)$moto['PrecioVenta'], $moto['Moneda'])) ?></div>
            <p class="detail-meta">Referencia: 18 cuotas de <?= htmlspecialchars(formatoPrecioWeb(round((float)$moto['PrecioVenta'] / 18, 2), $moto['Moneda'])) ?>. La financiación final depende de la tarjeta.</p>

            <div class="info-list">
                <?php if (!empty($moto['CapacidadBateriaAh'])): ?><div class="info-row"><strong>Batería</strong><span><?= (int)$moto['CapacidadBateriaAh'] ?>Ah</span></div><?php endif; ?>
                <div class="info-row"><strong>Color</strong><span><?= htmlspecialchars($moto['Color'] ?: 'A confirmar') ?></span></div>
                <div class="info-row"><strong>Estado</strong><span><?= htmlspecialchars($moto['Estado']) ?></span></div>
                <div class="info-row"><strong>Moneda</strong><span><?= htmlspecialchars($moto['Moneda']) ?></span></div>
            </div>
            <?php if (!empty($contenidoModelo['especificaciones'])): ?>
                <div class="product-specifications">
                    <h2>Características</h2>
                    <dl>
                        <?php foreach ($contenidoModelo['especificaciones'] as $etiqueta => $valor): ?>
                            <div><dt><?= htmlspecialchars($etiqueta) ?></dt><dd><?= htmlspecialchars($valor) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endif; ?>
            <?php if(count($variantes)>1):?><div class="variant-picker"><strong>Opciones disponibles</strong><div class="variant-options"><?php foreach($variantes as$v):?><?php $vUrl=publicBaseUrl('detalle.php').'?'.($tieneSlug&&!empty($v['Slug'])?'slug='.rawurlencode($v['Slug']):'id='.rawurlencode($v['IdVehiculo']));?><a class="variant-option <?=$v['IdVehiculo']===$moto['IdVehiculo']?'is-active':''?>" href="<?=$vUrl?>"><span><?=!empty($v['CapacidadBateriaAh'])?(int)$v['CapacidadBateriaAh'].'Ah · ':''?><?=htmlspecialchars($v['Color']?:'Color a confirmar')?></span><strong><?=htmlspecialchars(formatoPrecioWeb((float)$v['PrecioVenta'],$v['Moneda']))?></strong></a><?php endforeach?></div></div><?php endif?>

            <div class="hero-actions">
                <a class="btn" href="<?= htmlspecialchars(storefrontPublicUrl('modelos')) ?>">Comprar en la tienda actual</a>
                <a class="btn-secondary" href="<?= htmlspecialchars($urlWhatsapp) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($textoBoton) ?></a>
                <a class="btn-secondary" href="<?= publicBaseUrl('catalogo.php') ?>">Volver al catálogo</a>
            </div>
        </div>
    </div>
</section>

<div id="galleryLightbox" class="gallery-lightbox" hidden aria-hidden="true">
    <button type="button" class="gallery-lightbox__close" id="galleryClose" aria-label="Cerrar imagen">×</button>
    <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--prev" id="galleryPrev" aria-label="Imagen anterior">‹</button>
    <img id="galleryLightboxImage" src="<?= htmlspecialchars($imagenPrincipal) ?>" alt="<?= htmlspecialchars($moto['Modelo']) ?> ampliada">
    <button type="button" class="gallery-lightbox__nav gallery-lightbox__nav--next" id="galleryNext" aria-label="Imagen siguiente">›</button>
</div>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', () => {
    const gallery = document.querySelector('[data-gallery]');
    if (!gallery) return;

    let images = [];
    try {
        images = JSON.parse(gallery.dataset.gallery || '[]');
    } catch (error) {
        images = [];
    }

    if (!images.length) return;

    const mainImage = document.getElementById('galleryMainImage');
    const mainButton = document.getElementById('galleryMainButton');
    const thumbButtons = Array.from(document.querySelectorAll('[data-gallery-index]'));
    const lightbox = document.getElementById('galleryLightbox');
    const lightboxImage = document.getElementById('galleryLightboxImage');
    const closeBtn = document.getElementById('galleryClose');
    const prevBtn = document.getElementById('galleryPrev');
    const nextBtn = document.getElementById('galleryNext');
    let index = 0;
    let autoplay = null;

    const setIndex = (nextIndex, resetTimer = true) => {
        index = (nextIndex + images.length) % images.length;

        if (mainImage) {
            mainImage.src = images[index];
        }

        if (lightboxImage) {
            lightboxImage.src = images[index];
        }

        thumbButtons.forEach((button) => {
            button.classList.toggle('is-active', Number(button.dataset.galleryIndex) === index);
        });

        if (resetTimer) {
            startAutoplay();
        }
    };

    const startAutoplay = () => {
        if (autoplay) {
            clearInterval(autoplay);
        }

        if (images.length <= 1 || (lightbox && !lightbox.hidden)) {
            return;
        }

        autoplay = setInterval(() => {
            setIndex(index + 1, false);
        }, 4500);
    };

    const openLightbox = () => {
        if (!lightbox) return;
        if (autoplay) clearInterval(autoplay);
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('gallery-open');
    };

    const closeLightbox = () => {
        if (!lightbox) return;
        lightbox.hidden = true;
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('gallery-open');
        startAutoplay();
    };

    thumbButtons.forEach((button) => {
        button.addEventListener('click', () => setIndex(Number(button.dataset.galleryIndex || 0)));
    });

    mainButton?.addEventListener('click', openLightbox);
    closeBtn?.addEventListener('click', closeLightbox);
    prevBtn?.addEventListener('click', () => setIndex(index - 1, false));
    nextBtn?.addEventListener('click', () => setIndex(index + 1, false));

    lightbox?.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!lightbox || lightbox.hidden) return;

        if (event.key === 'Escape') closeLightbox();
        if (event.key === 'ArrowLeft') setIndex(index - 1, false);
        if (event.key === 'ArrowRight') setIndex(index + 1, false);
    });

    startAutoplay();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
