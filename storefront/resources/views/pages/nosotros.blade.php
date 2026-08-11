@extends('layouts.storefront-public')

@section('title', 'Nosotros | CommerceOps')

@section(
    'description',
    'Conocé CommerceOps, venta y servicio de bici-motos y scooters eléctricos en Montevideo, Uruguay.'
)

@section('content')
    @php
        $contact = config('storefront_content.contact');
    @endphp

    <div class="institutional-page">
        <section
            class="
                phase1-page-hero
                institutional-hero
            "
        >
            <div class="phase1-container">
                <p class="phase1-eyebrow">
                    CommerceOps Uruguay
                </p>

                <h1>Sobre CommerceOps</h1>

                <p class="phase1-lead">
                    Una propuesta enfocada en movilidad eléctrica
                    con estilo, personalidad y atención directa
                    en Uruguay.
                </p>
            </div>
        </section>

        <section class="institutional-story">
            <div
                class="
                    phase1-container
                    institutional-story__grid
                "
            >
                <div>
                    <p class="phase1-eyebrow">
                        Nuestra misión
                    </p>

                    <h2>
                        Movilidad eléctrica
                        con identidad propia
                    </h2>
                </div>

                <div class="institutional-story__copy">
                    <p>
                        Desde CommerceOps apostamos a la movilidad
                        eléctrica como un medio de apoyo a nuestro
                        ecosistema en la no emisión de gases.
                    </p>

                    <p>
                        El cuidado del medio ambiente es uno de
                        nuestros pilares fundamentales, junto con
                        diseños que buscan diferenciarse visualmente
                        y transmitir estilo.
                    </p>

                    <p>
                        Atendemos con coordinación previa en zona
                        Belvedere y acompañamos la compra con
                        asesoramiento, repuestos, garantía y
                        orientación de postventa.
                    </p>

                    <a
                        class="multipage-link"
                        href="{{ route('modelos.index') }}"
                    >
                        Conocer los modelos
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="institutional-values">
            <div class="phase1-container">
                <header class="institutional-section-heading">
                    <p class="phase1-eyebrow">
                        Cómo trabajamos
                    </p>

                    <h2>
                        Una experiencia simple
                        desde la primera consulta
                    </h2>
                </header>

                <div class="institutional-values__grid">
                    <article>
                        <span>01</span>

                        <h3>Modelos para la ciudad</h3>

                        <p>
                            Alternativas con identidad, diferentes
                            potencias, configuraciones y estilos.
                        </p>
                    </article>

                    <article>
                        <span>02</span>

                        <h3>Atención cercana</h3>

                        <p>
                            Contacto directo para responder
                            dudas, consultas y disponibilidad antes
                            de coordinar la compra.
                        </p>
                    </article>

                    <article>
                        <span>03</span>

                        <h3>Repuestos y respaldo</h3>

                        <p>
                            Buscamos acompañar la experiencia con
                            disponibilidad de repuestos y soporte
                            técnico.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="institutional-contact-band">
            <div
                class="
                    phase1-container
                    institutional-contact-band__inner
                "
            >
                <div>
                    <p class="phase1-eyebrow">
                        Contacto directo
                    </p>

                    <h2>Consultá disponibilidad ahora</h2>

                    <p>
                        Te respondemos rápido por WhatsApp y te
                        orientamos para elegir el modelo que mejor
                        se adapte a tu uso diario.
                    </p>
                </div>

                <a
                    class="
                        multipage-button
                        multipage-button--light
                    "
                    href="{{ $contact['whatsapp_url'] }}?text={{
                        rawurlencode(
                            'Hola, quiero conocer los modelos '
                            . 'disponibles de CommerceOps.'
                        )
                    }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Hablar por WhatsApp
                </a>
            </div>
        </section>
    </div>
@endsection
