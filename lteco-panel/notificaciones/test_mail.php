<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requiereSuperadmin();
require_once __DIR__ . '/mailer.php';

$ok = lteco_smtp_send(
    '✅ Prueba de notificaciones — ERP',
    '<h2>Notificaciones funcionando</h2><p>Este es un mensaje de prueba del sistema de notificaciones de <strong>ERP</strong>.</p><p>Si lo recibís, todo está configurado correctamente.</p>'
);

echo $ok ? '✅ Email enviado correctamente. Revisá el inbox de ltecobike@gmail.com' : '❌ Error al enviar. Revisá las credenciales en el .env.';
