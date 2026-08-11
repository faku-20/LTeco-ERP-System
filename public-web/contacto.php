<?php
require_once dirname(__DIR__) . '/shared/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/contenido-modelos.php';

$pageTitle = 'Contacto | ' . appName();

$empresaPublica = obtenerEmpresaPublica($pdo);

$modelo = trim((string)($_GET['modelo'] ?? ''));
$volver = trim((string)($_GET['volver'] ?? publicBaseUrl('catalogo.php')));

// LTECO:FIX_OPEN_REDIRECT — solo rutas internas permitidas
$rutasPermitidas = ['catalogo.php', 'detalle.php', 'index.php'];
$partesVolver = parse_url($volver) ?: [];
$baseVolver = basename((string)($partesVolver['path'] ?? ''));

if (
    $volver === ''
    || !empty($partesVolver['scheme'])
    || !empty($partesVolver['host'])
    || !in_array($baseVolver, $rutasPermitidas, true)
) {
    $volver = publicBaseUrl('catalogo.php');
} else {
    $parametrosVolver = [];
    if (!empty($partesVolver['query'])) {
        parse_str((string)$partesVolver['query'], $parametrosVolver);
    }
    $volver = publicBaseUrl($baseVolver) . ($parametrosVolver ? '?' . http_build_query($parametrosVolver) : '');
}

$whatsapp = whatsappEmpresaPublica($empresaPublica);
$instagram = $empresaPublica['Instagram'] ?? '';
$facebook = $empresaPublica['Facebook'] ?? '';

$mensaje = $modelo !== ''
    ? 'Hola, quiero consultar por la moto ' . $modelo . '.'
    : 'Hola, quiero consultar por las motos de ' . appName() . '.';

$linkWhatsapp = armarLinkWhatsapp($whatsapp, $mensaje);
$linkInstagram = armarLinkInstagram($instagram);
$linkFacebook = armarLinkFacebook($facebook);
$preguntasFrecuentes = preguntasFrecuentesPublicas();

require_once __DIR__ . '/includes/header.php';
?>

<section class="section contact-section">
    <div class="container contact-layout">

        <div class="contact-copy">
            <span class="eyebrow">Contacto directo</span>

            <h1>Elegí cómo querés consultar</h1>

            <p>
                <?= $modelo !== ''
                    ? 'Te dejamos las opciones para consultar por el modelo ' . htmlspecialchars($modelo) . '.'
                    : 'Te dejamos las opciones para pedir información, disponibilidad, fotos o detalles.' ?>
            </p>
        </div>

        <div class="contact-card">
            <h2>Canales disponibles</h2>

            <p class="text-muted">
                Podés comprar desde la web con tu cuenta verificada o escribirnos para confirmar detalles y recibir asesoramiento.
            </p>

            <div class="contact-options">

                <a 
                    class="contact-option contact-option-whatsapp" 
                    href="<?= htmlspecialchars($linkWhatsapp) ?>" 
                    target="_blank" 
                    rel="noopener"
                >
                    <span class="contact-option-icon">W</span>

                    <span>
                        <strong>WhatsApp</strong>
                        <small>Consulta rápida y directa</small>
                    </span>
                </a>

                <?php if (instagramUsuarioPublico($instagram) !== ''): ?>
                    <a 
                        class="contact-option contact-option-instagram" 
                        href="<?= htmlspecialchars($linkInstagram) ?>" 
                        target="_blank" 
                        rel="noopener"
                    >
                        <span class="contact-option-icon">IG</span>

                        <span>
                            <strong>Instagram</strong>
                            <small>Fotos, novedades y mensajes</small>
                        </span>
                    </a>
                <?php endif; ?>

                <?php if (facebookUsuarioPublico($facebook) !== ''): ?>
                    <a 
                        class="contact-option contact-option-facebook" 
                        href="<?= htmlspecialchars($linkFacebook) ?>" 
                        target="_blank" 
                        rel="noopener"
                    >
                        <span class="contact-option-icon">FB</span>
                        <span>
                            <strong>Facebook</strong>
                            <small>Noticias, publicaciones y mensajes</small>
                        </span>
                    </a>
                <?php endif; ?>

            </div>

            <a class="btn-secondary contact-back" href="<?= htmlspecialchars($volver) ?>">
                Volver
            </a>
        </div>

    </div>
</section>

<section class="section faq-section">
    <div class="container faq-layout">
        <div class="faq-heading">
            <span class="eyebrow">Información útil</span>
            <h2>Antes de elegir tu moto</h2>
            <p>Respuestas rápidas sobre uso, autonomía, carga, batería y respaldo posventa.</p>
        </div>
        <div class="faq-list">
            <?php foreach ($preguntasFrecuentes as $pregunta => $respuesta): ?>
                <details>
                    <summary><?= htmlspecialchars($pregunta) ?></summary>
                    <p><?= htmlspecialchars($respuesta) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
