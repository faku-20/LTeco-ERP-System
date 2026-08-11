@php
    $variants = collect($variants ?? [])->values();
    $colors = $variants->pluck('color')->filter()->unique()->values();
    $batteries = $variants->pluck('battery_ah')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value)->unique()->sort()->values();
    $first = $variants->first(fn (array $variant): bool => (int) ($variant['availability']['quantity'] ?? 0) > 0) ?? $variants->first();
    $initialAvailable = (int) ($first['availability']['quantity'] ?? 0) > 0;
@endphp
<form method="POST" action="{{ route('cart.store') }}" class="storefront-variant-form storefront-variant-form--detail" data-variant-selector data-variant-options='@json($variants->values())'>
    @csrf
    <input type="hidden" name="variant_id" value="{{ $initialAvailable ? ($first['variant_id'] ?? '') : '' }}" data-variant-id>
    <input type="hidden" name="quantity" value="1">
    <fieldset><legend>Configurá tu {{ $model }}</legend>
        @if ($colors->isNotEmpty())<label><span><x-icon name="color" :size="17" /> Color</span><select name="color" data-variant-color>@foreach ($colors as $color)<option value="{{ $color }}" @selected(($first['color'] ?? '') === $color)>{{ $color }}</option>@endforeach</select></label>@endif
        @if ($batteries->isNotEmpty())<label><span><x-icon name="battery" :size="17" /> Batería</span><select name="battery_ah" data-variant-battery>@foreach ($batteries as $battery)<option value="{{ $battery }}" @selected((int) ($first['battery_ah'] ?? 0) === $battery)>{{ $battery }}Ah</option>@endforeach</select></label>@endif
    </fieldset>
    <p class="storefront-variant-price" aria-live="polite"><strong data-variant-price>$ {{ number_format((float) ($first['price']['gross'] ?? 0), 0, ',', '.') }}</strong> <span data-variant-currency>{{ $first['price']['currency'] ?? 'UYU' }}</span></p>
    <p class="storefront-variant-status" data-variant-status aria-live="polite">{{ $initialAvailable ? '' : 'Esta combinación está agotada.' }}</p>
    <button class="btn" type="submit" data-variant-submit @disabled(! $initialAvailable)><x-icon name="cart" :size="18" /> Agregar al carrito</button>
</form>
