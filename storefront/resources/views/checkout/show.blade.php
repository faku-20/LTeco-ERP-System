@extends('layouts.storefront-public')

@section('title', 'Pedido | ERP')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="customer-auth">
    @php
        $cancellable = in_array($order->status, ['awaiting_payment', 'pickup_coordination_pending'], true);
        $coordination = in_array($order->status, ['pickup_coordination_pending', 'paid', 'preparing', 'ready_for_pickup'], true);
        $isCashReservation = $order->payment_method === 'cash'
            && in_array($order->status, ['awaiting_payment', 'pickup_coordination_pending'], true);
        $labels = [
            'reservation_pending' => 'Confirmando reserva',
            'awaiting_payment' => $order->payment_method === 'cash' ? 'Reserva confirmada' : 'Esperando el pago',
            'pickup_coordination_pending' => 'Esperando coordinación de retiro',
            'cancelled' => 'Reserva cancelada',
            'expired' => 'Reserva vencida',
            'paid' => 'Pago confirmado',
            'preparing' => 'En preparación',
            'ready_for_pickup' => 'Listo para retirar',
            'delivered' => 'Entregado',
            'refund_pending' => 'Reembolso en proceso',
            'refunded' => 'Reembolsado',
            'payment_exception' => 'Necesitamos revisar el pago',
        ];
        $progress = match ($order->status) {
            'paid' => 2,
            'preparing' => 3,
            'ready_for_pickup' => 4,
            'delivered' => 5,
            default => 1,
        };
    @endphp
    <div class="customer-auth__container">
        <header class="customer-auth__heading">
            <p class="phase1-eyebrow">Pedido {{ $order->panel_order_number ?: $order->public_uuid }}</p>
            <h1>{{ $isCashReservation ? 'Confirmamos tu reserva' : ($cancellable ? 'Tu moto está reservada' : ($labels[$order->status] ?? 'Estado de tu pedido')) }}</h1>
            <p>
                {{ $isCashReservation
                    ? 'Para continuar con el pago y coordinar la visita, contactanos por WhatsApp al +598 92 000 086. El comprobante final se emite cuando se registra el pago.'
                    : ($coordination ? 'Coordiná previamente el día y la hora de retiro con nuestro equipo.' : 'Acá podés consultar el estado y los importes de tu pedido.') }}
            </p>
        </header>
        <div class="customer-auth__form">
            @if (session('status'))<p class="customer-auth__notice">{{ session('status') }}</p>@endif
            @error('checkout')<p class="customer-auth__error">{{ $message }}</p>@enderror
            @error('cancel')<p class="customer-auth__error">{{ $message }}</p>@enderror
            <dl class="customer-account__details">
                <div><dt>Estado</dt><dd>{{ $labels[$order->status] ?? $order->status }}</dd></div>
                <div><dt>Forma de pago</dt><dd>{{ $order->payment_method === 'cash' ? 'Efectivo coordinado' : 'Tarjeta' }}</dd></div>
                @foreach ($order->items as $item)
                    <div><dt>Unidad {{ $loop->iteration }}</dt><dd>{{ $item->model }}{{ $item->battery_ah ? ' · '.$item->battery_ah.'Ah' : '' }}{{ $item->color ? ' · '.$item->color : '' }}</dd></div>
                @endforeach
                <div><dt>Subtotal</dt><dd>$ {{ number_format((float) $order->subtotal, 0, ',', '.') }} {{ $order->currency }}</dd></div>
                <div><dt>Descuento</dt><dd>− $ {{ number_format((float) $order->discount, 0, ',', '.') }} {{ $order->currency }}</dd></div>
                <div><dt>Total</dt><dd><strong>$ {{ number_format((float) $order->total, 0, ',', '.') }} {{ $order->currency }}</strong></dd></div>
                @if ($isCashReservation)
                    <div><dt>Comprobante</dt><dd>Se emite al registrar el pago en efectivo.</dd></div>
                @endif
                <div><dt>Reserva válida hasta</dt><dd>{{ $order->expires_at?->timezone('America/Montevideo')->format('d/m/Y H:i') }}</dd></div>
            </dl>
            @if (!in_array($order->status, ['cancelled', 'expired', 'refund_pending', 'refunded', 'payment_exception'], true))
                <ol class="order-progress" aria-label="Progreso del pedido">
                    @foreach (['Reserva confirmada','Pago confirmado','En preparación','Listo para retirar','Entregado'] as $step)
                        <li @class(['is-complete' => $loop->iteration <= $progress, 'is-current' => $loop->iteration === $progress])><span>{{ $loop->iteration }}</span>{{ $step }}</li>
                    @endforeach
                </ol>
            @else
                <p class="customer-auth__notice">{{ $labels[$order->status] ?? $order->status }}. Si necesitás ayuda, contactanos indicando {{ $order->panel_order_number ?: $order->public_uuid }}.</p>
            @endif
            @if ($coordination)
                <a class="phase1-button customer-auth__submit" href="https://wa.me/{{ preg_replace('/\D+/', '', (string) config('storefront.whatsapp_number')) }}?text={{ rawurlencode('Hola, quiero coordinar el retiro de mi pedido '.$order->public_uuid) }}" target="_blank" rel="noopener noreferrer">Coordinar por WhatsApp</a>
            @endif
            @if ($cancellable)
                <form method="POST" action="{{ route('orders.cancel', ['order' => $order->public_uuid]) }}" onsubmit="return confirm('¿Querés cancelar esta reserva? La unidad volverá a quedar disponible.');">
                    @csrf
                    <button class="customer-auth__link" type="submit">Cancelar reserva</button>
                </form>
            @endif
            @if ($order->panel_sale_id || in_array($order->status, ['paid','preparing','ready_for_pickup','delivered'], true))
                <a class="btn-outline" href="{{ route('orders.receipt', ['order' => $order->public_uuid]) }}">Ver comprobante</a>
            @endif
        </div>
    </div>
</section>
@endsection
