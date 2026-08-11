<?php

declare(strict_types=1);

namespace Lteco\Support;

final class VentaView
{
    /**
     * @param array<string,mixed> $venta
     */
    public static function numeroComprobante(array $venta): string
    {
        return trim((string) ($venta['NumeroFactura'] ?? ''))
            ?: str_pad((string) ($venta['IdVenta'] ?? '0'), 9, '0', STR_PAD_LEFT);
    }

    public static function montoComprobante(mixed $monto, mixed $moneda = 'UYU'): string
    {
        $simbolo = strtoupper((string) $moneda) === 'USD' ? 'USD ' : '$ ';

        return $simbolo . number_format((float) $monto, 2, ',', '.');
    }

    /**
     * @param array<string,mixed> $venta
     */
    public static function metodoPagoComprobante(array $venta): string
    {
        $texto = (string) ($venta['MetodoPago'] ?? 'Efectivo');
        if ($texto !== 'Tarjeta') {
            return $texto;
        }

        $datos = array_filter([
            $venta['TipoTarjeta'] ?? '',
            $venta['MarcaTarjeta'] ?? '',
            !empty($venta['CuotasTarjeta'])
                ? $venta['CuotasTarjeta'] . ' cuota' . ((int) $venta['CuotasTarjeta'] === 1 ? '' : 's')
                : '',
        ]);

        return $datos ? $texto . ' - ' . implode(' ', $datos) : $texto;
    }

    /**
     * @param array<string,mixed> $venta
     * @param list<array<string,mixed>> $detalles
     */
    public static function mensajeComprobante(
        array $venta,
        array $detalles,
        string $nombreEmpresa,
        string $fecha,
        string $enlaceVenta
    ): string {
        $monedaVenta = (string) ($venta['Moneda'] ?? 'UYU');
        $lineas = [];
        foreach ($detalles as $detalle) {
            $nombre = (string) ($detalle['Nombre'] ?? 'Producto');
            $cantidad = (int) ($detalle['Cantidad'] ?? 0);
            $subtotal = self::montoComprobante(
                $detalle['Subtotal'] ?? 0,
                $detalle['Moneda'] ?? $monedaVenta
            );
            $extra = '';

            if (($detalle['TipoProducto'] ?? '') === 'Moto') {
                $partes = [];
                if (!empty($detalle['Modelo'])) {
                    $partes[] = 'modelo ' . $detalle['Modelo'];
                }
                if (!empty($detalle['NumeroMotor'])) {
                    $partes[] = 'motor ' . $detalle['NumeroMotor'];
                }
                $extra = $partes ? ' (' . implode(', ', $partes) . ')' : '';
            }

            $lineas[] = "- {$nombre}{$extra} x{$cantidad} — {$subtotal}";
        }

        $mensaje = 'Hola';
        if (!empty($venta['NombreApellido'])) {
            $mensaje .= ' ' . $venta['NombreApellido'];
        }
        $mensaje .= ", te compartimos el comprobante de tu compra en {$nombreEmpresa}.\n\n";
        $mensaje .= 'Comprobante ' . self::numeroComprobante($venta) . "\n";
        $mensaje .= "Fecha: {$fecha}\n";
        $mensaje .= 'Estado: ' . (string) ($venta['EstadoVenta'] ?? 'Confirmada') . "\n";
        $mensaje .= 'Método de pago: ' . self::metodoPagoComprobante($venta) . "\n";
        $mensaje .= 'Total: ' . self::montoComprobante($venta['Total'] ?? 0, $monedaVenta) . "\n";
        $mensaje .= 'Pagado: ' . self::montoComprobante($venta['MontoPagado'] ?? 0, $monedaVenta) . "\n";
        $mensaje .= 'Saldo pendiente: '
            . self::montoComprobante($venta['SaldoPendiente'] ?? 0, $monedaVenta) . "\n\n";
        $mensaje .= "Detalle:\n" . implode("\n", $lineas) . "\n\n";
        $mensaje .= "Verificación: {$enlaceVenta}";

        return $mensaje;
    }

    /**
     * @param array<string,mixed> $venta
     * @param list<string> $serviceDates
     */
    public static function mensajePostventa(
        array $venta,
        ?string $garantiaFin,
        array $serviceDates
    ): string {
        $nombreCliente = trim((string) ($venta['NombreApellido'] ?? ''));
        $primerNombre = trim(explode(' ', $nombreCliente)[0] ?? $nombreCliente);
        if ($primerNombre === '') {
            $primerNombre = 'Cliente';
        } else {
            $primerNombre = mb_convert_case(mb_strtolower($primerNombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }

        $mensaje = "Hola {$primerNombre} 👋\n\n";
        $mensaje .= "Gracias por tu compra en LTecobike.\n\n";
        $mensaje .= 'Tu comprobante de compra es: ' . self::numeroComprobante($venta) . "\n\n";
        if ($garantiaFin) {
            $mensaje .= 'Tu garantía es válida hasta: ' . date('d/m/Y', strtotime($garantiaFin)) . "\n\n";
        }

        $mensaje .= "Los services recomendados se realizan cada 3 meses o cada 1500 km, lo que ocurra primero.\n";
        if ($serviceDates) {
            $mensaje .= "\n📅 Services programados:\n\n";
            foreach (array_values($serviceDates) as $index => $fecha) {
                $mensaje .= '- Service ' . ($index + 1) . ': ' . date('d/m/Y', strtotime($fecha)) . "\n\n";
            }
        }

        $mensaje .= "Esperamos que disfrutes tu moto eléctrica.\n\n";
        $mensaje .= "Ante cualquier consulta, podés comunicarte con nosotros por este medio.\n\n";
        $mensaje .= "Saludos,\n\n";
        $mensaje .= "Equipo LTecobike 🏍️";

        return $mensaje;
    }
}
