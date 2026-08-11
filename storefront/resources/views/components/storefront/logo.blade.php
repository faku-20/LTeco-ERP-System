@props([
    'width' => 132,
    'height' => 54,
    'loading' => 'eager',
    'alt' => config('storefront_content.brand', config('app.name', 'ERP')),
])
@php
    $svgRelative = 'images/brand/logo-ltecobike.svg';
    $source = file_exists(public_path($svgRelative))
        ? asset($svgRelative)
        : asset('images/brand/logo-header-public-web.png');
@endphp
<img
    {{ $attributes }}
    src="{{ $source }}"
    alt="{{ $alt }}"
    width="{{ $width }}"
    height="{{ $height }}"
    loading="{{ $loading }}"
>
