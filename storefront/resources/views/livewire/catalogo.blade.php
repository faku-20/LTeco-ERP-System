@php
    $contact = config('storefront_content.contact');
    $whatsappUrl = $contact['whatsapp_url'] ?? 'https://wa.me/59892000086';
@endphp

<div class="official-home legacy-home">
    <section class="official-hero legacy-home-hero">
        <img
            class="official-hero__image"
            src="{{ asset('images/legacy-home/hero-principal.webp') }}"
            alt="Bici-moto eléctrica ERP"
            width="1672"
            height="941"
            fetchpriority="high"
        >
        <div class="official-container official-hero__content">
            <h1>Bici-motos y scooters eléctricos.</h1>
            <p>
                Descubrí opciones de nuevos diseños y modelos.
                <br>Practicidad y características pensadas para la movilidad urbana.
                <br>Escribinos y te asesoramos sobre cada modelo.
            </p>
            <div class="official-actions">
                <a class="official-button" href="{{ route('modelos.index') }}">Ver modelos</a>
                <a
                    class="official-button official-button--outline"
                    href="{{ $whatsappUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >Consultar por WhatsApp</a>
            </div>
        </div>
    </section>

    <section class="official-section legacy-home-arrivals">
        <div class="official-container">
            <header class="official-section__heading legacy-home-section-heading">
                <div>
                    <p class="official-kicker">Ya disponibles</p>
                    <h2>Últimos ingresos</h2>
                    <p>Conocé los modelos que acaban de ingresar y consultanos por disponibilidad y colores.</p>
                </div>
                <a class="official-text-link" href="{{ route('modelos.index') }}">Ver catálogo completo</a>
            </header>

            <div class="legacy-home-arrivals__grid">
                <article class="legacy-home-arrival-card">
                    <div class="legacy-home-gallery" data-home-gallery>
                        <div class="legacy-home-gallery__track" data-home-gallery-track>
                            <img class="legacy-home-gallery__slide" src="{{ asset('images/legacy-home/sl-500-celeste.webp') }}" alt="SL 500W celeste" loading="lazy" decoding="async">
                            <img class="legacy-home-gallery__slide" src="{{ asset('images/legacy-home/sl-500-beige.webp') }}" alt="SL 500W beige" loading="lazy" decoding="async">
                            <img class="legacy-home-gallery__slide" src="{{ asset('images/legacy-home/sl-500-detalle.webp') }}" alt="SL 500W detalle" loading="lazy" decoding="async">
                        </div>
                        <button class="legacy-home-gallery__button legacy-home-gallery__button--prev" type="button" data-home-gallery-prev aria-label="Foto anterior del modelo SL 500W">‹</button>
                        <button class="legacy-home-gallery__button legacy-home-gallery__button--next" type="button" data-home-gallery-next aria-label="Foto siguiente del modelo SL 500W">›</button>
                        <div class="legacy-home-gallery__dots" data-home-gallery-dots aria-label="Indicadores de fotos del modelo SL 500W"></div>
                        <span class="legacy-home-arrival-badge">Disponible</span>
                    </div>
                    <div class="legacy-home-arrival-card__body">
                        <p class="legacy-home-tag">500W · 20Ah</p>
                        <h3>SL 500W</h3>
                        <p>Modelo urbano con motor de 500W, batería de 20Ah, doble faro delantero, canasto frontal y baúl trasero.</p>
                        <a
                            class="official-text-link"
                            href="{{ route('modelos.show', ['slug' => 'sl-500-20ah-beige-v0051']) }}"
                        >Ver y reservar SL 500W</a>
                    </div>
                </article>

                <article class="legacy-home-arrival-card legacy-home-arrival-card--featured">
                    <div class="legacy-home-gallery" data-home-gallery>
                        <div class="legacy-home-gallery__track" data-home-gallery-track>
                            <img class="legacy-home-gallery__slide" src="{{ asset('images/legacy-home/q8-500-azul-frente.webp') }}" alt="Q8 500W azul frente" loading="lazy" decoding="async">
                            <img class="legacy-home-gallery__slide" src="{{ asset('images/legacy-home/q8-500-azul-lateral.webp') }}" alt="Q8 500W azul lateral" loading="lazy" decoding="async">
                            <img class="legacy-home-gallery__slide" src="{{ asset('images/legacy-home/q8-500-azul.webp') }}" alt="Q8 500W azul" loading="lazy" decoding="async">
                        </div>
                        <button class="legacy-home-gallery__button legacy-home-gallery__button--prev" type="button" data-home-gallery-prev aria-label="Foto anterior de la Q8 500W">‹</button>
                        <button class="legacy-home-gallery__button legacy-home-gallery__button--next" type="button" data-home-gallery-next aria-label="Foto siguiente de la Q8 500W">›</button>
                        <div class="legacy-home-gallery__dots" data-home-gallery-dots aria-label="Indicadores de fotos de la Q8 500W"></div>
                        <span class="legacy-home-arrival-badge">Disponible</span>
                    </div>
                    <div class="legacy-home-arrival-card__body">
                        <p class="legacy-home-tag">500W · 12Ah o 20Ah</p>
                        <h3>Q8 500W</h3>
                        <p>Modelo Q8 de 500W disponible con batería de 12Ah o 20Ah, diseño compacto y respaldo para acompañante.</p>
                        <a
                            class="official-text-link"
                            href="{{ route('modelos.show', ['slug' => 'q8-500-w-v0230']) }}"
                        >Ver y reservar Q8 500W</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="legacy-home-patente">
        <div class="official-container">
            <p>MOVILIDAD ELÉCTRICA MÁS ECONÓMICA</p>
            <h2>Olvidate de la patente</h2>
            <strong>Costo cero · No paga patente</strong>
        </div>
    </section>

    <section class="official-section legacy-home-split">
        <div class="official-container legacy-home-split__grid">
            <div>
                <p class="official-kicker">Por qué elegir ERP</p>
                <h2>Movilidad eléctrica con identidad propia</h2>
                <p>
                    Nuestros modelos combinan diseño, practicidad y una propuesta pensada para quienes buscan una nueva forma de moverse.
                    Atención personalizada de forma directa para que puedas consultar detalles y elegir tu mejor opción de movilidad.
                </p>
                <div class="legacy-home-checklist">
                    <span>✔ Motor de 350W y 500W según el modelo</span>
                    <span>✔ Batería de plomo-ácido de 48V, con 12Ah o 20Ah según el modelo</span>
                    <span>✔ Freno delantero de disco o tambor y freno trasero de tambor</span>
                    <span>✔ Atención directa por WhatsApp e Instagram</span>
                </div>
                <div class="official-actions">
                    <a class="official-button" href="{{ route('modelos.index') }}">Ver modelos</a>
                    <a class="official-button official-button--outline legacy-home-dark-outline" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">Escribinos por WhatsApp</a>
                </div>
            </div>
            <img
                class="legacy-home-split__image"
                src="{{ asset('images/legacy-home/q8-500-beige-rojo.webp') }}"
                alt="Q8 500W beige ERP"
                loading="lazy"
                decoding="async"
            >
        </div>
    </section>

    <section class="legacy-home-info">
        <div class="official-container">
            <p class="official-kicker">Información útil</p>
            <h2>Lo que te ofrecemos</h2>
            <div class="legacy-home-info__grid">
                <article><h3>Motor y velocidad</h3><p>Según el modelo<br>Motor 350W o 500W<br>3 velocidades 35 a 50 km/h.</p></article>
                <article><h3>Batería</h3><p>Plomo-ácido<br>Variable<br>48V · 12Ah<br>48V · 20Ah</p></article>
                <article><h3>Sistemas de frenos</h3><p>Freno delantero de disco<br>Freno delantero de tambor<br><br>Freno trasero de tambor</p></article>
                <article><h3>Seguridad / Alarma</h3><p>3 formas de arranque<br>Llave tradicional<br>Llave a distancia<br>Llave por contacto NFC<br>Alarma</p></article>
            </div>
        </div>
    </section>

    <section class="legacy-home-final-cta">
        <div class="official-container official-cta__inner">
            <div>
                <p class="official-kicker">Consulta directa</p>
                <h2>¿Querés más información de un modelo?</h2>
                <p>Escribinos por WhatsApp o seguinos en Instagram. Hacé tus consultas y conocé mejor cada opción.</p>
            </div>
            <div class="official-actions">
                <a class="official-button official-button--light" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">Hablar por WhatsApp</a>
                <a class="official-button official-button--outline" href="{{ $contact['instagram_url'] ?? 'https://instagram.com/ltecobike' }}" target="_blank" rel="noopener noreferrer">Ver Instagram</a>
            </div>
        </div>
    </section>
</div>
