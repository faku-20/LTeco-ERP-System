<?php

declare(strict_types=1);

final class B2CLegacyEcommerceRetirementTest
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        $legacyEcommerce = (string) file_get_contents($root . '/public-web/includes/ecommerce.php');
        $legacyCart = (string) file_get_contents($root . '/public-web/carrito.php');
        $legacyCheckout = (string) file_get_contents($root . '/public-web/checkout.php');
        $legacyWebhook = (string) file_get_contents($root . '/public-web/webhook-mercadopago.php');
        $legacyHeader = (string) file_get_contents($root . '/public-web/includes/header.php');
        $legacyDetail = (string) file_get_contents($root . '/public-web/detalle.php');
        $legacyOrder = (string) file_get_contents($root . '/public-web/pedido.php');
        $panelOrder = (string) file_get_contents($root . '/src/Application/Ecommerce/StorefrontOrderService.php');
        $panelReservation = (string) file_get_contents($root . '/src/Infrastructure/Repository/EcommerceReservationRepository.php');

        Assert::isTrue('B2C legacy retirement', 'helper definitivo responde rutas comerciales retiradas', str_contains($legacyEcommerce, 'function ecommerceLegacyCommercialGone'));
        Assert::isTrue('B2C legacy retirement', 'helper usa 410 para métodos mutativos', str_contains($legacyEcommerce, 'http_response_code(410)'));
        Assert::isTrue('B2C legacy retirement', 'carrito legacy delegado a Storefront', str_contains($legacyCart, "ecommerceLegacyCommercialGone('carrito')"));
        Assert::isTrue('B2C legacy retirement', 'checkout legacy delegado a Storefront', str_contains($legacyCheckout, "ecommerceLegacyCommercialGone('checkout')"));
        Assert::isTrue('B2C legacy retirement', 'webhook MercadoPago legacy devuelve Gone', str_contains($legacyWebhook, 'http_response_code(410)'));
        Assert::isTrue('B2C legacy retirement', 'webhook MercadoPago documenta retiro', str_contains($legacyWebhook, 'legacy_mercadopago_webhook_retired'));

        foreach ([
            'function ecommerceCrearPedido',
            'function ecommerceMercadoPagoPreferencia',
            'function ecommerceConfirmarPago',
            'function ecommerceLiberarVencidos',
            'INSERT INTO ecommerce_pedido',
            'INSERT INTO ecommerce_pago',
            'https://api.mercadopago.com/checkout/preferences',
            "UPDATE producto SET Estado='Reservado'",
        ] as $forbidden) {
            Assert::isFalse('B2C legacy retirement', 'public-web sin writer legacy: ' . $forbidden, str_contains($legacyEcommerce, $forbidden));
        }

        Assert::isTrue('B2C legacy retirement', 'navegación carrito apunta a Storefront', str_contains($legacyHeader, "LTECO_STOREFRONT_PUBLIC_URL"));
        Assert::isFalse('B2C legacy retirement', 'header no lee carrito de sesión legacy', str_contains($legacyHeader, 'ecommerceCarritoIds'));
        Assert::isTrue('B2C legacy retirement', 'detalle apunta a tienda actual', str_contains($legacyDetail, "storefrontPublicUrl('modelos')"));
        Assert::isFalse('B2C legacy retirement', 'pedido histórico no libera reservas al visualizar', str_contains($legacyOrder, 'ecommerceLiberarVencidos'));
        Assert::isTrue('B2C legacy retirement', 'StorefrontOrderService conserva autoridad de creación', str_contains($panelOrder, 'INSERT INTO ecommerce_pedido'));
        Assert::isTrue('B2C legacy retirement', 'ReservationRepository conserva autoridad de reserva', str_contains($panelReservation, 'reserve('));
    }
}
