@php
    $seoIndexable=(bool)config('storefront_seo.indexable',false);
    $seoImage=url(asset(config('storefront_seo.default_image')));
    $contact=config('storefront_content.contact');
    $brand=(string)config('storefront_content.brand',config('app.name','ERP'));
    $tagline=(string)config('storefront_content.tagline','Sistema de Gestión Empresarial');
    $businessCategory=(string)config('storefront_content.business_category','productos y servicios');
    $phoneDigits=preg_replace('/\D+/','',(string)($contact['whatsapp_number']??''));
    $schemaPhone=$phoneDigits!==''?'+'.$phoneDigits:null;
    $businessSchema=[
        '@context'=>'https://schema.org',
        '@type'=>'Store',
        '@id'=>url('/').'#store',
        'name'=>$brand,
        'legalName'=>$brand,
        'url'=>url('/'),
        'image'=>$seoImage,
        'description'=>$tagline,
        'telephone'=>$schemaPhone,
        'email'=>($contact['email']??'')!==''?$contact['email']:null,
        'priceRange'=>(string)config('storefront_content.currency','UYU'),
        'currenciesAccepted'=>(string)config('storefront_content.currency','UYU'),
        'paymentAccepted'=>'Efectivo, tarjeta',
        'address'=>[
            '@type'=>'PostalAddress',
            'streetAddress'=>($contact['location']??'')!==''?$contact['location']:null,
            'addressLocality'=>(string)config('storefront_content.address_locality',''),
            'addressRegion'=>(string)config('storefront_content.address_region',''),
            'addressCountry'=>(string)config('storefront_content.address_country',''),
        ],
        'openingHoursSpecification'=>[
            [
                '@type'=>'OpeningHoursSpecification',
                'dayOfWeek'=>['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
                'opens'=>'10:00',
                'closes'=>'19:00',
            ],
        ],
        'contactPoint'=>[
            '@type'=>'ContactPoint',
            'telephone'=>$schemaPhone,
            'contactType'=>'customer service',
            'availableLanguage'=>[(string)config('storefront_content.language','Spanish')],
            'areaServed'=>(string)config('storefront_content.area_served',''),
        ],
        'areaServed'=>($served=(string)config('storefront_content.area_served_name',''))!==''?['@type'=>'Country','name'=>$served]:null,
        'sameAs'=>array_values(array_filter([$contact['instagram_url']??null])),
    ];
    $businessSchema=array_filter($businessSchema, static fn($value)=>$value!==null && $value!=='');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>@yield('title', $brand . ' | ' . $tagline)</title>

    <meta
        name="description"
        content="@yield(
            'description',
            'Conocé el catálogo de ' . $businessCategory . ' de ' . $brand . '.'
        )"
    >

    <meta
        name="robots"
        content="@yield('robots', $seoIndexable ? 'index,follow,max-image-preview:large' : 'noindex,nofollow,noarchive')"
    >

    <link
        rel="canonical"
        href="@yield('canonical', url()->current())"
    >

    <meta
        property="og:type"
        content="website"
    >
    <meta
        property="og:site_name"
        content="{{ $brand }}"
    >
    <meta
        property="og:title"
        content="@yield('title', $brand)"
    >
    <meta
        property="og:description"
        content="@yield(
            'description',
            $tagline
        )"
    >
    <meta
        property="og:url"
        content="@yield('canonical', url()->current())"
    >
    <meta property="og:locale" content="{{ str_replace('-', '_', str_replace('_', '-', app()->getLocale())) }}">
    <meta property="og:image" content="@yield('og_image', $seoImage)">
    <meta property="og:image:alt" content="@yield('og_image_alt', $brand)">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $brand)">
    <meta name="twitter:description" content="@yield('description', $tagline)">
    <meta name="twitter:image" content="@yield('og_image', $seoImage)">

    @if(config('storefront_seo.site_verification')!=='')
        <meta name="google-site-verification" content="{{ config('storefront_seo.site_verification') }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <script type="application/ld+json">{!! json_encode($businessSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>

    <link
        rel="stylesheet"
        href="{{ asset('css/storefront.css') }}?v={{ filemtime(public_path('css/storefront.css')) }}&hotfix=20260728_2035_model_card_media"
    >

    @livewireStyles
    @stack('head')
</head>
<body id="top" class="phase1-public-body">
    <a
        class="phase1-skip-link"
        href="#contenido-principal"
    >
        Ir al contenido
    </a>

    <x-storefront.header />

    @php
        $flashStatus = session('status');
        $flashMessage = match ($flashStatus) {
            'session-closed' => 'Cerraste sesión correctamente.',
            'verification-link-sent' => 'Te enviamos un nuevo enlace de verificación.',
            default => is_string($flashStatus) ? $flashStatus : null,
        };
        $mergeWarnings = collect(session('cart_merge_warnings', []));
        if (session('cart_merge_warning')) $mergeWarnings->push(session('cart_merge_warning'));
    @endphp
    @if($flashMessage)
        <div class="official-container"><div class="storefront-flash" role="status"><x-icon name="check" :size="19" /> <span>{{ $flashMessage }}</span></div></div>
    @endif
    @if($mergeWarnings->isNotEmpty())
        <div class="official-container"><div class="storefront-flash storefront-flash--warning" role="status"><x-icon name="alert" :size="20" /><div>@foreach($mergeWarnings->unique() as $warning)<p>{{ $warning }}</p>@endforeach</div></div></div>
    @endif

    <main
        id="contenido-principal"
        tabindex="-1"
    >
        @yield('content')
    </main>

    <x-storefront.footer />

    @php
        $whatsappFloatingText = rawurlencode(
            'Hola, quiero consultar por ' . $businessCategory . ' de ' . $brand . '.'
        );
    @endphp

    <div class="storefront-floating-actions" aria-label="Accesos rápidos">
        @if(($contact['whatsapp_number'] ?? '') !== '')
        <a
            class="storefront-floating-action storefront-floating-action--whatsapp"
            href="{{ $contact['whatsapp_url'] }}?text={{ $whatsappFloatingText }}"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Hablar por WhatsApp"
        >
            <x-icon name="whatsapp" :size="24" />
        </a>
        @endif

        <a
            class="storefront-floating-action storefront-floating-action--top"
            href="#top"
            aria-label="Volver arriba"
        >
            <x-icon name="arrow-left" :size="22" />
        </a>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
