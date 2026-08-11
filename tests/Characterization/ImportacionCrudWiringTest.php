<?php

declare(strict_types=1);

/**
 * Wiring E4: importaciones/crear.php y editar.php delegan las escrituras en
 * ImportacionCrudService y no conservan SQL inline. Conservan CSRF y el
 * redirect legacy (sin flash, sin auditoría).
 */
final class ImportacionCrudWiringTest
{
    public static function run(): void
    {
        $panel = dirname(__DIR__, 2) . '/lteco-panel/importaciones/';

        // --- crear.php ---
        $crear = (string) @file_get_contents($panel . 'crear.php');
        Assert::isTrue('Wiring crud importacion (crear.php)', 'crear.php legible', $crear !== '');
        Assert::isTrue('Wiring crud importacion (crear.php)', 'crear usa ImportacionCrudService', strpos($crear, 'ImportacionCrudService') !== false);
        Assert::same('Wiring crud importacion (crear.php)', 'crear delega el alta', 1, substr_count($crear, '->crear('));
        Assert::same(
            'Wiring crud importacion (crear.php)',
            'crear sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $crear)
        );
        Assert::isTrue('Wiring crud importacion (crear.php)', 'crear conserva CSRF', strpos($crear, 'verifyCsrfOrFail()') !== false);

        // --- editar.php ---
        $editar = (string) @file_get_contents($panel . 'editar.php');
        Assert::isTrue('Wiring crud importacion (editar.php)', 'editar.php legible', $editar !== '');
        Assert::isTrue('Wiring crud importacion (editar.php)', 'editar usa ImportacionCrudService', strpos($editar, 'ImportacionCrudService') !== false);
        Assert::same('Wiring crud importacion (editar.php)', 'editar delega la lectura', 1, substr_count($editar, '->obtener('));
        Assert::same('Wiring crud importacion (editar.php)', 'editar delega la edición', 1, substr_count($editar, '->editar('));
        Assert::same(
            'Wiring crud importacion (editar.php)',
            'editar sin SQL inline',
            0,
            preg_match_all('/(?<![<\/.])\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $editar)
        );
        Assert::isTrue('Wiring crud importacion (editar.php)', 'editar conserva CSRF', strpos($editar, 'verifyCsrfOrFail()') !== false);
    }
}
