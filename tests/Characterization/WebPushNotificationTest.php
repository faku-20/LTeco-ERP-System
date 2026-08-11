<?php

declare(strict_types=1);

final class WebPushNotificationTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $support = (string)file_get_contents($base.'/src/Presentation/Panel/Support/push.php');
        $migration = (string)file_get_contents($base.'/database/migrations/2026_08_05_000000_b5_runtime_schema.sql');
        $subscribe = (string)file_get_contents($base.'/lteco-panel/api/push/subscribe.php');
        $dispatch = (string)file_get_contents($base.'/lteco-panel/api/n8n/push_event.php');
        $client = (string)file_get_contents($base.'/lteco-panel/assets/js/web-push.js');
        $worker = (string)file_get_contents($base.'/lteco-panel/assets/js/sw.js');
        $footer = (string)file_get_contents($base.'/src/Presentation/Panel/View/Includes/footer.php');

        Assert::isTrue('Web Push', 'suscripciones vinculadas a usuario', str_contains($migration, 'IdUsuario INT NOT NULL'));
        Assert::isTrue('Web Push', 'evita entregas duplicadas', str_contains($migration, 'uq_push_event_subscription'));
        Assert::isTrue('Web Push', 'reclama evento de forma atómica', str_contains($support, "Status = 'processing'"));
        Assert::isTrue('Web Push', 'limita envío a roles administrativos', str_contains($support, "u.Rol IN ('Administrador','Superadmin','Superadministrador')"));
        Assert::isTrue('Web Push', 'endpoint de suscripción exige administrador', str_contains($subscribe, 'requiereAdmin()'));
        Assert::isTrue('Web Push', 'endpoint de suscripción valida CSRF', str_contains($subscribe, 'HTTP_X_CSRF_TOKEN'));
        Assert::isTrue('Web Push', 'n8n confirma solo tras entrega', str_contains($dispatch, "if (\$result['ok'])"));
        Assert::isTrue('Web Push', 'cliente solicita permiso por acción del usuario', str_contains($client, 'Notification.requestPermission()'));
        Assert::isTrue('Web Push', 'cliente registra PushManager con VAPID', str_contains($client, 'applicationServerKey'));
        Assert::isTrue('Web Push', 'cliente permite prueba local inmediata', str_contains($client, 'registration.showNotification'));
        Assert::isTrue('Web Push', 'service worker recibe push en segundo plano', str_contains($worker, "addEventListener('push'"));
        Assert::isTrue('Web Push', 'service worker abre agenda al tocar', str_contains($worker, "addEventListener('notificationclick'"));
        Assert::isTrue('Web Push', 'control se limita a admin', str_contains($footer, "function_exists('esAdmin') && esAdmin()"));
    }
}
