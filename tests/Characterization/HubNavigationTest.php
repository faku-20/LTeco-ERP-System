<?php

declare(strict_types=1);

final class HubNavigationTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);
        $hubFiles = [
            'comercial' => 'lteco-panel/comercial/index.php',
            'stock' => 'lteco-panel/stock/index.php',
            'ventas' => 'lteco-panel/operacion/index.php',
            'administracion' => 'lteco-panel/administracion/index.php',
            'automatizaciones' => 'lteco-panel/automatizaciones/index.php',
        ];

        $owners = [];
        foreach ($hubFiles as $hub => $relative) {
            $source = (string)file_get_contents($base.'/'.$relative);
            Assert::isTrue('Navegación hubs', $hub.' usa cards neutrales', !str_contains($source, "'tone' => 'brand'"));
            preg_match('/\$cards\s*=\s*\[(.*?)\];/s', $source, $match);
            preg_match_all("/panelBaseUrl\('([^']+)'\)/", $match[1] ?? '', $urls);
            foreach ($urls[1] as $url) {
                $owners[$url][] = $hub;
            }
        }

        $duplicates = array_filter($owners, static fn(array $hubs): bool => count($hubs) > 1);
        Assert::same('Navegación hubs', 'cada módulo tiene una sola sección dueña', [], $duplicates);
        Assert::same('Navegación hubs', 'WhatsApp pertenece a Comercial', ['comercial'], $owners['whatsapp/index.php'] ?? []);
        Assert::same('Navegación hubs', 'Asistente IA pertenece a Automatizaciones', ['automatizaciones'], $owners['ia/index.php'] ?? []);
        Assert::same('Navegación hubs', 'Clientes pertenece a Ventas', ['ventas'], $owners['clientes/index.php'] ?? []);
        Assert::same('Navegación hubs', 'Alertas pertenece a Comercial', ['comercial'], $owners['notificaciones/index.php'] ?? []);

        $home = (string)file_get_contents($base.'/lteco-panel/inicio.php');
        $css = (string)file_get_contents($base.'/lteco-panel/assets/css/lteco.css');
        preg_match('/elseif \(\$esAdmin\) \{(.*?)\} else \{/s', $home, $adminBlock);
        Assert::isTrue('Navegación hubs', 'inicio administrador usa cards neutrales', !str_contains($adminBlock[1] ?? '', "'color' => 'brand'"));
        preg_match_all("/panelBaseUrl\('([^']+)'\)/", $adminBlock[1] ?? '', $adminUrls);
        Assert::same('Navegación hubs', 'inicio administrador solo muestra secciones', [
            'comercial/index.php',
            'stock/index.php',
            'operacion/index.php',
            'administracion/index.php',
            'automatizaciones/index.php',
        ], $adminUrls[1]);
        Assert::isTrue('Navegación hubs', 'shell mobile vuelve a la primera columna', (bool)preg_match('/@media \(max-width: 720px\).*?\.shell-v4 > :not\([^}]+grid-column: 1 !important;/s', $css));
        Assert::isTrue('Navegación hubs', 'shell mobile evita columna flexible desbordada', str_contains($css, 'grid-template-columns: minmax(0, 1fr);'));
    }
}
