@extends('layouts.storefront-public')
@section('title','Mi cuenta | CommerceOps')
@section('robots','noindex,nofollow')
@section('content')
@php
    $profile = $user->profile;
    $customerType = old('customer_type', $profile?->customer_type ?? 'consumer');
    $statusLabels = [
        'reservation_pending'=>'Confirmando reserva','pickup_coordination_pending'=>'Esperando coordinación',
        'awaiting_payment'=>'Esperando pago','paid'=>'Pago confirmado','preparing'=>'En preparación',
        'ready_for_pickup'=>'Listo para retirar','delivered'=>'Entregado','cancelled'=>'Cancelado',
        'expired'=>'Vencido','refund_pending'=>'Reembolso en proceso','refunded'=>'Reembolsado',
        'payment_exception'=>'Revisión necesaria',
    ];
@endphp
<section class="account-page">
    <div class="official-container">
        <header class="account-page__hero">
            <div>
                <p class="official-kicker">Mi cuenta</p>
                <h1>Hola, {{ $user->first_name }}</h1>
                <p>Administrá tus datos, direcciones, pedidos y seguridad desde un solo lugar.</p>
            </div>
            <a class="storefront-button storefront-button--outline" href="{{ route('modelos.index') }}"><x-icon name="store" :size="18" /> Ver modelos</a>
        </header>

        @if($errors->any())
            <div class="storefront-flash storefront-flash--error" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif

        <div class="account-layout">
            <aside class="account-sidebar" aria-label="Secciones de mi cuenta">
                <nav>
                    <a href="#resumen"><x-icon name="account" :size="18" /> Resumen</a>
                    <a href="#datos"><x-icon name="account" :size="18" /> Datos personales</a>
                    <a href="#direcciones"><x-icon name="location" :size="18" /> Direcciones</a>
                    <a href="#pedidos"><x-icon name="orders" :size="18" /> Pedidos</a>
                    <a href="#seguridad"><x-icon name="security" :size="18" /> Seguridad</a>
                    <a href="#privacidad"><x-icon name="privacy" :size="18" /> Privacidad</a>
                </nav>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="account-logout" type="submit"><x-icon name="account" :size="18" /> Cerrar sesión</button></form>
            </aside>

            <div class="account-content">
                <section class="account-card" id="resumen">
                    <div class="account-card__heading"><div><p class="official-kicker">Resumen</p><h2>Estado de tu cuenta</h2></div><x-icon name="check" :size="28" /></div>
                    <div class="account-summary-grid">
                        <article><span>Correo</span><strong>{{ $user->email }}</strong><small>{{ $user->hasVerifiedEmail() ? 'Verificado' : 'Pendiente de verificación' }}</small></article>
                        <article><span>Tipo de cliente</span><strong>{{ $customerType === 'business' ? 'Empresa' : 'Consumidor final' }}</strong><small>Podés cambiarlo en tus datos.</small></article>
                        <article><span>Direcciones</span><strong>{{ $user->addresses->count() }}</strong><small>Direcciones de facturación guardadas.</small></article>
                        <article><span>Pedidos</span><strong>{{ $orders->total() }}</strong><small>Historial asociado a tu cuenta.</small></article>
                    </div>
                </section>

                <section class="account-card" id="datos">
                    <div class="account-card__heading"><div><p class="official-kicker">Perfil</p><h2>Datos personales y de facturación</h2></div><x-icon name="account" :size="28" /></div>
                    <form method="POST" action="{{ route('account.update') }}" class="account-form" data-account-form>
                        @csrf @method('PATCH')
                        <div class="account-form__grid">
                            <label>Nombre<input name="first_name" value="{{ old('first_name',$user->first_name) }}" maxlength="100" autocomplete="given-name" required></label>
                            <label>Apellido<input name="last_name" value="{{ old('last_name',$user->last_name) }}" maxlength="100" autocomplete="family-name" required></label>
                            <label class="is-wide">Correo<input type="email" name="email" value="{{ old('email',$user->email) }}" maxlength="255" autocomplete="email" required><small>Si lo cambiás, tendrás que verificar el nuevo correo.</small></label>
                            <label>Tipo de cliente<select name="customer_type" data-account-customer-type aria-controls="account-consumer-fields account-business-fields"><option value="consumer" @selected($customerType==='consumer')>Consumidor final</option><option value="business" @selected($customerType==='business')>Empresa / RUT</option></select></label>
                            <label>Teléfono<input name="phone" value="{{ old('phone',$profile?->phone_encrypted) }}" maxlength="30" inputmode="tel" autocomplete="tel" required></label>
                        </div>
                        <div id="account-consumer-fields" class="account-conditional" data-account-customer-fields="consumer">
                            <label>Cédula<input name="cedula" inputmode="numeric" value="{{ old('cedula',$profile?->cedula_encrypted) }}" autocomplete="off"></label>
                        </div>
                        <div id="account-business-fields" class="account-conditional account-form__grid" data-account-customer-fields="business">
                            <label>Razón social<input name="legal_name" value="{{ old('legal_name',$profile?->legal_name) }}" maxlength="190" autocomplete="organization"></label>
                            <label>RUT<input name="rut" inputmode="numeric" value="{{ old('rut',$profile?->rut_encrypted) }}" autocomplete="off"></label>
                        </div>
                        <button class="storefront-button storefront-button--primary" type="submit"><x-icon name="check" :size="18" /> Guardar mis datos</button>
                    </form>
                </section>

                <section class="account-card" id="direcciones">
                    <div class="account-card__heading"><div><p class="official-kicker">Facturación</p><h2>Direcciones</h2></div><x-icon name="location" :size="28" /></div>
                    <div class="account-address-list">
                        @forelse ($user->addresses as $address)
                            <article class="account-address-card">
                                <form method="POST" action="{{ route('account.addresses.update',$address) }}" class="account-form">@csrf @method('PATCH')
                                    <div class="account-address-card__title"><strong>Dirección {{ $loop->iteration }}</strong>@if($address->is_primary)<span>Principal</span>@endif</div>
                                    <div class="account-form__grid">
                                        <label class="is-wide">Dirección<input name="line1" value="{{ $address->line1_encrypted }}" maxlength="190" required></label>
                                        <label>Departamento<input name="department" value="{{ $address->department_encrypted }}" maxlength="100" required></label>
                                        <label>Barrio<input name="city" value="{{ $address->city_encrypted }}" maxlength="100" required></label>
                                        <label class="is-wide">Complemento: edificio, piso o número de apto<input name="line2" value="{{ $address->line2_encrypted }}" maxlength="190"></label>
                                        <label>Código postal<input name="postal_code" value="{{ $address->postal_code_encrypted }}" maxlength="20"></label>
                                        <label class="account-check"><input type="checkbox" name="is_primary" value="1" @checked($address->is_primary)> Usar como principal</label>
                                    </div>
                                    <button class="storefront-button storefront-button--outline" type="submit">Actualizar dirección</button>
                                </form>
                                <form method="POST" action="{{ route('account.addresses.destroy',$address) }}" onsubmit="return confirm('¿Eliminar esta dirección?')">@csrf @method('DELETE')<button class="storefront-text-button" type="submit"><x-icon name="delete" :size="17" /> Eliminar</button></form>
                            </article>
                        @empty
                            <div class="account-empty"><x-icon name="location" :size="34" /><h3>No guardaste direcciones</h3><p>Agregá una para completar más rápido el checkout.</p></div>
                        @endforelse
                    </div>
                    <details class="account-disclosure"><summary><x-icon name="plus" :size="18" /> Agregar otra dirección</summary>
                        <form method="POST" action="{{ route('account.addresses.store') }}" class="account-form">@csrf
                            <div class="account-form__grid">
                                <label class="is-wide">Dirección<input name="line1" maxlength="190" required></label>
                                <label>Departamento<input name="department" maxlength="100" required></label>
                                <label>Barrio<input name="city" maxlength="100" required></label>
                                <label class="is-wide">Complemento: edificio, piso o número de apto<input name="line2" maxlength="190"></label>
                                <label>Código postal<input name="postal_code" maxlength="20"></label>
                                <label class="account-check"><input type="checkbox" name="is_primary" value="1"> Usar como principal</label>
                            </div>
                            <button class="storefront-button storefront-button--primary">Guardar dirección</button>
                        </form>
                    </details>
                </section>

                <section class="account-card" id="pedidos">
                    <div class="account-card__heading"><div><p class="official-kicker">Historial</p><h2>Mis pedidos</h2></div><x-icon name="orders" :size="28" /></div>
                    <div class="account-orders">
                        @forelse ($orders as $order)
                            <article class="account-order">
                                <div><strong>{{ $order->panel_order_number ?: 'Pedido en proceso' }}</strong><span>{{ $order->created_at->format('d/m/Y') }} · {{ $order->items->pluck('model')->unique()->join(', ') ?: 'Pedido en proceso' }}</span><small>{{ $statusLabels[$order->status]??$order->status }}</small></div>
                                <div><strong>$ {{ number_format((float)$order->total,0,',','.') }} {{ $order->currency }}</strong><a href="{{ route('orders.show',$order->public_uuid) }}">Ver pedido <x-icon name="arrow-right" :size="15" /></a>@if(in_array($order->status,['paid','preparing','ready_for_pickup','delivered'],true)||$order->panel_sale_id)<a href="{{ route('orders.receipt',$order->public_uuid) }}">Comprobante</a>@endif</div>
                            </article>
                        @empty
                            <div class="account-empty"><x-icon name="orders" :size="34" /><h3>Todavía no tenés pedidos</h3><p>Cuando hagas una reserva, vas a poder seguirla desde acá.</p><a class="storefront-button storefront-button--primary" href="{{ route('modelos.index') }}">Ver modelos</a></div>
                        @endforelse
                    </div>
                    {{ $orders->links() }}
                </section>

                <section class="account-card" id="seguridad">
                    <div class="account-card__heading"><div><p class="official-kicker">Acceso</p><h2>Seguridad</h2></div><x-icon name="security" :size="28" /></div>
                    <form method="POST" action="{{ route('account.password') }}" class="account-form">@csrf @method('PATCH')
                        <div class="account-form__grid">
                            <label class="is-wide">Contraseña actual<input type="password" name="current_password" autocomplete="current-password" required></label>
                            <label>Nueva contraseña<input type="password" name="password" autocomplete="new-password" required></label>
                            <label>Confirmar nueva contraseña<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
                        </div>
                        <button class="storefront-button storefront-button--primary"><x-icon name="lock" :size="18" /> Cambiar contraseña</button>
                    </form>
                </section>

                <section class="account-card" id="privacidad">
                    <div class="account-card__heading"><div><p class="official-kicker">Tus derechos</p><h2>Privacidad y datos</h2></div><x-icon name="privacy" :size="28" /></div>
                    <p>Podés obtener una copia de la información vinculada a tu cuenta o solicitar acceso, corrección, oposición o supresión. Se conservará únicamente la información exigida por obligaciones comerciales, fiscales, de garantía o seguridad.</p>
                    <div class="account-privacy-grid">
                        <form method="POST" action="{{ route('account.privacy.export') }}" class="account-form">@csrf<label>Contraseña actual<input type="password" name="current_password" autocomplete="current-password" required></label><button class="storefront-button storefront-button--outline">Descargar copia de mis datos</button></form>
                        <form method="POST" action="{{ route('account.privacy.store') }}" class="account-form">@csrf<label>Tipo de solicitud<select name="type" required><option value="access">Acceso</option><option value="correction">Corrección</option><option value="objection">Oposición</option><option value="suppression">Supresión</option></select></label><label>Detalle<textarea name="details" maxlength="2000" placeholder="Contanos qué necesitás"></textarea></label><button class="storefront-button storefront-button--outline">Enviar solicitud</button></form>
                    </div>
                    @if($user->privacyRequests()->exists())<div class="account-privacy-history"><h3>Solicitudes anteriores</h3>@foreach($user->privacyRequests()->latest()->limit(10)->get() as $privacy)<p><strong>{{ ucfirst($privacy->type) }}</strong> · {{ $privacy->status }} · {{ $privacy->created_at->format('d/m/Y') }}@if($privacy->resolution_manifest['response']??null)<br>{{ $privacy->resolution_manifest['response'] }}@endif</p>@endforeach</div>@endif
                </section>
            </div>
        </div>
    </div>
</section>
@endsection
@push('scripts')
    <script src="{{ asset('js/account.js') }}?v={{ filemtime(public_path('js/account.js')) }}" defer></script>
@endpush
