@php
    $variantRows = collect($moto->variantes ?? [])
        ->filter(fn ($variant) => is_array($variant) && isset($variant['variant_id']))
        ->values();
    $firstVariant = $variantRows->first(
        fn (array $variant): bool => (int) ($variant['availability']['quantity'] ?? 0) > 0,
    ) ?? $variantRows->first();
    $slides = collect();

    foreach ($variantRows as $variant) {
        foreach (($variant['gallery'] ?? []) as $image) {
            $url = is_array($image) ? (string) ($image['url'] ?? '') : (string) $image;
            if ($url === '') {
                continue;
            }

            $slides->push([
                'url' => $url,
                'color' => trim((string) ($variant['color'] ?? '')),
                'battery_ah' => $variant['battery_ah'] ?? null,
            ]);
        }
    }

    if ($slides->isEmpty()) {
        $slides = collect($moto->imagenes ?? [])
            ->filter()
            ->map(fn ($image): array => [
                'url' => (string) $image,
                'color' => '',
                'battery_ah' => null,
            ]);
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
@endphp
<article class="storefront-model-card" data-model-card>
    <div class="storefront-model-card__media" data-model-carousel tabindex="0" role="region" aria-roledescription="carrusel" aria-label="Fotos de {{ $moto->nombre }}">
        <div class="storefront-model-card__track" data-carousel-track>
            @forelse ($slides as $index => $slide)
                @php($image = $slide['url'])
                <a
                    href="{{ route('modelos.show', ['slug' => $moto->slug]) }}"
                    data-carousel-slide
                    data-carousel-color="{{ $slide['color'] }}"
                    data-carousel-battery="{{ $slide['battery_ah'] }}"
                    aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                >
                    <img
                        src="{{ str_starts_with($image, 'http://') || str_starts_with($image, 'https://') ? $image : asset($image) }}"
                        alt="{{ $moto->nombre }}, fotografía {{ $index + 1 }}"
                        loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                        width="1086"
                        height="900"
                    >
                </a>
            @empty
                <a href="{{ route('modelos.show', ['slug' => $moto->slug]) }}" data-carousel-slide aria-hidden="false"><span class="storefront-model-card__placeholder">{{ $moto->nombre }}</span></a>
            @endforelse
        </div>
        @if ($slides->count() > 1)
            <button type="button" class="storefront-model-card__arrow storefront-model-card__arrow--prev" data-carousel-previous aria-label="Foto anterior"><x-icon name="arrow-left" :size="20" /></button>
            <button type="button" class="storefront-model-card__arrow storefront-model-card__arrow--next" data-carousel-next aria-label="Foto siguiente"><x-icon name="arrow-right" :size="20" /></button>
            <span class="storefront-model-card__counter" data-carousel-counter aria-live="polite">1 / {{ $slides->count() }}</span>
        @endif
        <span class="storefront-model-card__stock {{ $moto->disponible ? 'is-available' : 'is-sold-out' }}">{{ $moto->disponible ? 'Stock verificado' : 'Agotado' }}</span>
    </div>
    <div class="storefront-model-card__body">
        <p class="storefront-model-card__eyebrow">
            @if ($moto->potencia_w)
                <x-icon name="power" :size="15" /> Potencia · {{ $moto->potencia_w }}W
            @else
                Movilidad eléctrica
            @endif
        </p>
        <h2><a href="{{ route('modelos.show', ['slug' => $moto->slug]) }}">{{ $moto->nombre }}</a></h2>
        <p>{{ $moto->descripcion }}</p>
        @if ($moto->tiene_precio)
            <p class="storefront-model-card__price">
                <small>Precio</small>
                <strong data-variant-price>$ {{ number_format($initialPrice, 0, ',', '.') }}</strong>
                <span data-variant-currency>{{ $initialCurrency }}</span>
            </p>
        @endif
        @if ($variantRows->isNotEmpty())
            <form method="POST" action="{{ route('cart.store') }}" class="storefront-variant-form" data-variant-selector data-variant-options='@json($variantRows->values())'>
                @csrf
                <input type="hidden" name="variant_id" value="{{ $initialAvailable ? ($firstVariant['variant_id'] ?? '') : '' }}" data-variant-id>
                <input type="hidden" name="quantity" value="1">
                @if ($colors->isNotEmpty())
                    <label><span><x-icon name="color" :size="16" /> Color</span><select name="color" data-variant-color aria-label="Color de {{ $moto->nombre }}">@foreach ($colors as $color)<option value="{{ $color }}" @selected(($firstVariant['color'] ?? '') === $color)>{{ $color }}</option>@endforeach</select></label>
                @endif
                @if ($batteries->isNotEmpty())
                    <label><span><x-icon name="battery" :size="16" /> Batería</span><select name="battery_ah" data-variant-battery aria-label="Batería de {{ $moto->nombre }}">@foreach ($batteries as $battery)<option value="{{ $battery }}" @selected((int) ($firstVariant['battery_ah'] ?? 0) === $battery)>{{ $battery }}Ah</option>@endforeach</select></label>
                @endif
                <p class="storefront-variant-status" data-variant-status aria-live="polite">{{ $initialAvailable ? '' : 'Esta combinación está agotada.' }}</p>
                <button type="submit" class="storefront-button storefront-button--primary" data-variant-submit @disabled(! $initialAvailable)><x-icon name="cart" :size="18" /> Agregar al carrito</button>
            </form>
        @else
            <a class="storefront-button storefront-button--outline" href="{{ route('modelos.show', ['slug' => $moto->slug]) }}">Ver ficha completa</a>
        @endif
        <a class="storefront-model-card__link" href="{{ route('modelos.show', ['slug' => $moto->slug]) }}">Ver modelo completo <x-icon name="arrow-right" :size="16" /></a>
    </div>
</article>
