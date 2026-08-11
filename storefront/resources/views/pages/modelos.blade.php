@extends('layouts.storefront-public')

@section('title', 'Modelos de motos eléctricas en Uruguay | ERP')
@section('description', 'Compará motos eléctricas ERP disponibles en Uruguay. Elegí modelo, color y batería, agregá al carrito o reservá online con retiro coordinado.')

@section('content')
    @php
        $motos = collect($motos ?? []);
        $latest = $motos->take(2);
        $formatPrice = fn (float $price): string => '$ '.number_format($price, 0, ',', '.');
        $imageUrl = fn (string $image): string => str_starts_with($image, 'http://') || str_starts_with($image, 'https://') ? $image : asset($image);
        $faqs = [
            [
                'question' => '¿Para qué tipo de uso son estas motos eléctricas?',
                'answer' => 'Están pensadas principalmente para uso urbano: traslados diarios para un pasajero, trabajo o movilidad dentro de la ciudad, de forma económica y práctica.',
            ],
            [
                'question' => '¿Qué autonomía tienen?',
                'answer' => 'La autonomía depende del modelo, el peso y el uso, pero están pensadas para cubrir recorridos urbanos diarios sin problema, con un recorrido de hasta 50 km de autonomía.',
            ],
            [
                'question' => '¿Cuánto demora la carga?',
                'answer' => 'El tiempo de carga indicado es de aproximadamente 5 a 6 horas, dependiendo del modelo y del uso previo de la batería.',
            ],
            [
                'question' => '¿Qué tipo de batería utilizan?',
                'answer' => 'Según el modelo, trabajan con batería de plomo-ácido de 48V y capacidades de 12Ah, 15Ah o 20Ah.',
            ],
            [
                'question' => '¿Se pueden usar en la vía pública?',
                'answer' => 'Pueden utilizarse como cualquier vehículo, cumpliendo con la normativa vigente: empadronamiento, uso de casco y libreta G1 o G2.',
            ],
            [
                'question' => '¿Requieren empadronamiento y seguro?',
                'answer' => 'Sí. Requieren empadronamiento y seguro SOA. Podemos orientarte sobre esos trámites, pero no están incluidos en el precio publicado. No pagan patente.',
            ],
            [
                'question' => '¿Qué garantía tienen?',
                'answer' => 'Tienen garantía ERP de 1 año o 6000 km, lo que ocurra primero, condicionada a realizar los 4 mantenimientos correspondientes durante el primer año.',
            ],
            [
                'question' => '¿Cómo consulto disponibilidad o modelos?',
                'answer' => 'Podés escribirnos por WhatsApp o Instagram y te respondemos a la brevedad con disponibilidad y modelos.',
            ],
            [
                'question' => '¿Me pueden asesorar para elegir?',
                'answer' => 'Claro. Te ayudamos a elegir el modelo ideal según tu uso, presupuesto y necesidades.',
            ],
        ];
        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ])->values()->all(),
        ];
    @endphp

    @push('head')
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @endpush

    <div class="catalog-page storefront-models-page">
        <section class="storefront-page-hero storefront-models-hero">
            <div class="official-container">
                <p class="official-kicker">Modelos eléctricos</p>
                <h1>Elegí tu modelo ideal</h1>
                <p>Modelos diseñados para moverte con estilo, ahorro y comodidad en la ciudad. Compará opciones, elegí color y batería, y reservá online.</p>
            </div>
        </section>

        <section class="official-section storefront-models-editorial" aria-labelledby="catalog-title">
            <div class="official-container">
                <header class="official-section__heading storefront-models-heading">
                    <div>
                        <p class="official-kicker">Catálogo actual</p>
                        <h2 id="catalog-title">Conocé nuestras opciones</h2>
                        <p>Compará potencia, comodidad y características para elegir el modelo que mejor se adapte a tu necesidad.</p>
                    </div>
                </header>

                @if ($motos->isNotEmpty())
                    <div class="storefront-models-list">
                        @foreach ($motos as $moto)
                            @php
                                $variantRows = collect($moto->variantes ?? [])
                                    ->filter(fn ($variant) => is_array($variant) && isset($variant['variant_id']))
                                    ->values();
                                $firstVariant = $variantRows->first(fn (array $variant): bool => (int) ($variant['availability']['quantity'] ?? 0) > 0)
                                    ?? $variantRows->first();
                                $slides = collect();

                                foreach ($variantRows as $variant) {
                                    foreach (($variant['gallery'] ?? []) as $image) {
                                        $url = is_array($image) ? (string) ($image['url'] ?? '') : (string) $image;
                                        if ($url !== '') {
                                            $slides->push([
                                                'url' => $url,
                                                'color' => trim((string) ($variant['color'] ?? '')),
                                                'battery_ah' => $variant['battery_ah'] ?? null,
                                            ]);
                                        }
                                    }
                                }

                                if ($slides->isEmpty()) {
                                    $slides = collect($moto->imagenes ?? [])
                                        ->filter()
                                        ->map(fn ($image): array => ['url' => (string) $image, 'color' => '', 'battery_ah' => null]);
                                }

                                $slides = $slides->unique('url')->values();
                                $colors = $variantRows->pluck('color')->filter()->unique()->values();
                                $batteries = $variantRows->pluck('battery_ah')
                                    ->filter(fn ($value) => is_numeric($value))
                                    ->map(fn ($value) => (int) $value)
                                    ->unique()
                                    ->sort()
                                    ->values();
                                $initialPrice = (float) ($firstVariant['price']['gross'] ?? $moto->precio ?? 0);
                                $initialCurrency = (string) ($firstVariant['price']['currency'] ?? $moto->moneda ?? 'UYU');
                                $initialAvailable = (int) ($firstVariant['availability']['quantity'] ?? 0) > 0;
                                $features = collect([
                                    $moto->potencia_w ? 'Motor '.$moto->potencia_w.'W' : null,
                                    $batteries->isNotEmpty() ? 'Batería '.($batteries->count() > 1 ? $batteries->implode('Ah / ').'Ah' : $batteries->first().'Ah') : null,
                                    $colors->isNotEmpty() ? 'Colores: '.$colors->implode(', ') : null,
                                    'Carga estimada: 5 a 6 hs',
                                    'Uso urbano',
                                    'Retiro coordinado',
                                ])->filter()->values();
                            @endphp

                            <article class="storefront-editorial-model" data-model-card>
                                <div class="storefront-editorial-model__media" data-model-carousel tabindex="0" role="region" aria-roledescription="carrusel" aria-label="Fotos de {{ $moto->nombre }}">
                                    <div class="storefront-model-card__track" data-carousel-track>
                                        @forelse ($slides as $index => $slide)
                                            <a
                                                href="{{ route('modelos.show', ['slug' => $moto->slug]) }}"
                                                data-carousel-slide
                                                data-carousel-color="{{ $slide['color'] }}"
                                                data-carousel-battery="{{ $slide['battery_ah'] }}"
                                                aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                                            >
                                                <img
                                                    src="{{ $imageUrl((string) $slide['url']) }}"
                                                    alt="{{ $moto->nombre }}, fotografía {{ $index + 1 }}"
                                                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                                    width="1086"
                                                    height="900"
                                                >
                                            </a>
                                        @empty
                                            <a href="{{ route('modelos.show', ['slug' => $moto->slug]) }}" data-carousel-slide aria-hidden="false">
                                                <span class="storefront-model-card__placeholder">{{ $moto->nombre }}</span>
                                            </a>
                                        @endforelse
                                    </div>
                                    @if ($slides->count() > 1)
                                        <button type="button" class="storefront-model-card__arrow storefront-model-card__arrow--prev" data-carousel-previous aria-label="Foto anterior"><x-icon name="arrow-left" :size="20" /></button>
                                        <button type="button" class="storefront-model-card__arrow storefront-model-card__arrow--next" data-carousel-next aria-label="Foto siguiente"><x-icon name="arrow-right" :size="20" /></button>
                                        <span class="storefront-model-card__counter" data-carousel-counter aria-live="polite">1 / {{ $slides->count() }}</span>
                                    @endif
                                    <span class="storefront-model-card__stock {{ $moto->disponible ? 'is-available' : 'is-sold-out' }}">{{ $moto->disponible ? 'Disponible' : 'Agotado' }}</span>
                                </div>

                                <div class="storefront-editorial-model__content">
                                    <p class="storefront-editorial-model__eyebrow">{{ $moto->potencia_w ? $moto->potencia_w.'W' : 'Modelo eléctrico' }}</p>
                                    <h3>{{ $moto->nombre }}</h3>
                                    <p>{{ $moto->descripcion }}</p>

                                    <ul class="storefront-editorial-model__features">
                                        @foreach ($features as $feature)
                                            <li>{{ $feature }}</li>
                                        @endforeach
                                    </ul>

                                    <div class="storefront-editorial-purchase">
                                        @if ($moto->tiene_precio)
                                            <p class="storefront-model-card__price">
                                                <small>Desde</small>
                                                <strong data-variant-price>{{ $formatPrice($initialPrice) }}</strong>
                                                <span data-variant-currency>{{ $initialCurrency }}</span>
                                            </p>
                                        @endif

                                        @if ($variantRows->isNotEmpty())
                                            <form method="POST" action="{{ route('cart.store') }}" class="storefront-variant-form storefront-editorial-variant-form" data-variant-selector data-variant-options='@json($variantRows->values())'>
                                                @csrf
                                                <input type="hidden" name="variant_id" value="{{ $initialAvailable ? ($firstVariant['variant_id'] ?? '') : '' }}" data-variant-id>
                                                <input type="hidden" name="quantity" value="1">
                                                <fieldset>
                                                    @if ($colors->isNotEmpty())
                                                        <label><span><x-icon name="color" :size="16" /> Color</span><select name="color" data-variant-color aria-label="Color de {{ $moto->nombre }}">@foreach ($colors as $color)<option value="{{ $color }}" @selected(($firstVariant['color'] ?? '') === $color)>{{ $color }}</option>@endforeach</select></label>
                                                    @endif
                                                    @if ($batteries->isNotEmpty())
                                                        <label><span><x-icon name="battery" :size="16" /> Batería</span><select name="battery_ah" data-variant-battery aria-label="Batería de {{ $moto->nombre }}">@foreach ($batteries as $battery)<option value="{{ $battery }}" @selected((int) ($firstVariant['battery_ah'] ?? 0) === $battery)>{{ $battery }}Ah</option>@endforeach</select></label>
                                                    @endif
                                                </fieldset>
                                                <p class="storefront-variant-status" data-variant-status aria-live="polite">{{ $initialAvailable ? 'Disponible para reservar online.' : 'Esta combinación está agotada.' }}</p>
                                                <div class="storefront-editorial-model__actions">
                                                    <button type="submit" class="storefront-button storefront-button--primary" data-variant-submit @disabled(! $initialAvailable)><x-icon name="cart" :size="18" /> Agregar al carrito</button>
                                                    <a class="storefront-button storefront-button--outline" href="{{ route('modelos.show', ['slug' => $moto->slug]) }}">Ver ficha completa</a>
                                                </div>
                                            </form>
                                        @else
                                            <div class="storefront-editorial-model__actions">
                                                <a class="storefront-button storefront-button--outline" href="{{ route('modelos.show', ['slug' => $moto->slug]) }}">Ver ficha completa</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="multipage-empty">
                        <h2>Catálogo temporalmente no disponible</h2>
                        <p>Contactanos para consultar los modelos disponibles.</p>
                    </div>
                @endif
            </div>
        </section>

        @if ($latest->isNotEmpty())
            <section class="official-section storefront-latest-models" aria-labelledby="latest-title">
                <div class="official-container">
                    <header class="official-section__heading storefront-models-heading">
                        <div>
                            <p class="official-kicker">Recién llegados</p>
                            <h2 id="latest-title">Últimos ingresos</h2>
                            <p>Modelos publicados actualmente en la tienda online para consultar disponibilidad, color y batería.</p>
                        </div>
                    </header>
                    <div class="storefront-model-grid storefront-model-grid--latest">
                        @foreach ($latest as $moto)
                            @include('components.storefront.model-card', ['moto' => $moto])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="official-section faq-section storefront-models-faq" aria-labelledby="faq-title">
            <div class="official-container faq-layout">
                <div class="faq-heading">
                    <p class="official-kicker">Preguntas frecuentes</p>
                    <h2 id="faq-title">Todo lo que necesitás saber antes de comprar</h2>
                    <p>Estas son algunas de las consultas más comunes sobre nuestros vehículos eléctricos. Si necesitás más información, podés escribirnos directamente.</p>
                </div>
                <div class="faq-list">
                    @foreach ($faqs as $faq)
                        <details>
                            <summary>{{ $faq['question'] }}</summary>
                            <p>{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="official-cta">
            <div class="official-container official-cta__inner">
                <div>
                    <p class="official-kicker">Consulta directa</p>
                    <h2>Encontrá el modelo perfecto para vos</h2>
                    <p>Escribinos por WhatsApp o coordiná una visita. Te asesoramos según tu uso, presupuesto y necesidad.</p>
                </div>
                <div class="official-actions">
                    <a class="official-button official-button--light" href="{{ route('contacto') }}">Consultar por WhatsApp</a>
                    <a class="official-button official-button--outline" href="{{ route('visits.create') }}">Agendar visita</a>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/storefront.js') }}?v={{ filemtime(public_path('js/storefront.js')) }}&hotfix=20260728_variant_gallery_filter" defer></script>
@endpush
