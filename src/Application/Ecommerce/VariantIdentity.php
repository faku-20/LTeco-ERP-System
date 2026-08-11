<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

final class VariantIdentity
{
    public static function id(string $model, ?int $batteryAh, string $color, string $currency, mixed $gross): string
    {
        $parts = [
            self::normalize($model),
            $batteryAh,
            self::normalize($color),
            strtoupper(trim($currency)),
            self::decimal($gross),
        ];

        return hash('sha256', (string) json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtolower($value, 'UTF-8');
    }

    public static function decimal(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
