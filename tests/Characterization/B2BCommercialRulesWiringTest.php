<?php

declare(strict_types=1);

use Lteco\Domain\Balance\BalanceCalculo;
use Lteco\Domain\Venta\ReglasComerciales;

final class B2BCommercialRulesWiringTest
{
    public static function run(): void
    {
        self::reglasCanalizadas();
        self::wiringProductivo();
        self::historicosBalance();
    }

    private static function reglasCanalizadas(): void
    {
        $caso = 'B2B reglas comerciales';

        $efectivo = ReglasComerciales::calcular([
            'subtotalBruto' => 10000,
            'metodoPago' => 'Efectivo',
            'descuentoContadoPct' => 5,
            'tasaIVA' => 22,
        ]);
        Assert::isTrue($caso, 'efectivo es contado', $efectivo['esContado'] === true);
        Assert::money($caso, 'efectivo aplica descuento central', 500.00, (float) $efectivo['descuentoAplicado']);
        Assert::money($caso, 'efectivo total cliente', 9500.00, (float) $efectivo['total']);

        $transferencia = ReglasComerciales::calcular([
            'subtotalBruto' => 10000,
            'metodoPago' => 'Transferencia',
            'descuentoContadoPct' => 5,
            'tasaIVA' => 22,
        ]);
        Assert::isTrue($caso, 'transferencia es contado', $transferencia['esContado'] === true);
        Assert::same($caso, 'transferencia equivale a efectivo', $efectivo['total'], $transferencia['total']);

        $tarjeta = ReglasComerciales::calcular([
            'subtotalBruto' => 10000,
            'metodoPago' => 'Tarjeta',
            'tipoTarjeta' => 'Crédito',
            'descuentoContadoPct' => 5,
            'recargoTarjetaPct' => 3.5,
            'tasaIVA' => 22,
        ]);
        Assert::money($caso, 'tarjeta no sube total cliente', 10000.00, (float) $tarjeta['total']);
        Assert::money($caso, 'tarjeta registra costo interno', 350.00, (float) $tarjeta['comisionTarjeta']);
        Assert::money($caso, 'IVA configurable 10%', 909.09, ReglasComerciales::ivaIncluido(10000.00, 10.0));
        Assert::money($caso, 'Balance delega IVA configurable', 909.09, BalanceCalculo::ivaDesdeBruto(10000.00, 10.0));
    }

    private static function wiringProductivo(): void
    {
        $root = dirname(__DIR__, 2);
        $guardar = (string) file_get_contents($root . '/lteco-panel/ventas/guardar.php');
        $pedidoPanel = (string) file_get_contents($root . '/src/Application/Ecommerce/PedidoPanelService.php');
        $legacyEcommerce = (string) file_get_contents($root . '/public-web/includes/ecommerce.php');
        $catalog = (string) file_get_contents($root . '/src/Application/Ecommerce/CatalogService.php');
        $reservation = (string) file_get_contents($root . '/src/Infrastructure/Repository/EcommerceReservationRepository.php');
        $distribuidor = (string) file_get_contents($root . '/src/Domain/Distribuidor/VentaDistribuidorCalculo.php');
        $ventasIndex = (string) file_get_contents($root . '/lteco-panel/ventas/index.php');

        Assert::isTrue('B2B wiring', 'venta panel usa Regla central', str_contains($guardar, 'ReglasComerciales::calcular'));
        Assert::isTrue('B2B wiring', 'ecommerce oficial usa Regla central', str_contains($pedidoPanel, 'ReglasComerciales::calcular'));
        Assert::isFalse('B2B wiring', 'ecommerce legacy no conserva cálculo comercial activo', str_contains($legacyEcommerce, 'ReglasComerciales::calcular'));
        Assert::isTrue('B2B wiring', 'catálogo usa IVA central', str_contains($catalog, 'ReglasComerciales::ivaIncluido'));
        Assert::isTrue('B2B wiring', 'reserva normaliza IVA por configuración central', str_contains($reservation, 'ConfiguracionComercial::normalizar'));
        Assert::isTrue('B2B wiring', 'distribuidor usa IVA central', str_contains($distribuidor, 'ReglasComerciales::ivaIncluido'));
        Assert::isTrue('B2B wiring', 'listado fallback usa IVA central', str_contains($ventasIndex, 'ReglasComerciales::ivaIncluido'));

        foreach ([
            'src/Application/Ecommerce/PedidoPanelService.php' => $pedidoPanel,
            'lteco-panel/ventas/index.php' => $ventasIndex,
            'src/Domain/Distribuidor/VentaDistribuidorCalculo.php' => $distribuidor,
        ] as $path => $source) {
            Assert::isFalse('B2B wiring', $path . ' sin 22/122 inline', preg_match('/22\s*\/\s*122|\*\s*22\s*\/\s*122/', $source) === 1);
        }
    }

    private static function historicosBalance(): void
    {
        $root = dirname(__DIR__, 2);
        $repository = (string) file_get_contents($root . '/src/Infrastructure/Repository/BalanceRepository.php');
        $index = (string) file_get_contents($root . '/lteco-panel/balance/index.php');
        $export = (string) file_get_contents($root . '/lteco-panel/balance/exportar.php');
        $receipt = (string) file_get_contents($root . '/src/Support/VentaView.php');
        $anulacion = (string) file_get_contents($root . '/src/Infrastructure/Repository/VentaAnulacionRepository.php');

        Assert::isTrue('B2B históricos', 'mesesResumen usa TipoCambioAplicado si existe', str_contains($repository, 'COALESCE(NULLIF(TipoCambioAplicado, 0)'));
        Assert::isTrue('B2B históricos', 'balance lee IVA persistido de venta', str_contains($repository, 'v.MontoIVA'));
        Assert::isTrue('B2B históricos', 'balance index usa conversión histórica', str_contains($index, 'convertirMontoVentaAUyu'));
        Assert::isTrue('B2B históricos', 'balance index suma MontoIVA histórico', str_contains($index, "\$venta['MontoIVA']"));
        Assert::isTrue('B2B históricos', 'balance export usa conversión histórica', str_contains($export, 'convertirMontoVentaAUyu'));
        Assert::isTrue('B2B históricos', 'balance export usa TC histórico de gastos', str_contains($export, "\$gasto['TipoCambioAplicado']"));
        Assert::isTrue('B2B históricos', 'comprobante usa datos de venta persistidos', str_contains($receipt, "['Total']") || str_contains($receipt, "'Total'"));
        Assert::isTrue('B2B históricos', 'anulación revierte comisiones por IdVenta', str_contains($anulacion, 'WHERE IdVenta = ?'));
    }
}
