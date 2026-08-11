<?php

declare(strict_types=1);

final class DistribuidorCommonLimpiezaTest
{
    public static function run(): void
    {
        $ruta = dirname(__DIR__, 2) . '/lteco-panel/distribuidores/_common.php';
        $source = (string) @file_get_contents($ruta);

        Assert::isTrue('Limpieza common distribuidor', '_common.php legible', $source !== '');

        foreach ([
            'distribuidorActual',
            'distribuidorUpsertStock',
            'distribuidorWhatsappPedido',
            'distribuidorResumenComisiones',
            'distribuidorRemitosPendientes',
            'distribuidorPostventasAbiertas',
        ] as $helper) {
            Assert::same(
                'Limpieza common distribuidor',
                "elimina helper sin uso {$helper}",
                0,
                substr_count($source, "function {$helper}(")
            );
        }

        Assert::isTrue(
            'Limpieza common distribuidor',
            'conserva guard del portal',
            strpos($source, 'function requiereDistribuidorPanel(') !== false
        );
        Assert::same(
            'Limpieza common distribuidor',
            'elimina registro legacy de comisión ya migrado',
            0,
            substr_count($source, 'function distribuidorRegistrarComision(')
        );
        $helpers = (string) @file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/includes/helpers.php');
        Assert::same(
            'Limpieza common distribuidor',
            'elimina helper global de usuario comisión ya migrado',
            0,
            substr_count($helpers, 'function obtenerUsuarioComisionDistribuidor(')
        );
        Assert::same(
            'Limpieza common distribuidor',
            'elimina query builder de stock sin uso',
            0,
            substr_count($source, 'function distribuidorStockQueryBase(')
        );
        Assert::same(
            'Limpieza common distribuidor',
            'elimina query builder de pedidos sin uso',
            0,
            substr_count($source, 'function distribuidorPedidoQueryBase(')
        );
    }
}
