@php
    $firstImage=collect($moto->imagenes??[])->first();
    $canonical=route('modelos.show',['slug'=>$moto->slug]);
    $productSchema=[
        '@context'=>'https://schema.org',
        '@type'=>'Product',
        'name'=>$moto->nombre,
        'model'=>$moto->nombre,
        'description'=>$moto->descripcion,
        'image'=>collect($moto->imagenes??[])->map(fn(string $image)=>url(asset($image)))->values()->all(),
        'brand'=>['@type'=>'Brand','name'=>'ERP'],
        'category'=>'Bici-moto eléctrica',
        'url'=>$canonical,
        'additionalProperty'=>array_values(array_filter([
            $moto->potencia_w ? ['@type'=>'PropertyValue','name'=>'Potencia','value'=>$moto->potencia_w.'W'] : null,
            ['@type'=>'PropertyValue','name'=>'Uso recomendado','value'=>'Traslados urbanos'],
            ['@type'=>'PropertyValue','name'=>'Incluye','value'=>'Vehículo y cargador compatible'],
        ])),
        'offers'=>[
            '@type'=>'Offer',
            'priceCurrency'=>$moto->moneda?:'UYU',
            'price'=>(string)round((float)$moto->precio,2),
            'availability'=>$moto->disponible?'https://schema.org/InStock':'https://schema.org/OutOfStock',
            'itemCondition'=>'https://schema.org/NewCondition',
            'url'=>$canonical,
            'seller'=>['@type'=>'Organization','name'=>'ERP'],
        ],
    ];
    $breadcrumbSchema=[
        '@context'=>'https://schema.org',
        '@type'=>'BreadcrumbList',
        'itemListElement'=>[
            ['@type'=>'ListItem','position'=>1,'name'=>'Inicio','item'=>route('catalogo')],
            ['@type'=>'ListItem','position'=>2,'name'=>'Modelos','item'=>route('modelos.index')],
            ['@type'=>'ListItem','position'=>3,'name'=>$moto->nombre,'item'=>$canonical],
        ],
    ];
    $normalizedModelName=\Illuminate\Support\Str::of($moto->nombre)->ascii()->lower()->toString();
    $seoDescription='Conocé fotografías, variantes, precio y disponibilidad de '.$moto->nombre.' ERP.';
    if(str_contains($normalizedModelName,'sl')&&str_contains($normalizedModelName,'500')){
        $seoDescription='SL-500W eléctrica 500W y batería 20Ah: modelo urbano con canasto frontal, baúl trasero, autonomía aproximada de 50 km y retiro coordinado en zona Belvedere.';
    }elseif(str_contains($normalizedModelName,'q8')&&str_contains($normalizedModelName,'500')){
        $seoDescription='Q8-500W eléctrica 500W: opción urbana compacta con variantes de color y batería, autonomía aproximada de 50 km y asesoramiento ERP.';
    }
@endphp
@extends('layouts.storefront-public')
@section('title',$moto->nombre.' | ERP')
@section('description',$seoDescription)
@section('canonical',$canonical)
@if($firstImage)@section('og_image',url(asset($firstImage)))@section('og_image_alt',$moto->nombre.' ERP')@endif
@push('head')
    @if($firstImage)<link rel="preload" as="image" href="{{ asset($firstImage) }}">@endif
    <script type="application/ld+json">{!! json_encode($productSchema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endpush
@section('content')
    <div class="model-detail-page"><livewire:modelo-detalle :slug="$slug" /></div>
@endsection
@push('scripts')<script src="{{ asset('js/storefront.js') }}?v={{ filemtime(public_path('js/storefront.js')) }}&hotfix=20260728_variant_gallery_filter" defer></script>@endpush
