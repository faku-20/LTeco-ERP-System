<?php
declare(strict_types=1);

final class EcommerceWiringTest
{
    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        $checkout = (string) file_get_contents($root . '/public-web/checkout.php');
        $cuenta = (string) file_get_contents($root . '/public-web/includes/ecommerce.php');
        $storefrontOrder = (string) file_get_contents($root . '/src/Application/Ecommerce/StorefrontOrderService.php');
        $reservation = (string) file_get_contents($root . '/src/Infrastructure/Repository/EcommerceReservationRepository.php');
        $storefrontCheckout = (string) file_get_contents($root . '/storefront/app/Http/Controllers/CheckoutController.php');
        $panel = (string) file_get_contents($root . '/src/Application/Ecommerce/PedidoPanelService.php');
        $venta = (string) file_get_contents($root . '/src/Application/Venta/VentaLineasService.php');
        $migration = (string) file_get_contents($root . '/database/migrations/2026_07_22_ecommerce_hardening.sql');
        $operations = (string) file_get_contents($root . '/database/migrations/2026_07_22_ecommerce_operations.sql');
        $accountPage = (string) file_get_contents($root . '/public-web/cuenta.php');
        $receipt = (string) file_get_contents($root . '/public-web/comprobante.php');
        $cart = (string) file_get_contents($root . '/public-web/carrito.php');
        $cron = (string) file_get_contents($root . '/lteco-panel/cron/ecommerce.php');
        $cronService = (string) file_get_contents($root . '/src/Application/Ecommerce/EcommerceCronService.php');
        $auth = (string) file_get_contents($root . '/src/Presentation/Panel/Support/auth.php');

        Assert::isTrue('Ecommerce', 'checkout legacy redirige a Storefront', str_contains($checkout, "ecommerceLegacyCommercialGone('checkout')"));
        Assert::isFalse('Ecommerce', 'checkout no ofrece invitado', str_contains($checkout, 'comprar sin cuenta'));
        Assert::isFalse('Ecommerce', 'checkout no ofrece envío sin tarifa', str_contains($checkout, 'value="Envio"'));
        Assert::isTrue('Ecommerce', 'cuenta exige correo verificado', str_contains($cuenta, 'CorreoVerificadoEn IS NOT NULL'));
        Assert::isTrue('Ecommerce', 'registro informa resultado SMTP', str_contains($cuenta, "'correo_enviado' => ecommerceEnviarVerificacion"));
        Assert::isTrue('Ecommerce', 'registro valida confirmación de clave', str_contains($cuenta, 'Las contraseñas no coinciden'));
        Assert::isTrue('Ecommerce', 'registro valida empresa y RUT', str_contains($cuenta, "\$tipoCliente === 'Empresa'"));
        Assert::isTrue('Ecommerce', 'correo deja diagnóstico sin dirección completa', str_contains($cuenta, "[ECOMMERCE_MAIL]"));
        Assert::isTrue('Ecommerce', 'tokens se guardan hasheados', str_contains($cuenta, "hash('sha256', \$token)"));
        Assert::isTrue('Ecommerce', 'pedido oficial se crea desde StorefrontOrderService', str_contains($storefrontOrder, 'INSERT INTO ecommerce_pedido'));
        Assert::isTrue('Ecommerce', 'reserva oficial usa lock atómico de producto', str_contains($reservation, "Estado='Disponible' AND Stock>0 AND MostrarEnWeb=1"));
        Assert::isTrue('Ecommerce', 'pago tardío tiene excepción en panel', str_contains($panel, 'ExcepcionPagoSinStock'));
        Assert::isTrue('Ecommerce', 'cobro no activa postventa', str_contains($panel, 'registrarVehiculoPendienteEntrega'));
        Assert::isTrue('Ecommerce', 'entrega activa postventa', str_contains($panel, 'activarPostventaEnEntrega'));
        Assert::isTrue('Ecommerce', 'detalle sin postventa existe', str_contains($venta, 'registrarVehiculoPendienteEntrega'));
        Assert::isTrue('Ecommerce', 'ocupación exclusiva persistida', str_contains($migration, 'PRIMARY KEY (IdVehiculo)'));
        Assert::isTrue('Ecommerce', 'auditoría ecommerce persistida', str_contains($migration, 'CREATE TABLE IF NOT EXISTS ecommerce_auditoria'));
        Assert::isTrue('Ecommerce', 'cuenta permite mantener perfil', str_contains($accountPage, "\$accion==='perfil'"));
        Assert::isTrue('Ecommerce', 'cuenta muestra garantía', str_contains($accountPage, '<h2>Garantías</h2>'));
        Assert::isTrue('Ecommerce', 'cuenta permite ejercer privacidad', str_contains($accountPage, "\$accion==='privacidad'"));
        Assert::isTrue('Ecommerce', 'comprobante exige sesión', str_contains($receipt, 'ecommerceExigirCuenta('));
        Assert::isTrue('Ecommerce', 'comprobante valida propietario', str_contains($receipt, 'ecommercePedidoPorToken($pdo,$token,(int)$cuenta'));
        Assert::isTrue('Ecommerce', 'panel tiene reembolso trazable', str_contains($panel, 'solicitarReembolso'));
        Assert::isTrue('Ecommerce', 'operaciones guarda solicitudes de privacidad', str_contains($operations, 'ecommerce_solicitud_privacidad'));
        Assert::isTrue('Ecommerce', 'operaciones incluye cola de avisos', str_contains($operations, 'ecommerce_notificacion'));
        Assert::isTrue('Ecommerce', 'tarea automática recuerda services', str_contains($cronService, 'RecordatorioService'));
        Assert::isTrue('Ecommerce', 'tarea automática admite prueba segura', str_contains($cron, '--dry-run'));
        Assert::isTrue('Ecommerce', 'administrador recibe módulo ecommerce', str_contains($auth, "'ecommerce'"));
        Assert::isTrue('Ecommerce', 'carrito legacy queda retirado', str_contains($cart, "ecommerceLegacyCommercialGone('carrito')"));
        Assert::isTrue('Ecommerce', 'pedido oficial admite varias unidades', str_contains($storefrontOrder, 'foreach ($items->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item)'));
        Assert::isTrue('Ecommerce', 'reserva oficial usa transacción', str_contains($reservation, 'beginTransaction()'));
        Assert::isTrue('Ecommerce', 'login tiene límite adicional', str_contains($cuenta, 'ecommerce_limite_acceso'));
        Assert::isTrue('Ecommerce', 'métricas no guardan visitante', str_contains($cuenta, 'ecommerce_metrica_diaria'));
        Assert::isTrue('Ecommerce', 'Storefront exige aceptación de términos', str_contains($storefrontCheckout, "'accept_terms' => ['accepted']"));
        Assert::isTrue('Ecommerce', 'Storefront registra consentimiento de checkout', str_contains($storefrontCheckout, "'checkout_terms'"));
    }
}
