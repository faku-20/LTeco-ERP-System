<!DOCTYPE html>
<html lang="es-UY">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Comprobante de compra | CommerceOps')</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}?v={{ filemtime(public_path('css/storefront.css')) }}">
</head>
<body class="receipt-document-body">
    @yield('content')
</body>
</html>
