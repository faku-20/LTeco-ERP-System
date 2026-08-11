<?php

declare(strict_types=1);

final class ClienteCrudWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/clientes/';
        self::verificar($base . 'crear.php', 'crear', '->crear(', 'requiereModulo("clientes")');
        self::verificar($base . 'editar.php', 'editar', '->editar(', 'requiereAdmin()');
    }

    private static function verificar(string $ruta, string $caso, string $delegacion, string $guard): void
    {
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring CRUD cliente', "{$caso}.php legible", $source !== '');
        Assert::isTrue(
            'Wiring CRUD cliente',
            "{$caso} usa ClienteCrudService",
            strpos($source, 'ClienteCrudService') !== false
        );
        Assert::same(
            'Wiring CRUD cliente',
            "{$caso} delega persistencia",
            1,
            substr_count($source, $delegacion)
        );
        Assert::same(
            'Wiring CRUD cliente',
            "{$caso} sin SQL inline",
            0,
            // Lookbehind: ignora <select>/</select> del HTML y métodos JS como items.delete().
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring CRUD cliente',
            "{$caso} conserva guard",
            strpos($source, $guard) !== false
        );
        Assert::isTrue(
            'Wiring CRUD cliente',
            "{$caso} conserva CSRF",
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring CRUD cliente',
            "{$caso} conserva auditoría",
            strpos($source, 'registrarAuditoria(') !== false
        );
    }
}
