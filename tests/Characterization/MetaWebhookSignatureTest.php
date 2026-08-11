<?php

declare(strict_types=1);

use Lteco\Application\Whatsapp\MetaWebhookSignature;

final class MetaWebhookSignatureTest
{
    public static function run(): void
    {
        $payload = '{"object":"whatsapp_business_account","entry":[]}';
        $secret = 'test-app-secret';
        $valid = 'sha256='.hash_hmac('sha256', $payload, $secret);

        Assert::isTrue('Firma webhook Meta', 'acepta firma correcta', MetaWebhookSignature::isValid($payload, $valid, $secret));
        Assert::isFalse('Firma webhook Meta', 'rechaza firma incorrecta', MetaWebhookSignature::isValid($payload, 'sha256=incorrecta', $secret));
        Assert::isFalse('Firma webhook Meta', 'rechaza firma ausente', MetaWebhookSignature::isValid($payload, '', $secret));
        Assert::isFalse('Firma webhook Meta', 'rechaza secreto ausente', MetaWebhookSignature::isValid($payload, $valid, ''));

        $source = (string)file_get_contents(dirname(__DIR__, 2).'/lteco-panel/whatsapp_webhook.php');
        Assert::isTrue('Firma webhook Meta', 'webhook valida antes de decodificar', strpos($source, 'MetaWebhookSignature::isValid') < strpos($source, 'json_decode'));
    }
}
