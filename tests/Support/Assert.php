<?php

declare(strict_types=1);

/**
 * Mini-aserciones sin dependencias externas.
 *
 * No es PHPUnit a propósito: composer no está instalado y la Fase 0 prohíbe
 * dependencias nuevas. Cuando exista composer/PHPUnit, estos casos migran 1:1.
 */
final class Assert
{
    public static int $passed = 0;
    public static int $failed = 0;
    /** @var string[] */
    public static array $failures = [];

    /** Comparación de dinero con tolerancia de medio centavo. */
    public static function money(string $caso, string $campo, float $esperado, float $real): void
    {
        if (abs($esperado - $real) < 0.005) {
            self::$passed++;
            return;
        }
        self::fail(sprintf('%s :: %s esperado=%.2f real=%.2f', $caso, $campo, $esperado, $real));
    }

    public static function isTrue(string $caso, string $campo, bool $real): void
    {
        $real ? self::$passed++ : self::fail(sprintf('%s :: %s esperado=true real=false', $caso, $campo));
    }

    public static function isFalse(string $caso, string $campo, bool $real): void
    {
        !$real ? self::$passed++ : self::fail(sprintf('%s :: %s esperado=false real=true', $caso, $campo));
    }

    public static function same(string $caso, string $campo, mixed $esperado, mixed $real): void
    {
        if ($esperado === $real) {
            self::$passed++;
            return;
        }

        self::fail(sprintf(
            '%s :: %s esperado=%s real=%s',
            $caso,
            $campo,
            var_export($esperado, true),
            var_export($real, true)
        ));
    }

    private static function fail(string $mensaje): void
    {
        self::$failed++;
        self::$failures[] = $mensaje;
    }
}
