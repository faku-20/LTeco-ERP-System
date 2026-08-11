@php
    $carouselImages = collect(
        $moto->imagenes
    )
        ->map(
            function (
                string $image,
                int $index
            ) use (
                $moto
            ): array {
                $size = @getimagesize(
                    public_path($image)
                );

                $portrait = (
                    is_array($size)
                    && isset($size[0], $size[1])
                    && $size[1] > $size[0]
                );

                return [
                    'src' => $image,
                    'portrait' => $portrait,
                    'alt' => (
                        $moto->nombre
                        . ', fotografía '
                        . ($index + 1)
                    ),
                ];
            },
        )
        ->values();

    $batteryLabel = null;
    $variantRows = collect($moto->variantes ?? [])->filter(fn ($variant) => is_array($variant) && isset($variant['variant_id']))->values();

    if ($moto->bateria_ah) {
        $batteryLabel = (
            count($variantRows) > 1
                ? 'Hasta '
                : ''
        )
            . $moto->bateria_ah
            . ' Ah';
    }

    $variantCarouselImages = $variantRows
        ->flatMap(
            function (array $variant): array {
                return collect($variant['gallery'] ?? [])
                    ->map(
                        function (array|string $image) use ($variant): array {
                            $src = is_array($image)
                                ? (string) ($image['url'] ?? '')
                                : (string) $image;

                            if ($src === '') {
                                return [];
                            }

                            $size = str_starts_with($src, 'http://')
                                || str_starts_with($src, 'https://')
                                    ? false
                                    : @getimagesize(public_path($src));

                            return [
                                'src' => $src,
                                'portrait' => (
                                    is_array($size)
                                    && isset($size[0], $size[1])
                                    && $size[1] > $size[0]
                                ),
                                'alt' => trim(
                                    (string) ($variant['model'] ?? 'Modelo')
                                    . ' '
                                    . (string) ($variant['color'] ?? '')
                                ),
                                'color' => (string) ($variant['color'] ?? ''),
                                'battery_ah' => (string) ($variant['battery_ah'] ?? ''),
                            ];
                        },
                    )
                    ->filter()
                    ->all();
            },
        )
        ->unique(fn (array $image): string => $image['src'] . '|' . $image['color'] . '|' . $image['battery_ah'])
        ->values();

    if ($variantCarouselImages->isNotEmpty()) {
        $carouselImages = $variantCarouselImages;
    }

    $normalizedModelName = \Illuminate\Support\Str::of($moto->nombre)
        ->ascii()
        ->lower()
        ->toString();

    $batteryOptionsLabel = collect($moto->opciones_bateria ?? [])
        ->filter(fn ($value) => is_numeric($value))
        ->map(fn ($value) => ((int) $value) . 'Ah')
        ->unique()
        ->values()
        ->join(' o ');

    $modelInfo = [
        'summary' => [
            'Conocé este modelo eléctrico ERP. Te asesoramos según disponibilidad, colores y forma de uso.',
        ],
        'ideal_for' => [
            'Traslados urbanos diarios',
            'Trayectos cortos al trabajo o estudio',
            'Uso práctico dentro de la ciudad',
        ],
        'included' => [
            'Vehículo seleccionado',
            'Cargador compatible',
            'Accesorios o trámites solo si se cotizan expresamente',
        ],
        'warranty' => [
            'Garantía ERP de 1 año o 6000 km, lo que ocurra primero.',
            'Requiere realizar los 4 mantenimientos correspondientes durante el primer año.',
            'No cubre golpes, accidentes, modificaciones, ingreso excesivo de agua, sobrecarga, falta de mantenimiento ni desgaste normal.',
        ],
        'specs' => array_values(array_filter([
            $moto->potencia_w ? 'Motor: ' . $moto->potencia_w . 'W' : null,
            $batteryOptionsLabel !== '' ? 'Batería: ' . $batteryOptionsLabel : null,
            'Velocidad máxima: 45 km/h',
            'Tiempo de carga: 5 a 6 hs',
            'Peso de carga: hasta 100 kg',
            'Alarma de seguridad',
            '3 velocidades',
        ])),
        'license' => 'Requiere libreta para moto/ciclomotor categoría G1 o G2.',
    ];

    if (str_contains($normalizedModelName, 'q8') && str_contains($normalizedModelName, '500')) {
        $modelInfo = [
            'summary' => [
                'Bici-moto scooter eléctrica urbana de 500W, ágil para traslados diarios, con diseño compacto y respaldo para acompañante.',
            ],
            'ideal_for' => [
                'Ir al trabajo o estudiar en trayectos urbanos',
                'Moverte con bajo costo operativo en la ciudad',
                'Clientes que buscan una opción compacta con respaldo para acompañante',
            ],
            'included' => [
                'Vehículo seleccionado',
                'Cargador compatible',
                'Empadronamiento, seguro SOA y accesorios no incluidos',
            ],
            'warranty' => [
                'Garantía ERP de 1 año o 6000 km, lo que ocurra primero.',
                'Requiere realizar los 4 mantenimientos correspondientes durante el primer año.',
                'No cubre golpes, accidentes, modificaciones, ingreso excesivo de agua, sobrecarga, falta de mantenimiento ni desgaste normal.',
            ],
            'specs' => array_values(array_filter([
                'Motor: 500W',
                'Batería: ' . ($batteryOptionsLabel !== '' ? $batteryOptionsLabel : '12Ah o 20Ah'),
                'Velocidad máxima: 45 km/h',
                'Autonomía aproximada: 50 km',
                'Tiempo de carga: 5 a 6 hs',
                'Frenos: disco',
                'Peso de carga: hasta 100 kg',
                'Alarma de seguridad',
                '3 velocidades',
            ])),
            'license' => 'Requiere libreta para moto/ciclomotor categoría G1 o G2.',
        ];
    } elseif (str_contains($normalizedModelName, 'sl') && str_contains($normalizedModelName, '500')) {
        $modelInfo = [
            'summary' => [
                'Bici-moto scooter eléctrica urbana de 500W y 20Ah, con postura cómoda, doble faro delantero, canasto frontal y baúl trasero.',
            ],
            'ideal_for' => [
                'Traslados urbanos diarios con buena comodidad',
                'Ir al trabajo, hacer mandados o moverte por barrios cercanos',
                'Clientes que valoran canasto frontal, baúl trasero y postura cómoda',
            ],
            'included' => [
                'Vehículo seleccionado',
                'Cargador compatible',
                'Empadronamiento, seguro SOA y accesorios no incluidos',
            ],
            'warranty' => [
                'Garantía ERP de 1 año o 6000 km, lo que ocurra primero.',
                'Requiere realizar los 4 mantenimientos correspondientes durante el primer año.',
                'No cubre golpes, accidentes, modificaciones, ingreso excesivo de agua, sobrecarga, falta de mantenimiento ni desgaste normal.',
            ],
            'specs' => [
                'Motor: 500W',
                'Batería: 20Ah',
                'Velocidad máxima: 45 km/h',
                'Autonomía aproximada: 50 km',
                'Tiempo de carga: 5 a 6 hs',
                'Freno delantero: disco',
                'Freno trasero: tambor',
                'Peso de carga: 130-140 kg',
                'Alarma de seguridad',
                '3 velocidades',
            ],
            'license' => 'Requiere libreta para moto/ciclomotor categoría G1 o G2.',
        ];
    } elseif (str_contains($normalizedModelName, 'q8') && str_contains($normalizedModelName, '350')) {
        $modelInfo = [
            'summary' => [
                'Bici-moto scooter eléctrica práctica para moverte por la ciudad, con bajo costo de uso, motor de 350W y autonomía aproximada de 45 km.',
            ],
            'ideal_for' => [
                'Trayectos urbanos cortos',
                'Uso diario económico dentro de la ciudad',
                'Clientes que priorizan practicidad y bajo consumo',
            ],
            'included' => [
                'Vehículo seleccionado',
                'Cargador compatible',
                'Accesorios o trámites solo si se cotizan expresamente',
            ],
            'warranty' => [
                'Garantía ERP de 1 año o 6000 km, lo que ocurra primero.',
                'Requiere realizar los 4 mantenimientos correspondientes durante el primer año.',
                'No cubre golpes, accidentes, modificaciones, ingreso excesivo de agua, sobrecarga, falta de mantenimiento ni desgaste normal.',
            ],
            'specs' => [
                'Motor: 350W',
                'Velocidad máxima: 45 km/h',
                'Autonomía aproximada: 45 km',
                'Tiempo de carga: 5 a 6 hs',
                'Frenos: tambor',
                'Peso de carga: hasta 100 kg',
                'Alarma de seguridad',
                '3 velocidades',
            ],
            'license' => 'Requiere libreta para moto/ciclomotor categoría G1 o G2.',
        ];
    }
@endphp

<section class="model-detail">
    <div class="model-detail__container">
        <nav
            class="model-breadcrumb"
            aria-label="Navegación secundaria"
        >
            <a href="{{ route('catalogo') }}">
                Inicio
            </a>

            <span aria-hidden="true">/</span>

            <a href="{{ route('modelos.index') }}">
                Modelos
            </a>

            <span aria-hidden="true">/</span>

            <strong>{{ $moto->nombre }}</strong>
        </nav>

        <div class="model-detail__grid">
            <div class="model-detail__media-column">
                <div
                    class="
                        model-carousel
                        model-detail__carousel
                    "
                    data-model-carousel
                    tabindex="0"
                    role="region"
                    aria-roledescription="carrusel"
                    aria-label="
                        Fotografías de {{ $moto->nombre }}
                    "
                >
                    <div class="model-carousel__viewport">
                        <div
                            class="model-carousel__track"
                            data-carousel-track
                        >
                            @foreach (
                                $carouselImages
                                as $imageIndex => $image
                            )
                                <figure
                                    class="
                                        model-carousel__slide
                                        {{ $image['portrait']
                                            ? 'is-portrait'
                                            : '' }}
                                    "
                                    data-carousel-slide
                                    data-carousel-color="{{ $image['color'] ?? '' }}"
                                    data-carousel-battery="{{ $image['battery_ah'] ?? '' }}"
                                    aria-hidden="{{
                                        $imageIndex === 0
                                            ? 'false'
                                            : 'true'
                                    }}"
                                >
                                    <img
                                        src="{{ str_starts_with($image['src'], 'http://') || str_starts_with($image['src'], 'https://') ? $image['src'] : asset($image['src']) }}"
                                        alt="{{ $image['alt'] }}"
                                        loading="{{
                                            $imageIndex === 0
                                                ? 'eager'
                                                : 'lazy'
                                        }}"
                                    >
                                </figure>
                            @endforeach
                        </div>

                        <span class="model-editorial__badge">
                            {{ $moto->nombre }}
                        </span>

                        @if ($carouselImages->count() > 1)
                            <button
                                class="
                                    model-carousel__arrow
                                    model-carousel__arrow--previous
                                "
                                type="button"
                                data-carousel-previous
                                aria-label="Fotografía anterior"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="m15 18-6-6 6-6"/>
                                </svg>
                            </button>

                            <button
                                class="
                                    model-carousel__arrow
                                    model-carousel__arrow--next
                                "
                                type="button"
                                data-carousel-next
                                aria-label="Fotografía siguiente"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="m9 18 6-6-6-6"/>
                                </svg>
                            </button>

                            <span
                                class="model-carousel__counter"
                                aria-live="polite"
                                data-carousel-counter
                            >
                                1 / {{ $carouselImages->count() }}
                            </span>
                        @endif
                    </div>

                    @if ($carouselImages->count() > 1)
                        <div class="model-carousel__thumbnails">
                            @foreach (
                                $carouselImages
                                as $imageIndex => $image
                            )
                                <button
                                    class="
                                        model-carousel__thumbnail
                                        {{ $imageIndex === 0
                                            ? 'is-active'
                                            : '' }}
                                    "
                                    type="button"
                                    data-carousel-thumbnail="{{
                                        $imageIndex
                                    }}"
                                    aria-label="
                                        Mostrar fotografía
                                        {{ $imageIndex + 1 }}
                                    "
                                    aria-current="{{
                                        $imageIndex === 0
                                            ? 'true'
                                            : 'false'
                                    }}"
                                >
                                    <img
                                        src="{{ str_starts_with($image['src'], 'http://') || str_starts_with($image['src'], 'https://') ? $image['src'] : asset($image['src']) }}"
                                        alt=""
                                        loading="lazy"
                                    >
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <section class="model-detail__full-info" aria-labelledby="model-full-info-title">
                    <h2 id="model-full-info-title">Ficha completa</h2>

                    <div class="model-detail__copy">
                        @foreach ($modelInfo['summary'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>

                    <ul class="model-detail__specifications">
                        @foreach ($modelInfo['specs'] as $spec)
                            <li>{{ $spec }}</li>
                        @endforeach
                    </ul>

                    <div class="model-detail__commercial-grid">
                        <section>
                            <h3>Ideal para</h3>

                            <ul>
                                @foreach ($modelInfo['ideal_for'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </section>

                        <section>
                            <h3>Qué incluye</h3>

                            <ul>
                                @foreach ($modelInfo['included'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </section>

                        <section>
                            <h3>Garantía</h3>

                            <ul>
                                @foreach ($modelInfo['warranty'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </section>
                    </div>

                    <p class="model-detail__license">
                        {{ $modelInfo['license'] }}
                    </p>
                </section>
            </div>

            <article class="model-detail__content">
                <span
                    @class([
                        'availability',
                        'is-available' => $moto->disponible,
                    ])
                >
                    {{ $moto->disponible
                        ? 'Disponible'
                        : 'Consultar disponibilidad' }}
                </span>

                <h1>{{ $moto->nombre }}</h1>

                @if ($moto->tiene_precio)
                    <p class="model-detail__price">
                        <small>Desde</small>

                        <strong>
                            {{ $moto->moneda === 'USD'
                                ? 'US$'
                                : '$' }}

                            {{ number_format(
                                (float) $moto->precio,
                                0,
                                ',',
                                '.'
                            ) }}
                        </strong>

                        <span>{{ $moto->moneda }}</span>
                    </p>
                @else
                    <p
                        class="
                            model-detail__price
                            model-detail__price--consult
                        "
                    >
                        Consultar precio
                    </p>
                @endif

                <p class="model-detail__description">
                    {{ $moto->descripcion }}
                </p>

                <h2>Resumen y disponibilidad</h2>

                <dl class="model-detail__facts">
                    @if ($moto->potencia_w)
                        <div>
                            <dt>Potencia</dt>
                            <dd>
                                {{ $moto->potencia_w }} W
                            </dd>
                        </div>
                    @endif

                    @if ($batteryLabel)
                        <div>
                            <dt>Batería</dt>
                            <dd>{{ $batteryLabel }}</dd>
                        </div>
                    @endif

                    @if (count($variantRows) > 1)
                        <div>
                            <dt>Variantes registradas</dt>
                            <dd>{{ count($variantRows) }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt>Disponibilidad</dt>
                        <dd>
                            {{ $moto->disponible
                                ? 'Disponible'
                                : 'A confirmar' }}
                        </dd>
                    </div>
                </dl>

                <div class="model-detail__colors">
                    <strong>Colores disponibles</strong>

                    @if (count($moto->colores) > 0)
                        <div>
                            @foreach (
                                $moto->colores as $color
                            )
                                @if ($variantRows->isNotEmpty())
                                    <button
                                        type="button"
                                        data-variant-color-option="{{ $color }}"
                                    >
                                        {{ $color }}
                                    </button>
                                @else
                                    <span>{{ $color }}</span>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p>
                            Consultá las variantes disponibles.
                        </p>
                    @endif
                </div>

                <div class="model-detail__purchase">
                    @if ($variantRows->isNotEmpty())
                        @include('components.storefront.variant-selector', ['variants' => $variantRows, 'model' => $moto->nombre])
                    @endif
                </div>

                <div class="model-detail__secondary-actions">
                    <a
                        class="btn-outline"
                        href="{{ route('visits.create', ['modelo' => $moto->nombre]) }}"
                    >
                        Agendar visita
                    </a>
                    <a
                        class="btn-outline"
                        href="{{ $moto->whatsapp_url }}"
                        @if (
                            str_starts_with(
                                $moto->whatsapp_url,
                                'https://'
                            )
                        )
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                    >
                        Consultar por WhatsApp

                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            aria-hidden="true"
                        >
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>

                    <a
                        class="btn-outline"
                        href="{{ route('modelos.index') }}"
                    >
                        Ver todos los modelos
                    </a>
                </div>

                <p class="model-detail__notice">
                    Precio, color, configuración y disponibilidad
                    sujetos a confirmación.
                </p>
            </article>
        </div>
    </div>
</section>
