<?php

declare(strict_types=1);

use Lteco\Application\Whatsapp\WhatsappQuotedModel;

final class WhatsappQuotedModelTest
{
    public static function run(): void
    {
        Assert::same('Respuesta citada WhatsApp', 'imagen inicial Q8 350W', 'Q8 350W', WhatsappQuotedModel::fromTemplate('auto_respuesta_inicial_media_q8_350w'));
        Assert::same('Respuesta citada WhatsApp', 'texto modelo Q8 500W', 'Q8 500W', WhatsappQuotedModel::fromTemplate('auto_respuesta_modelo_q8_500w'));
        Assert::same('Respuesta citada WhatsApp', 'cierre modelo LY 500W', 'LY 500W', WhatsappQuotedModel::fromTemplate('auto_respuesta_modelo_ly_500w_cierre'));
        Assert::same('Respuesta citada WhatsApp', 'imagen inicial SL 500W', 'SL 500W', WhatsappQuotedModel::fromTemplate('auto_respuesta_inicial_media_sl_500w'));
        Assert::same('Respuesta citada WhatsApp', 'ignora mensaje manual', null, WhatsappQuotedModel::fromTemplate('manual_text'));

        $base = dirname(__DIR__, 2);
        $webhook = (string)file_get_contents($base.'/lteco-panel/whatsapp_webhook.php');
        $aiSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/ai.php');
        $whatsappSupport = (string)file_get_contents($base.'/src/Presentation/Panel/Support/whatsapp.php');

        Assert::isTrue('Respuesta citada WhatsApp', 'webhook captura context.id', str_contains($webhook, "message['context']['id']"));
        Assert::isTrue('Respuesta citada WhatsApp', 'inbox guarda modelo citado', str_contains($aiSupport, 'ReplyToModelo'));
        Assert::isTrue('Respuesta citada WhatsApp', 'clasificación recibe modelo citado', str_contains($aiSupport, 'Modelo del mensaje citado:'));
        Assert::isTrue('Respuesta citada WhatsApp', 'envío guarda ID devuelto por Meta', str_contains($whatsappSupport, "responseData['messages'][0]['id']"));
    }
}
