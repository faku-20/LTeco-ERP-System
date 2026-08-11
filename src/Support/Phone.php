<?php

declare(strict_types=1);

namespace Lteco\Support;

/**
 * Normalización y validación pura de teléfonos del panel.
 */
final class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/\s+/u', ' ', $phone) ?? $phone;

        return mb_substr($phone, 0, 20, 'UTF-8');
    }

    public static function isValid(?string $phone): bool
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 6
            && strlen($digits) <= 15
            && preg_match('/^[0-9+()\-\s.]+$/', $phone) === 1;
    }
}
