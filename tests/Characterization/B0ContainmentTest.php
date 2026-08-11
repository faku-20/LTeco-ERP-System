<?php

declare(strict_types=1);

final class B0ContainmentTest
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        $panelEndpoint = (string) file_get_contents($root . '/lteco-panel/api/storefront/v1/order-simulated-payment.php');
        $panelSupport = (string) file_get_contents($root . '/lteco-panel/includes/storefront_api.php');
        $storefrontConfig = (string) file_get_contents($root . '/storefront/config/storefront.php');
        $storefrontController = (string) file_get_contents($root . '/storefront/app/Http/Controllers/CheckoutController.php');
        $storefrontView = (string) file_get_contents($root . '/storefront/resources/views/checkout/index.blade.php');
        $legacyEcommerce = (string) file_get_contents($root . '/public-web/includes/ecommerce.php');
        $legacyCart = (string) file_get_contents($root . '/public-web/carrito.php');
        $legacyCheckout = (string) file_get_contents($root . '/public-web/checkout.php');
        $legacyHeader = (string) file_get_contents($root . '/public-web/includes/header.php');
        $legacyDetail = (string) file_get_contents($root . '/public-web/detalle.php');

        $guardPos = strpos($panelEndpoint, 'storefrontPaymentSimulatorEnabled()');
        $effectPos = strpos($panelEndpoint, 'confirmarPagoTarjetaSimulada');

        Assert::isTrue('B0 MQ-001', 'panel define gate simulator default-off', str_contains($panelSupport, "LTECO_STOREFRONT_PAYMENT_SIMULATOR_ENABLED"));
        Assert::isTrue('B0 MQ-001', 'panel deshabilita simulator en production', str_contains($panelSupport, '!appIsProduction()'));
        Assert::isTrue('B0 MQ-001', 'panel rechaza simulator con 403', str_contains($panelEndpoint, "StorefrontApiException(403, 'payment_simulator_disabled'"));
        Assert::isTrue('B0 MQ-001', 'panel evalua gate antes del efecto comercial', $guardPos !== false && $effectPos !== false && $guardPos < $effectPos);
        Assert::isTrue('B0 MQ-001', 'storefront config simulator default false', str_contains($storefrontConfig, "env('STOREFRONT_PAYMENT_SIMULATOR_ENABLED', false)"));
        Assert::isTrue('B0 MQ-001', 'storefront validator no acepta tarjeta sin gate', str_contains($storefrontController, "\$paymentMethods = ['cash'];"));
        Assert::isTrue('B0 MQ-001', 'storefront card solo se agrega si el gate esta habilitado', str_contains($storefrontController, "\$paymentMethods[] = 'card';"));
        Assert::isFalse('B0 MQ-001', 'checkout productivo no muestra tarjeta como default', str_contains($storefrontView, "old('payment_method', 'card')"));
        Assert::isTrue('B0 MQ-001', 'checkout productivo deja efectivo como default', str_contains($storefrontView, "old('payment_method', 'cash')"));

        Assert::isFalse('B0 MQ-002', 'public-web ya no tiene flag temporal de writes', str_contains($legacyEcommerce, "LTECO_PUBLIC_WEB_ECOMMERCE_WRITES_ENABLED"));
        Assert::isFalse('B0 MQ-002', 'public-web ya no define creador legacy de pedidos', str_contains($legacyEcommerce, 'function ecommerceCrearPedido'));
        Assert::isFalse('B0 MQ-002', 'public-web ya no inserta pedidos ecommerce', str_contains($legacyEcommerce, 'INSERT INTO ecommerce_pedido'));
        Assert::isFalse('B0 MQ-002', 'public-web ya no crea preferences MercadoPago', str_contains($legacyEcommerce, 'function ecommerceMercadoPagoPreferencia'));
        Assert::isFalse('B0 MQ-002', 'public-web ya no llama checkout/preferences MercadoPago', str_contains($legacyEcommerce, 'https://api.mercadopago.com/checkout/preferences'));
        Assert::isTrue('B0 MQ-002', 'carrito legacy queda retirado', str_contains($legacyCart, "ecommerceLegacyCommercialGone('carrito')"));
        Assert::isTrue('B0 MQ-002', 'checkout legacy queda retirado', str_contains($legacyCheckout, "ecommerceLegacyCommercialGone('checkout')"));
        Assert::isTrue('B0 MQ-002', 'nav publica apunta al storefront actual', str_contains($legacyHeader, "LTECO_STOREFRONT_PUBLIC_URL"));
        Assert::isFalse('B0 MQ-002', 'detalle publico no ofrece checkout legacy', str_contains($legacyDetail, "checkout.php?vehiculo="));
        Assert::isFalse('B0 MQ-002', 'detalle publico no postea al carrito legacy', str_contains($legacyDetail, "action=\"<?=publicBaseUrl('carrito.php')?>\""));
    }
}
