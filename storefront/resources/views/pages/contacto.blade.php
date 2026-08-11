@extends('layouts.storefront-public')
@section('title', 'Contacto | CommerceOps')
@section('description', 'Contactá a CommerceOps en Uruguay por WhatsApp o Instagram. Consultá motos eléctricas, disponibilidad, visitas coordinadas y postventa.')
@section('content')
@php
    $contact = config('storefront_content.contact');
    $whatsappConsultation = $contact['whatsapp_url'].'?text='.rawurlencode('Hola, quiero consultar por los modelos de CommerceOps.');
@endphp
<div class="contact-page">
    <section class="phase1-page-hero contact-page__hero">
        <div class="official-container contact-hero__grid">
            <div><p class="official-kicker">Contacto</p><h1>Hablemos</h1><p class="phase1-lead">Contanos qué necesitás y te orientamos sobre modelos, compra, visitas o postventa.</p></div>
            <a class="storefront-button storefront-button--primary" href="{{ $whatsappConsultation }}" target="_blank" rel="noopener noreferrer"><x-icon name="whatsapp" :size="20" /> Escribir por WhatsApp</a>
        </div>
    </section>

    <section class="contact-page__channels">
        <div class="official-container contact-channel-grid">
            <a class="contact-channel-card contact-channel-card--primary" href="{{ $whatsappConsultation }}" target="_blank" rel="noopener noreferrer"><x-icon name="whatsapp" :size="28" /><span class="contact-channel-card__label">WhatsApp</span><strong>{{ $contact['whatsapp_display'] }}</strong><p>Consultá modelos, precios, colores y disponibilidad.</p><span class="contact-channel-card__action">Iniciar conversación <x-icon name="arrow-right" :size="16" /></span></a>
            <a class="contact-channel-card" href="{{ $contact['instagram_url'] }}" target="_blank" rel="noopener noreferrer"><x-icon name="instagram" :size="28" /><span class="contact-channel-card__label">Instagram</span><strong>{{ $contact['instagram_label'] }}</strong><p>Conocé novedades, fotografías y contenido de CommerceOps.</p><span class="contact-channel-card__action">Ver Instagram <x-icon name="arrow-right" :size="16" /></span></a>
            <article class="contact-channel-card"><x-icon name="location" :size="28" /><span class="contact-channel-card__label">Ubicación</span><strong>{{ $contact['location'] }}</strong><p>La visita y el retiro se coordinan previamente.</p>@if(($contact['map_url']??'')!=='')<a class="contact-channel-card__action" href="{{ $contact['map_url'] }}" target="_blank" rel="noopener noreferrer">Abrir ubicación <x-icon name="arrow-right" :size="16" /></a>@else<a class="contact-channel-card__action" href="{{ route('visits.create') }}">Agendar visita <x-icon name="arrow-right" :size="16" /></a>@endif</article>
            @if (($contact['hours'] ?? '') !== '')<article class="contact-channel-card"><x-icon name="clock" :size="28" /><span class="contact-channel-card__label">Horarios</span><strong>{{ $contact['hours'] }}</strong><p>Coordiná antes de acercarte.</p></article>@endif
        </div>
    </section>

    <section class="contact-form-section">
        <div class="official-container contact-form-layout">
            <div><p class="official-kicker">Consulta guiada</p><h2>Prepará tu mensaje</h2><p>Completá los datos y te llevamos a WhatsApp con el mensaje listo. No guardamos esta consulta en la web.</p><ul class="contact-help-list"><li><x-icon name="check" :size="18" /> Indicá el modelo o tipo de uso.</li><li><x-icon name="check" :size="18" /> Consultá color, batería o disponibilidad.</li><li><x-icon name="check" :size="18" /> Para visitar el local, podés usar la agenda.</li></ul><div class="official-actions"><a class="storefront-button storefront-button--outline" href="{{ route('visits.create') }}"><x-icon name="clock" :size="18" /> Agendar visita</a><a class="storefront-button storefront-button--outline" href="{{ route('modelos.index') }}">Ver catálogo</a></div></div>
            <form method="POST" action="{{ route('contacto.store') }}" class="contact-form">@csrf
                @if($errors->any())<div class="storefront-flash storefront-flash--error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                <div class="contact-form__grid">
                    <label>Nombre<input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></label>
                    <label>Teléfono<input name="phone" value="{{ old('phone') }}" maxlength="30" inputmode="tel" autocomplete="tel" required></label>
                    <label class="is-wide">Correo (opcional)<input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email"></label>
                    <label class="is-wide">Motivo<select name="reason" required><option value="">Seleccioná una opción</option><option value="modelos" @selected(old('reason')==='modelos')>Consulta por modelos</option><option value="compra" @selected(old('reason')==='compra')>Consulta de compra</option><option value="visita" @selected(old('reason')==='visita')>Agendar una visita</option><option value="postventa" @selected(old('reason')==='postventa')>Postventa</option><option value="otro" @selected(old('reason')==='otro')>Otra consulta</option></select></label>
                    <label class="is-wide">Mensaje<textarea name="message" minlength="10" maxlength="1200" rows="6" required>{{ old('message') }}</textarea></label>
                    <label class="contact-honeypot" aria-hidden="true">Sitio web<input name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <button class="storefront-button storefront-button--primary" type="submit"><x-icon name="whatsapp" :size="20" /> Continuar por WhatsApp</button>
                <p class="storefront-muted">Al continuar se abrirá WhatsApp con estos datos. Revisá el mensaje antes de enviarlo.</p>
            </form>
        </div>
    </section>
</div>
@endsection
