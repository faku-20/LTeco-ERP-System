@extends('layouts.storefront-public')

@section('title','ERP | Bici-motos y scooters eléctricos en Uruguay')
@section('description','Bici-motos y scooters eléctricos en Uruguay. Comprá online, coordiná tu retiro en Belvedere y recibí atención directa de ERP.')

@push('head')
    <link rel="preload" as="image" href="{{ asset('images/editorial/hero-principal.webp') }}" fetchpriority="high">
@endpush

@section('content')
    <livewire:catalogo />
@endsection

@push('scripts')
    <script src="{{ asset('js/storefront.js') }}?v={{ filemtime(public_path('js/storefront.js')) }}&hotfix=20260728_variant_gallery_filter" defer></script>
@endpush
