<?php

declare(strict_types=1);

namespace Lteco\Support;

/**
 * Cédula de identidad uruguaya: normalización y validación del dígito verificador.
 *
 * El sistema almacena la cédula como dígitos sin puntos ni guion. La validación
 * sigue el algoritmo oficial: coeficientes 2-9-8-7-6-3-4 sobre los 7 dígitos del
 * cuerpo (rellenado a la izquierda con ceros), el último dígito es el verificador.
 */
final class Cedula
{
    /**
     * Deja solo dígitos (quita puntos, guion y espacios).
     */
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    /**
     * Valida una cédula uruguaya (7 u 8 dígitos, dígito verificador correcto).
     */
    public static function isValidUruguayan(?string $value): bool
    {
        $ci = self::normalize($value);
        $len = strlen($ci);
        if ($len < 7 || $len > 8) {
            return false;
        }

        $verificador = (int) substr($ci, -1);
        $cuerpo = substr($ci, 0, -1);

        return self::digitoVerificador($cuerpo) === $verificador;
    }

    private static function digitoVerificador(string $cuerpo): int
    {
        $cuerpo = str_pad($cuerpo, 7, '0', STR_PAD_LEFT);
        $coeficientes = [2, 9, 8, 7, 6, 3, 4];
        $suma = 0;
        for ($i = 0; $i < 7; $i++) {
            $suma += ($coeficientes[$i] * (int) $cuerpo[$i]) % 10;
        }

        $resto = $suma % 10;
        return $resto === 0 ? 0 : 10 - $resto;
    }
}
