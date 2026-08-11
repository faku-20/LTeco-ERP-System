<?php

declare(strict_types=1);

namespace Lteco\Application\Whatsapp;

final class MetaWebhookSignature
{
    public static function isValid(string $payload, string $signature, string $appSecret): bool
    {
        if ($signature === '' || $appSecret === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expected, $signature);
    }
}
