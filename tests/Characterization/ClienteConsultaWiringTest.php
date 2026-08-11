<?php

declare(strict_types=1);

final class ClienteConsultaWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/clientes/';
        self::verificar($base . 'index.php', 'index', '->listar(', true);
        self::verificar($base . 'detalle.php', 'detalle', '->ventas(', false);
    }

    private static function verificar(string $ruta, string $caso, string $delegacion, bool $esperaPageshow): void
    {
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Wiring consulta cliente', "{$caso}.php legible", $source !== '');
        Assert::isTrue(
            'Wiring consulta cliente',
            "{$caso} usa ClienteConsultaService",
            strpos($source, 'ClienteConsultaService') !== false
        );
        Assert::same(
            'Wiring consulta cliente',
            "{$caso} delega la lectura",
            1,
            substr_count($source, $delegacion)
        );
        Assert::same(
            'Wiring consulta cliente',
            "{$caso} sin SQL inline",
            0,
            // Lookbehind: ignora <select>/</select> del HTML y métodos JS como items.delete().
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $source)
        );
        Assert::isTrue(
            'Wiring consulta cliente',
            "{$caso} conserva guard de módulo",
            strpos($source, 'requiereModulo("clientes")') !== false
        );
        if ($esperaPageshow) {
            Assert::isTrue(
                'Wiring consulta cliente',
                "{$caso} resetea el botón al volver atrás (pageshow/bfcache)",
                strpos($source, "addEventListener('pageshow'") !== false
                    && strpos($source, 'event.persisted') !== false
            );
        }
    }
}
