<?php

declare(strict_types=1);

/**
 * Wiring F4: configuracion/index.php y guardar.php delegan en
 * ConfiguracionService, no conservan SQL inline y preservan transacción, ensure
 * de WhatsApp, CSRF y auditoría.
 */
final class ConfiguracionWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/configuracion/';
        $sinSql = '/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i';

        // --- index.php ---
        $index = (string) @file_get_contents($panel . 'index.php');
        Assert::isTrue('Wiring configuracion (index)', 'usa ConfiguracionService', strpos($index, 'ConfiguracionService') !== false);
        Assert::same('Wiring configuracion (index)', 'delega lectura de configuración', 2, substr_count($index, '->obtenerConfiguracion('));
        Assert::same('Wiring configuracion (index)', 'delega lectura de empresa', 1, substr_count($index, '->obtenerEmpresa('));
        Assert::same('Wiring configuracion (index)', 'sin SQL inline', 0, preg_match_all($sinSql, $index));
        Assert::isTrue('Wiring configuracion (index)', 'conserva guard admin', strpos($index, 'requiereAdmin()') !== false);
        Assert::isTrue('Wiring configuracion (index)', 'conserva ensure WhatsApp', strpos($index, 'whatsappEnsureColumnas(') !== false);

        // --- guardar.php ---
        $guardar = (string) @file_get_contents($panel . 'guardar.php');
        Assert::isTrue('Wiring configuracion (guardar)', 'usa ConfiguracionService', strpos($guardar, 'ConfiguracionService') !== false);
        Assert::same('Wiring configuracion (guardar)', 'delega WhatsApp', 1, substr_count($guardar, '->actualizarWhatsapp('));
        Assert::same('Wiring configuracion (guardar)', 'delega update empresa', 1, substr_count($guardar, '->actualizarEmpresa('));
        Assert::same('Wiring configuracion (guardar)', 'delega insert empresa', 1, substr_count($guardar, '->insertarEmpresa('));
        Assert::same('Wiring configuracion (guardar)', 'delega propagación de RUT', 1, substr_count($guardar, '->propagarRutProducto('));
        Assert::same('Wiring configuracion (guardar)', 'sin SQL inline', 0, preg_match_all($sinSql, $guardar));
        Assert::isTrue('Wiring configuracion (guardar)', 'conserva transacción', strpos($guardar, 'beginTransaction()') !== false && strpos($guardar, 'commit()') !== false);
        Assert::isTrue('Wiring configuracion (guardar)', 'conserva CSRF', strpos($guardar, 'verifyCsrfOrFail()') !== false);
        Assert::isTrue('Wiring configuracion (guardar)', 'conserva auditoría', strpos($guardar, 'registrarAuditoria') !== false);
    }
}
