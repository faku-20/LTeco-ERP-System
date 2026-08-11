<?php

declare(strict_types=1);

namespace Lteco\Support;

/**
 * Normalización pura de entradas usada por los formularios legacy.
 *
 * Preserva deliberadamente el comportamiento previo de includes/helpers.php.
 */
final class InputNormalizer
{
    public static function number(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = str_replace(' ', '', $text);

        if (str_contains($text, ',')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        }

        return $text;
    }

    public static function nonNegativeDecimal(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $text = self::number($value);
        if ($text === '' || !is_numeric($text)) {
            return $default;
        }

        return max(0.0, (float) $text);
    }

    public static function nonNegativeInt(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $text = preg_replace('/[^0-9]/', '', (string) $value) ?? '';
        if ($text === '') {
            return $default;
        }

        return max(0, (int) $text);
    }

    public static function optionalText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function humanText(?string $value, int $max = 255): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($max > 0) {
            $text = mb_substr($text, 0, $max, 'UTF-8');
        }

        return $text;
    }
}
