<?php

declare(strict_types=1);

final class N8nWhatsappWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $support = (string)file_get_contents($base.'/src/Presentation/Panel/Support/n8n.php');
        $endpoint = (string)file_get_contents($base.'/lteco-panel/api/n8n/meta_whatsapp.php');
        $screen = (string)file_get_contents($base.'/lteco-panel/n8n/index.php');

        Assert::isTrue('n8n WhatsApp', 'endpoint exige token interno', str_contains($endpoint, 'n8nAuthorizeOrFail()'));
        Assert::isTrue('n8n WhatsApp', 'registra estados Meta', str_contains($support, 'registrarEstadoWebhook('));
        Assert::isTrue('n8n WhatsApp', 'espera entrega de imagen para cerrar', str_contains($support, 'aiMaybeSendInitialAutoReplyClosure('));
        Assert::isTrue('n8n WhatsApp', 'clasifica mensajes entrantes', str_contains($support, 'aiClassifyInbox($pdo, $id)'));
        Assert::isTrue('n8n WhatsApp', 'intenta respuesta inicial', str_contains($support, 'aiMaybeSendInitialAutoReply($pdo, $phone, $id)'));
        Assert::isFalse('n8n WhatsApp', 'no responde mensajes posteriores', str_contains($support, 'aiMaybeSendModelAutoReply($pdo, $phone, $id)'));
        Assert::isTrue('n8n WhatsApp', 'analiza intención de visita', str_contains($support, 'agendaMaybeScheduleFromInbox($pdo, $id)'));
        Assert::isTrue('n8n WhatsApp', 'pantalla diferencia API y push', str_contains($screen, 'n8n consume las APIs internas'));
    }
}
