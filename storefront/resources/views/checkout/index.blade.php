@extends('layouts.storefront-public')

@section('title', 'Comprar | ERP')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="customer-auth">
    <div class="customer-auth__container">
        <header class="customer-auth__heading">
            <p class="phase1-eyebrow">Reserva online</p>
            <h1>Confirmá tus datos</h1>
            <p>Confirmamos tu reserva y continuamos por WhatsApp para coordinar el pago y la visita.</p>
        </header>

        <form class="customer-auth__form checkout-form" method="POST" action="{{ route('checkout.store') }}">
            @csrf
            @if ($errors->any())
                <div class="customer-auth__errors" role="alert">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <fieldset class="checkout-form__section checkout-form__cart"><legend>Tu carrito</legend>
                @foreach ($cart->items as $item)
                    @php($live=$liveVariants->get($item->variant_id))
                    <div class="checkout-form__cart-row"><span><strong>{{ $item->quantity }} × {{ $item->model }}</strong><small>{{ $item->battery_ah ? $item->battery_ah.'Ah' : '' }} · {{ $item->color }}</small></span><strong>$ {{ number_format((float)($live['price']['gross']??$item->expected_gross)*$item->quantity,0,',','.') }} {{ $item->currency }}</strong></div>
                @endforeach<a class="checkout-form__edit" href="{{ route('cart.index') }}">Editar carrito</a>
            </fieldset>

            <fieldset class="checkout-form__section"><legend>Datos de facturación</legend>
                <div class="checkout-form__grid">
                    <label class="checkout-form__field checkout-form__field--wide">Tipo de cliente
                        <select name="customer_type" required data-customer-type aria-controls="business-fields consumer-fields">
                            <option value="consumer" @selected(old('customer_type',$profile?->customer_type)==='consumer')>Consumidor final</option>
                            <option value="business" @selected(old('customer_type',$profile?->customer_type)==='business')>Empresa / RUT</option>
                        </select>
                    </label>
                    <div class="checkout-form__nested checkout-form__field--wide" id="business-fields" data-customer-fields="business"><label>Razón social<input name="legal_name" value="{{ old('legal_name',$profile?->legal_name) }}"></label><label>RUT<input name="rut" value="{{ old('rut',$profile?->rut_encrypted) }}" inputmode="numeric"></label></div>
                    <label class="checkout-form__field">Teléfono<input name="phone" value="{{ old('phone',$profile?->phone_encrypted) }}" inputmode="tel" autocomplete="tel" required></label>
                    <div class="checkout-form__nested" id="consumer-fields" data-customer-fields="consumer"><label>Cédula<input name="cedula" value="{{ old('cedula',$profile?->cedula_encrypted) }}" inputmode="numeric"></label></div>
                    <label class="checkout-form__field checkout-form__field--wide">Dirección de facturación<input name="address_line1" value="{{ old('address_line1',$address?->line1_encrypted) }}" autocomplete="billing address-line1" required></label>
                    <label class="checkout-form__field">Departamento<input name="department" value="{{ old('department',$address?->department_encrypted) }}" autocomplete="billing address-level1" required></label>
                    <label class="checkout-form__field">Barrio<input name="city" value="{{ old('city',$address?->city_encrypted) }}" autocomplete="billing address-level2" required></label>
                    <label class="checkout-form__field checkout-form__field--wide">Complemento: edificio, piso o número de apto<input name="address_line2" value="{{ old('address_line2',$address?->line2_encrypted) }}" autocomplete="billing address-line2"></label>
                    <label class="checkout-form__field checkout-form__field--wide">Código postal<input name="postal_code" value="{{ old('postal_code',$address?->postal_code_encrypted) }}" autocomplete="billing postal-code"></label>
                </div>
            </fieldset>

            <fieldset class="checkout-form__section">
                <legend>Forma de pago</legend>
                <div class="checkout-form__payment-options">
                    <label class="checkout-form__payment-option"><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span><strong>Efectivo coordinado</strong><small>Confirmamos tu reserva; el pago y el comprobante final se coordinan por WhatsApp.</small></span></label>
                    @if ($paymentSimulatorEnabled)
                        <label class="checkout-form__payment-option"><input type="radio" name="payment_method" value="card" @checked(old('payment_method') === 'card')><span><strong>Tarjeta de prueba</strong><small>Disponible solamente en entornos de prueba autorizados.</small></span></label>
                    @endif
                </div>
            </fieldset>
            <label class="checkout-form__terms"><input type="checkbox" name="accept_terms" value="1" required> <span>Acepto los <a href="{{ route('terminos') }}" target="_blank" rel="noopener">términos de compra y reserva</a> y la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener">política de privacidad</a>.</span></label>
            <button class="phase1-button customer-auth__submit" type="submit">Confirmar</button>
        </form>
    </div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/checkout.js') }}" defer></script>@endpush
