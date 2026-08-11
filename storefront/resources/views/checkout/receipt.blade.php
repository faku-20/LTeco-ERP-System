@extends('layouts.storefront-receipt')

@section('title', 'Comprobante de compra | CommerceOps')

@section('content')
@php
    $billing = is_array($order->billing_snapshot_encrypted) ? $order->billing_snapshot_encrypted : [];
    $address = is_array($billing['address'] ?? null) ? $billing['address'] : [];
    $customerType = ($billing['customer_type'] ?? 'consumer') === 'business' ? 'Empresa / RUT' : 'Consumidor final';
    $paymentLabel = $order->payment_method === 'cash' ? 'Efectivo' : 'Tarjeta';
    $receiptNumber = $order->panel_order_number ?: ('WEB-'.strtoupper(substr(str_replace('-', '', $order->public_uuid), 0, 20)));
    $receiptCode = str_starts_with($receiptNumber, 'WEB-') ? $receiptNumber : 'WEB-'.$receiptNumber;
    $receiptCodeDisplay = preg_replace('/^(WEB-[A-Z0-9]{8})([A-Z0-9]+)/', '$1 $2', $receiptCode);
    $date = ($order->paid_at ?? $order->created_at)->timezone('America/Montevideo')->format('d/m/Y H:i');
    $statusLabel = in_array($order->status, ['paid', 'preparing', 'ready_for_pickup', 'delivered'], true) ? 'Confirmada' : 'Pendiente';
    $clientName = trim((string) ($billing['name'] ?? $order->user?->full_name ?? 'Cliente'));
    $clientEmail = (string) ($billing['email'] ?? $order->user?->email ?? '');
    $clientPhone = (string) ($billing['phone'] ?? '-');
    $clientCedula = (string) ($billing['cedula'] ?? '-');
    $clientRut = (string) ($billing['rut'] ?? '-');
    $clientAddress = trim(implode(', ', array_filter([
        $address['line1'] ?? null,
        $address['line2'] ?? null,
        $address['city'] ?? null,
        $address['department'] ?? null,
    ]))) ?: '-';
    $money = static fn (float $amount): string => '$ '.number_format($amount, 2, ',', '.');
@endphp
<main class="purchase-receipt">
    <section class="purchase-receipt__top">
        <div class="purchase-receipt__brand">
            <p>Comprobante de compra</p>
            <h1>CommerceOps</h1>
            <span>+59892000086 contacto@example.com</span>
        </div>
        <aside class="purchase-receipt__summary-card">
            <h2>Comprobante {{ $receiptCodeDisplay }}</h2>
            <p>Fecha: {{ $date }}</p>
            <p>Moneda: {{ $order->currency }}</p>
            <strong>{{ $statusLabel }}</strong>
        </aside>
    </section>

    <section class="purchase-receipt__info-grid">
        <article class="purchase-receipt__box">
            <h2>Cliente</h2>
            <p><strong>{{ $clientName }}</strong></p>
            <p>Tipo: {{ $customerType }}</p>
            <p>Teléfono: {{ $clientPhone ?: '-' }}</p>
            <p>Correo: {{ $clientEmail ?: '-' }}</p>
            <p>Cédula: {{ $clientCedula ?: '-' }}</p>
            <p>RUT: {{ $clientRut ?: '-' }}</p>
            <p>Dirección: {{ $clientAddress }}</p>
        </article>
        <article class="purchase-receipt__box">
            <h2>Datos de venta</h2>
            <p>Método de pago: <strong>{{ $paymentLabel }}</strong></p>
            <p>Tipo de cliente: <strong>{{ ($billing['customer_type'] ?? 'consumer') === 'business' ? 'Empresa' : 'Final' }}</strong></p>
        </article>
    </section>

    <section class="purchase-receipt__products purchase-receipt__box">
        <h2>Detalle de productos</h2>
        <div class="purchase-receipt__table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Detalle</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        @php($snapshot = is_array($item->vehicle_snapshot) ? $item->vehicle_snapshot : [])
                        <tr>
                            <td>
                                <strong>{{ $item->model }}{{ $item->battery_ah ? '-'.$item->battery_ah.'Ah' : '' }}{{ $item->color ? '-'.$item->color : '' }}</strong>
                                <span>Moto</span>
                            </td>
                            <td>
                                <span>Modelo: {{ $item->model }}</span>
                                @if(!empty($snapshot['engine_number']))
                                    <span>Motor: {{ $snapshot['engine_number'] }}</span>
                                @endif
                                <span>Color: {{ $item->color ?: '-' }}</span>
                            </td>
                            <td>1</td>
                            <td>{{ $money((float) $item->gross) }}</td>
                            <td>{{ $money((float) $item->gross) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="purchase-receipt__totals">
        <dl>
            <div><dt>Total</dt><dd>{{ $money((float) $order->total) }}</dd></div>
            <div><dt>Monto pagado</dt><dd>{{ $money(in_array($order->status, ['paid', 'preparing', 'ready_for_pickup', 'delivered'], true) ? (float) $order->total : 0) }}</dd></div>
            <div><dt>Saldo pendiente</dt><dd>{{ $money(in_array($order->status, ['paid', 'preparing', 'ready_for_pickup', 'delivered'], true) ? 0 : (float) $order->total) }}</dd></div>
            <div><dt>Estado</dt><dd>{{ $statusLabel }}</dd></div>
        </dl>
    </section>

    <p class="purchase-receipt__note"><strong>Observaciones:</strong> Compra registrada correctamente.</p>
    <p class="purchase-receipt__note">Venta de motos eléctricas y repuestos</p>

    <div class="purchase-receipt__actions">
        <button type="button" onclick="window.print()">Imprimir o guardar como PDF</button>
    </div>
</main>
@endsection
