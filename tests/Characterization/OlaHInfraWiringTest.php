<?php

declare(strict_types=1);

final class OlaHInfraWiringTest
{
    public static function run(): void
    {
        $base = dirname(__DIR__, 2);

        foreach ([
            'lteco-panel/ventas/guardar.php',
            'lteco-panel/vehiculos/crear.php',
            'lteco-panel/vehiculos/editar.php',
            'lteco-panel/vehiculos/reservar.php',
            'lteco-panel/login.php',
            'lteco-panel/includes/auth.php',
            'lteco-panel/includes/auditoria.php',
            'lteco-panel/includes/helpers.php',
            'lteco-panel/includes/whatsapp.php',
            'src/Presentation/Panel/Support/auth.php',
            'src/Presentation/Panel/Support/auditoria.php',
            'src/Presentation/Panel/Support/helpers.php',
            'src/Presentation/Panel/Support/whatsapp.php',
            'lteco-panel/cron/whatsapp_services.php',
            'lteco-panel/distribuidores/_common.php',
            'lteco-panel/distribuidores/nueva_venta.php',
        ] as $relativePath) {
            $source = (string) @file_get_contents($base . '/' . $relativePath);
            Assert::isTrue('Wiring Ola H', $relativePath . ' legible', $source !== '');
            $esperadoSql = $relativePath === 'src/Presentation/Panel/Support/helpers.php' ? 2 : 0;
            Assert::same(
                'Wiring Ola H',
                $relativePath . ' sin SQL inline real',
                $esperadoSql,
                self::countSqlStatements($source)
            );
        }

        $helpersWrapper = (string) @file_get_contents($base . '/lteco-panel/includes/helpers.php');
        Assert::isTrue(
            'Wiring Ola H',
            'helpers público es wrapper de presentación',
            strpos($helpersWrapper, 'src/Presentation/Panel/Support/helpers.php') !== false
        );

        $headerWrapper = (string) @file_get_contents($base . '/lteco-panel/includes/header.php');
        Assert::isTrue(
            'Wiring Ola H',
            'header público es wrapper de presentación',
            strpos($headerWrapper, 'src/Presentation/Panel/View/Includes/header.php') !== false
        );

        $helpers = (string) @file_get_contents($base . '/src/Presentation/Panel/Support/helpers.php');
        Assert::isTrue('Wiring Ola H', 'helpers delega seguridad', strpos($helpers, 'SeguridadService') !== false);
        Assert::isTrue('Wiring Ola H', 'helpers delega schema', strpos($helpers, 'SchemaRepository') !== false);
        Assert::isTrue('Wiring Ola H', 'helpers delega imágenes', strpos($helpers, 'insertarImagen(') !== false);

        $whatsapp = (string) @file_get_contents($base . '/src/Presentation/Panel/Support/whatsapp.php');
        Assert::isTrue('Wiring Ola H', 'whatsapp delega persistencia', strpos($whatsapp, 'WhatsappService') !== false);

        $intencionales = implode("\n", self::filesWithPdoSql($base . '/lteco-panel', $base));

        Assert::same(
            'Wiring Ola H',
            'SQL procedural residual solo en cleanup y prueba push',
            "lteco-panel/api/push/test.php\nlteco-panel/cron/ecommerce.php\nlteco-panel/scripts/cleanup_test_data.php\nlteco-panel/scripts/panel_migrate.php\nlteco-panel/scripts/panel_preflight.php",
            $intencionales
        );
    }

    private static function countSqlStatements(string $source): int
    {
        $phpSource = '';
        foreach (token_get_all($source) as $token) {
            if (!is_array($token) || $token[0] !== T_INLINE_HTML) {
                $phpSource .= is_array($token) ? $token[1] : $token;
            }
        }

        return preg_match_all(
            '/\b(?:SELECT\b[\s\S]{0,500}?\bFROM\b|INSERT\s+INTO\b|UPDATE\s+[`a-z0-9_.]+\s+SET\b|DELETE\s+FROM\b|CREATE\s+TABLE\b|ALTER\s+TABLE\b|SHOW\s+TABLES\b)/i',
            $phpSource
        );
    }

    /** @return list<string> */
    private static function filesWithPdoSql(string $dir, string $base): array
    {
        $matches = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) @file_get_contents($file->getPathname());
            if (preg_match('/\\$pdo->(prepare|query|exec)|->prepare\\(|->query\\(/', $source) === 1) {
                $matches[] = ltrim(str_replace($base, '', $file->getPathname()), '/');
            }
        }
        sort($matches);
        return $matches;
    }
}
