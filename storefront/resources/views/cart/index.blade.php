@extends('layouts.storefront-public')
@section('title','Carrito | ERP')
@section('robots','noindex,nofollow')
@section('content')
<section class="storefront-cart-page">
    <div class="official-container">
        <header class="storefront-page-hero"><p class="official-kicker">Tienda online</p><h1>Tu carrito</h1><p>El precio y el stock se vuelven a comprobar antes de reservar.</p></header>
        @if($errors->any())<div class="storefront-flash storefront-flash--error" role="alert"><x-icon name="alert" :size="20" /><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
        @if($guest && $cart && $cart->items->isNotEmpty())
            <div class="storefront-cart-guest"><x-icon name="account" :size="22" /><div><strong>Tu carrito está guardado en este navegador</strong><p>Podés seguir comprando sin cuenta. Para finalizar, te pediremos registrarte o iniciar sesión y conservaremos los productos.</p></div></div>
        @endif
        @if($cart && $cart->items->isNotEmpty())
            <div class="storefront-cart-layout">
                <div class="storefront-cart-items">
                    @foreach($cart->items as $item)
                        <article class="storefront-cart-item">
                            <div class="storefront-cart-item__icon"><x-icon name="power" :size="28" /></div>
                            <div class="storefront-cart-item__info"><p class="official-kicker">Variante seleccionada</p><h2>{{ $item->model }}</h2><p><x-icon name="battery" :size="16" /> {{ $item->battery_ah ? $item->battery_ah.'Ah' : 'Batería a confirmar' }} <span aria-hidden="true">·</span> <x-icon name="color" :size="16" /> {{ $item->color ?: 'Color a confirmar' }}</p><strong>$ {{ number_format((float)$item->expected_gross,0,',','.') }} {{ $item->currency }} por unidad</strong></div>
                            <div class="storefront-cart-item__actions">
                                <form method="POST" action="{{ route('cart.update',$item) }}">@csrf @method('PATCH')<label>Cantidad<input type="number" name="quantity" min="1" max="{{ config('storefront_cart.max_quantity',10) }}" value="{{ $item->quantity }}" inputmode="numeric"></label><button class="storefront-button storefront-button--outline" type="submit">Actualizar</button></form>
                                <p class="storefront-cart-item__subtotal"><span>Subtotal</span><strong>$ {{ number_format((float)$item->expected_gross*$item->quantity,0,',','.') }} {{ $item->currency }}</strong></p>
                                <form method="POST" action="{{ route('cart.destroy',$item) }}">@csrf @method('DELETE')<button class="storefront-text-button" type="submit"><x-icon name="delete" :size="17" /> Quitar</button></form>
                            </div>
                        </article>
                    @endforeach
                    <a class="storefront-button storefront-button--outline storefront-cart-continue" href="{{ route('modelos.index') }}"><x-icon name="arrow-left" :size="18" /> Seguir viendo modelos</a>
                </div>
                <aside class="storefront-cart-summary">
                    <p class="official-kicker">Resumen</p><h2>Total del carrito</h2>
                    <p><span>Unidades</span><strong>{{ $cart->items->sum('quantity') }}</strong></p>
                    <p><span>Subtotal</span><strong>$ {{ number_format($cart->items->sum(fn($item)=>(float)$item->expected_gross*$item->quantity),0,',','.') }} UYU</strong></p>
                    <a class="storefront-button storefront-button--primary" href="{{ route('checkout.index') }}"><x-icon name="cart" :size="18" /> Continuar con la compra</a>
                    <p class="storefront-muted">Precio y stock se validan nuevamente. Retiro o visita con coordinación previa; no realizamos envíos por el momento.</p>
                </aside>
            </div>
        @else
            <div class="storefront-empty"><x-icon name="cart" :size="42" /><h2>Tu carrito está vacío</h2><p>Elegí un modelo, color y batería para comenzar.</p><a class="storefront-button storefront-button--primary" href="{{ route('modelos.index') }}">Ver modelos</a></div>
        @endif
    </div>
</section>
@endsection
