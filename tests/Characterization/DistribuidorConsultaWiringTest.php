<?php

declare(strict_types=1);

/**
 * C3: congela que las cinco páginas de lectura de distribuidores deleguen en
 * DistribuidorConsultaService y no conserven consultas SQL inline.
 */
final class DistribuidorConsultaWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/';

        self::verificar($base . 'index.php', 'index', [
            '->tablasDistribuidorListas(',
            '->panelDistribuidor(',
            '->listarDistribuidores(',
        ]);
        self::verificar($base . 'pedidos.php', 'pedidos', ['->pedidos(']);
        self::verificar($base . 'ventas.php', 'ventas', ['->ventas(']);
        self::verificar($base . 'busqueda.php', 'busqueda', ['->buscar(']);
        self::verificar($base . 'reportes_admin.php', 'reportes admin', ['->reportes(']);
    }

    /**
     * @param list<string> $delegaciones
     */
    private static function verificar(string $ruta, string $caso, array $delegaciones): void
    {
        $source = (string) @file_get_contents($ruta);
        Assert::isTrue('Wiring consultas distribuidor', $caso . ' legible', $source !== '');
        Assert::isTrue(
            'Wiring consultas distribuidor',
            $caso . ' usa DistribuidorConsultaService',
            strpos($source, 'DistribuidorConsultaService') !== false
        );

        foreach ($delegaciones as $delegacion) {
            Assert::same(
                'Wiring consultas distribuidor',
                $caso . ' delega en ' . $delegacion,
                1,
                substr_count($source, $delegacion)
            );
        }

        Assert::same(
            'Wiring consultas distribuidor',
            $caso . ' sin $pdo->prepare inline',
            0,
            substr_count($source, '$pdo->prepare')
        );
        Assert::same(
            'Wiring consultas distribuidor',
            $caso . ' sin $pdo->query inline',
            0,
            substr_count($source, '$pdo->query')
        );
        Assert::same(
            'Wiring consultas distribuidor',
            $caso . ' sin SELECT inline',
            0,
            preg_match_all('/\bSELECT\b/i', $source)
        );
    }
}
