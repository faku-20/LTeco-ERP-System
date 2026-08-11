@php
    $contact = config('storefront_content.contact');
    $brand = (string) config('storefront_content.brand', config('app.name', 'CommerceOps'));
    $tagline = (string) config('storefront_content.tagline', 'Gestión comercial integral');
@endphp
<footer class="official-footer">
    <div class="official-container official-footer__grid">
        <section class="official-footer__brand">
            <x-storefront.logo :width="132" :height="54" loading="lazy" />
            <p>{{ $tagline }}</p>
        </section>
        <nav aria-label="Explorar"><h2>Explorar</h2><a href="{{ route('modelos.index') }}">Modelos</a><a href="{{ route('savings.index') }}">Calculadora de ahorro</a><a href="{{ route('visits.create') }}">Agendar visita</a><a href="{{ route('contacto') }}">Contacto</a></nav>
        <section><h2>Contacto</h2>@if(($contact['whatsapp_number']??'')!=='')<a href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"><x-icon name="whatsapp" :size="18" /> WhatsApp {{ $contact['whatsapp_display'] }}</a>@endif @if(($contact['instagram_url']??'#')!=='#')<a href="{{ $contact['instagram_url'] }}" target="_blank" rel="noopener noreferrer"><x-icon name="instagram" :size="18" /> Instagram {{ $contact['instagram_label'] }}</a>@endif @if(($contact['email']??'')!=='')<a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>@endif<p><x-icon name="location" :size="18" /> {{ $contact['location'] }}</p></section>
        <nav aria-label="Información"><h2>Información</h2><a href="{{ route('privacidad') }}">Privacidad</a><a href="{{ route('terminos') }}">Términos de compra y reserva</a><a href="{{ route('nosotros') }}">Nosotros</a></nav>
    </div>
    <div class="official-container official-footer__bottom"><p>© {{ now()->year }} {{ $brand }}.</p><p>Entrega, retiro o visita según configuración comercial.</p></div>
</footer>
