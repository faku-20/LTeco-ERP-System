<?php
$instagramUrl = '';
$facebookUrl = '';
$whatsappFooter = whatsappEmpresaPublica($empresaPublica);

if (!empty($empresaPublica['Instagram'])) {
    $instagramUrl = armarLinkInstagram($empresaPublica['Instagram']);
    $instagramUrl = $instagramUrl === '#' ? '' : $instagramUrl;
}

if (!empty($empresaPublica['Facebook'])) {
    $facebookUrl = armarLinkFacebook($empresaPublica['Facebook']);
}
?>

<footer class="site-footer">
    <div class="container footer-wrap">
        <div>
            <h3><?= htmlspecialchars($empresaPublica['Nombre'] ?? appName()) ?></h3>
            <p><?= htmlspecialchars($empresaPublica['Descripcion'] ?? appTagline()) ?></p>
        </div>

        <div>
            <h4>Contacto</h4>

            <div class="footer-links">
                <?php if (!empty($empresaPublica['Telefono'])): ?>
                    <a href="tel:<?= preg_replace('/\D+/', '', (string)$empresaPublica['Telefono']) ?>">
                        Tel: <?= htmlspecialchars($empresaPublica['Telefono']) ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($empresaPublica['Correo'])): ?>
                    <a href="mailto:<?= htmlspecialchars($empresaPublica['Correo']) ?>">
                        <?= htmlspecialchars($empresaPublica['Correo']) ?>
                    </a>
                <?php endif; ?>

                <?php if ($whatsappFooter !== ''): ?>
                    <a href="<?= htmlspecialchars(armarLinkWhatsapp($whatsappFooter, 'Hola, quiero consultar por ' . businessCategory() . ' de ' . appName() . '.')) ?>" target="_blank" rel="noopener">
                        WhatsApp: <?= htmlspecialchars($empresaPublica['WhatsApp'] ?? $whatsappFooter) ?>
                    </a>
                <?php endif; ?>

                <?php if ($facebookUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" rel="noopener">
                        Facebook
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h4>Redes</h4>

            <div class="footer-links">
                <?php if ($instagramUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" rel="noopener">
                        Instagram
                    </a>
                <?php endif; ?>

                <?php if ($facebookUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" rel="noopener">
                        Facebook
                    </a>
                <?php endif; ?>
            </div>
            <div class="footer-links footer-legal">
                <a href="<?= publicBaseUrl('terminos.php') ?>">Términos</a>
                <a href="<?= publicBaseUrl('privacidad.php') ?>">Privacidad</a>
                <a href="<?= publicBaseUrl('cambios-devoluciones.php') ?>">Cambios y garantía</a>
                <a href="<?= publicBaseUrl('cuenta.php') ?>">Mi cuenta</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
