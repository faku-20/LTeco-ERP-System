<?php

declare(strict_types=1);

final class RepuestoCrudWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/repuestos/';
        self::verificar($base . 'crear.php', 'crear', '->crear(');
        self::verificar($base . 'editar.php', 'editar', '->editar(');
        self::verificar($base . 'eliminar.php', 'eliminar', '->ocultar(');
    }

    private static function verificar(string $ruta, string $caso, string $delegacion): void
    {
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring CRUD repuesto', "{$caso}.php legible", $source !== '');
        Assert::isTrue(
            'Wiring CRUD repuesto',
            "{$caso} usa RepuestoCrudService",
            strpos($source, 'RepuestoCrudService') !== false
        );
        Assert::same(
            'Wiring CRUD repuesto',
            "{$caso} delega persistencia",
            1,
            substr_count($source, $delegacion)
        );
        Assert::same(
            'Wiring CRUD repuesto',
            "{$caso} sin SQL inline",
            0,
            // Lookbehind: ignora <select>/</select> del HTML y métodos JS como items.delete().
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring CRUD repuesto',
            "{$caso} conserva guard admin",
            strpos($source, 'requiereAdmin()') !== false
        );
        Assert::isTrue(
            'Wiring CRUD repuesto',
            "{$caso} conserva CSRF",
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring CRUD repuesto',
            "{$caso} conserva auditoría",
            strpos($source, 'registrarAuditoria(') !== false
        );
    }
}
