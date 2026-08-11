<?php

declare(strict_types=1);

use Lteco\Support\InputNormalizer;
use Lteco\Support\Phone;

/**
 * Caracteriza la normalización legacy usada por ventas/guardar.php.
 *
 * Estos resultados se congelan antes de reemplazar los helpers globales. Algunos
 * son poco intuitivos, pero cambiarlos sería una modificación funcional separada.
 */
final class VentaSupportTest
{
    public static function run(): void
    {
        self::numeros();
        self::texto();
        self::telefonos();
    }

    private static function numeros(): void
    {
        $caso = 'Support venta - números';

        Assert::same($caso, 'decimal local', '1234.56', InputNormalizer::number(' 1.234,56 '));
        Assert::same($caso, 'decimal con punto', '1200.50', InputNormalizer::number(' 1200.50 '));
        Assert::same($caso, 'separadores mixtos legacy', '1.23456', InputNormalizer::number('1,234.56'));
        Assert::same($caso, 'decimal negativo se limita a cero', 0.0, InputNormalizer::nonNegativeDecimal('-25,50', 7.0));
        Assert::same($caso, 'decimal inválido usa default', 7.0, InputNormalizer::nonNegativeDecimal('abc', 7.0));
        Assert::same($caso, 'decimal vacío usa default', 7.0, InputNormalizer::nonNegativeDecimal('', 7.0));
        Assert::same($caso, 'entero decorado', 18, InputNormalizer::nonNegativeInt('18 cuotas'));
        Assert::same($caso, 'signo negativo legacy se elimina', 12, InputNormalizer::nonNegativeInt('-12'));
    }

    private static function texto(): void
    {
        $caso = 'Support venta - texto';

        Assert::same($caso, 'opcional vacío', null, InputNormalizer::optionalText(" \n "));
        Assert::same($caso, 'opcional conserva contenido', 'Observación', InputNormalizer::optionalText(' Observación '));
        Assert::same(
            $caso,
            'espacios y límite UTF-8',
            'José Pérez L',
            InputNormalizer::humanText("  José\n  Pérez   López  ", 12)
        );
        Assert::same($caso, 'límite desactivado', 'Texto completo', InputNormalizer::humanText(' Texto  completo ', 0));
    }

    private static function telefonos(): void
    {
        $caso = 'Support venta - teléfono';

        Assert::same($caso, 'vacío es null', null, Phone::normalize('   '));
        Assert::same($caso, 'colapsa espacios', '099 123 456', Phone::normalize("  099   123\n456  "));
        Assert::isTrue($caso, 'formato permitido', Phone::isValid('+598 (99) 123-456'));
        Assert::isFalse($caso, 'letras no permitidas', Phone::isValid('099 ABC 123'));
        Assert::isTrue($caso, 'vacío opcional es válido', Phone::isValid(''));
    }
}
