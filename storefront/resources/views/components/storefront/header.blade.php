@php
    $cartCount = app(\App\Services\CartManager::class)->count(request());
    $brand = (string) config('storefront_content.brand', config('app.name', 'CommerceOps'));
    $tagline = (string) config('storefront_content.tagline', 'Gestión comercial');
@endphp
<header class="official-header">
    <div class="official-container official-header__inner">
        <a class="official-brand" href="{{ route('catalogo') }}" aria-label="{{ $brand }} — Inicio">
            <x-storefront.logo :alt="$brand" :width="56" :height="28" />
            <span>
                <strong>{{ $brand }}</strong>
                <small>{{ $tagline }}</small>
            </span>
        </a>
        <nav class="official-nav" aria-label="Navegación principal">
            <a href="{{ route('catalogo') }}" @class(['is-active'=>request()->routeIs('catalogo')])>Inicio</a>
            <a href="{{ route('modelos.index') }}" @class(['is-active'=>request()->routeIs('modelos.*')])>Modelos</a>
            <a href="{{ route('savings.index') }}" @class(['official-nav-icon-link','is-active'=>request()->routeIs('savings.*')])><x-icon name="calculator" :size="18" /> Calculadora de ahorro</a>
            <a href="{{ route('nosotros') }}" @class(['is-active'=>request()->routeIs('nosotros')])>Nosotros</a>
            <a href="{{ route('contacto') }}" @class(['is-active'=>request()->routeIs('contacto')])>Contacto</a>
            <a href="{{ route('cart.index') }}" @class(['official-nav-icon-link','official-cart-link','is-active'=>request()->routeIs('cart.*','checkout.*')])>
                <x-icon name="cart" :size="18" /> Carrito <span>{{ $cartCount }}</span>
            </a>
            @if(config('storefront_auth.accounts_enabled'))
                @auth
                    <a href="{{ route('account.dashboard') }}" @class(['official-nav-icon-link','is-active'=>request()->routeIs('account.*')])><x-icon name="account" :size="18" /> Mi cuenta</a>
                @else
                    <a href="{{ route('login') }}" @class(['official-nav-icon-link','is-active'=>request()->routeIs('login')])><x-icon name="account" :size="18" /> Ingresar</a>
                @endauth
            @endif
        </nav>
        <details class="official-mobile-menu">
            <summary><x-icon name="menu" :size="20" /> Menú</summary>
            <nav aria-label="Navegación móvil">
                <a href="{{ route('catalogo') }}">Inicio</a>
                <a href="{{ route('modelos.index') }}">Modelos</a>
                <a href="{{ route('savings.index') }}"><x-icon name="calculator" :size="18" /> Calculadora de ahorro</a>
                <a href="{{ route('nosotros') }}">Nosotros</a>
                <a href="{{ route('contacto') }}">Contacto</a>
                <a href="{{ route('cart.index') }}"><x-icon name="cart" :size="18" /> Carrito ({{ $cartCount }})</a>
                @if(config('storefront_auth.accounts_enabled'))
                    @auth<a href="{{ route('account.dashboard') }}"><x-icon name="account" :size="18" /> Mi cuenta</a>
                    @else<a href="{{ route('login') }}"><x-icon name="account" :size="18" /> Ingresar</a>@endauth
                @endif
            </nav>
        </details>
    </div>
</header>
