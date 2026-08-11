<?php

declare(strict_types=1);

final class DistribuidorCrudWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/';
        self::verificar($base . 'crear.php', 'crear', '->crear(');
        self::verificar($base . 'editar.php', 'editar', '->editar(');
    }

    private static function verificar(string $ruta, string $caso, string $delegacion): void
    {
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring CRUD distribuidor', "{$caso}.php legible", $source !== '');
        Assert::isTrue(
            'Wiring CRUD distribuidor',
            "{$caso} usa DistribuidorCrudService",
            strpos($source, 'DistribuidorCrudService') !== false
        );
        Assert::same(
            'Wiring CRUD distribuidor',
            "{$caso} delega persistencia",
            1,
            substr_count($source, $delegacion)
        );
        Assert::same(
            'Wiring CRUD distribuidor',
            "{$caso} sin SQL inline",
            0,
            preg_match_all('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring CRUD distribuidor',
            "{$caso} conserva guard admin",
            strpos($source, 'requiereAdmin()') !== false
        );
        Assert::isTrue(
            'Wiring CRUD distribuidor',
            "{$caso} conserva CSRF",
            strpos($source, 'verifyCsrfOrFail()') !== false
        );
        Assert::isTrue(
            'Wiring CRUD distribuidor',
            "{$caso} conserva auditoría",
            strpos($source, 'registrarAuditoria(') !== false
        );
    }
}
