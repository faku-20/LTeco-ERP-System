<?php

declare(strict_types=1);

use Lteco\Support\VentaView;

final class VentaConsultaViewTest
{
    public static function run(): void
    {
        self::formatoComprobante();
        self::mensajeComprobante();
        self::mensajePostventa();
        self::wiringPaginas();
    }

    private static function formatoComprobante(): void
    {
        $caso = 'Vista venta - formato';
        $ventaTarjeta = [
            'IdVenta' => 42,
            'NumeroFactura' => '',
            'MetodoPago' => 'Tarjeta',
            'TipoTarjeta' => 'Credito',
            'MarcaTarjeta' => 'Visa',
            'CuotasTarjeta' => 3,
        ];

        Assert::same($caso, 'numero fallback', '000000042', VentaView::numeroComprobante($ventaTarjeta));
        Assert::same($caso, 'monto UYU', '$ 1.234,50', VentaView::montoComprobante(1234.5, 'UYU'));
        Assert::same($caso, 'monto USD', 'USD 1.234,50', VentaView::montoComprobante(1234.5, 'usd'));
        Assert::same(
            $caso,
            'tarjeta con datos',
            'Tarjeta - Credito Visa 3 cuotas',
            VentaView::metodoPagoComprobante($ventaTarjeta)
        );
    }

    private static function mensajeComprobante(): void
    {
        $venta = [
            'IdVenta' => 7,
            'NombreApellido' => 'Ana Perez',
            'FechaVenta' => '2026-06-13',
            'EstadoVenta' => 'Confirmada',
            'MetodoPago' => 'Efectivo',
            'Total' => 1000,
            'MontoPagado' => 750,
            'SaldoPendiente' => 250,
            'Moneda' => 'UYU',
        ];
        $detalles = [[
            'Nombre' => 'E-Bike',
            'Cantidad' => 1,
            'Subtotal' => 1000,
            'Moneda' => 'UYU',
            'TipoProducto' => 'Moto',
            'Modelo' => 'M1',
            'NumeroMotor' => 'MOTOR-9',
        ]];

        $esperado = "Hola Ana Perez, te compartimos el comprobante de tu compra en Lteco.\n\n"
            . "Comprobante 000000007\n"
            . "Fecha: 13/06/2026\n"
            . "Estado: Confirmada\n"
            . "Metodo de pago: Efectivo\n"
            . "Total: $ 1.000,00\n"
            . "Pagado: $ 750,00\n"
            . "Saldo pendiente: $ 250,00\n\n"
            . "Detalle:\n"
            . "- E-Bike (modelo M1, motor MOTOR-9) x1 — $ 1.000,00\n\n"
            . 'Verificacion: https://example.test/c/7';
        $real = VentaView::mensajeComprobante(
            $venta,
            $detalles,
            'Lteco',
            '13/06/2026',
            'https://example.test/c/7'
        );

        // Los acentos forman parte del texto legacy; se normalizan solo en el esperado.
        $normalizar = static fn (string $texto): string => strtr($texto, [
            'Método' => 'Metodo',
            'Verificación' => 'Verificacion',
        ]);
        Assert::same('Vista venta - mensaje comprobante', 'texto exacto', $esperado, $normalizar($real));
    }

    private static function mensajePostventa(): void
    {
        $venta = [
            'IdVenta' => 9,
            'NumeroFactura' => 'F-2026-000009',
            'NombreApellido' => 'Juan Rodriguez',
        ];
        $esperado = "Hola Juan 👋\n\n"
            . "Gracias por tu compra en LTecobike.\n\n"
            . "Tu comprobante de compra es: F-2026-000009\n\n"
            . "Tu garantía es válida hasta: 30/06/2027\n\n"
            . "Los services recomendados se realizan cada 3 meses o cada 1500 km, lo que ocurra primero.\n\n"
            . "📅 Services programados:\n\n"
            . "- Service 1: 30/09/2026\n\n"
            . "- Service 2: 30/12/2026\n\n"
            . "Esperamos que disfrutes tu moto eléctrica.\n\n"
            . "Ante cualquier consulta, podés comunicarte con nosotros por este medio.\n\n"
            . "Saludos,\n\n"
            . 'Equipo LTecobike 🏍️';

        Assert::same(
            'Vista venta - mensaje postventa',
            'texto exacto',
            $esperado,
            VentaView::mensajePostventa($venta, '2027-06-30', ['2026-09-30', '2026-12-30'])
        );
    }

    private static function wiringPaginas(): void
    {
        foreach (['comprobante.php', 'detalle.php'] as $pagina) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/' . $pagina);
            Assert::isTrue('Wiring consulta venta', $pagina . ' legible', is_string($source));
            if (!is_string($source)) {
                continue;
            }

            Assert::same(
                'Wiring consulta venta',
                $pagina . ' carga venta por servicio',
                1,
                substr_count($source, '$ventaQuery->ventaConCliente($id)')
            );
            Assert::same(
                'Wiring consulta venta',
                $pagina . ' carga detalles por servicio',
                1,
                substr_count($source, '$ventaQuery->detalles($id)')
            );
            Assert::same(
                'Wiring consulta venta',
                $pagina . ' sin SELECT inline',
                0,
                preg_match('/["\']\s*SELECT\b/i', $source)
            );
        }

        $detalle = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/detalle.php');
        Assert::same(
            'Wiring consulta venta',
            'detalle carga garantia y services por servicio',
            1,
            is_string($detalle) ? substr_count($detalle, '$ventaQuery->garantiaYServices($id, $detalles)') : 0
        );

        $whatsapp = file_get_contents(dirname(__DIR__, 2) . '/lteco-panel/ventas/whatsapp_reenviar.php');
        Assert::isTrue('Wiring consulta venta', 'whatsapp_reenviar.php legible', is_string($whatsapp));
        Assert::same(
            'Wiring consulta venta',
            'WhatsApp carga sus datos por servicio',
            1,
            is_string($whatsapp) ? substr_count($whatsapp, '$ventaQuery->datosWhatsapp($idVenta)') : 0
        );
        Assert::same(
            'Wiring consulta venta',
            'WhatsApp sin SELECT inline',
            0,
            is_string($whatsapp) ? preg_match('/["\']\s*SELECT\b/i', $whatsapp) : 1
        );
    }
}
