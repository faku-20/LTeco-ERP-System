@extends('layouts.storefront-public')

@section('title', 'Nueva contraseña | CommerceOps')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="customer-auth"><div class="customer-auth__container">
    <header class="customer-auth__heading"><p class="phase1-eyebrow">Cuenta de cliente</p><h1>Elegí una nueva contraseña</h1></header>
    <form class="customer-auth__form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Correo<input type="email" name="email" value="{{ old('email', $email) }}" required></label>
        <label>Nueva contraseña<input type="password" name="password" autocomplete="new-password" required></label>
        <label>Confirmar contraseña<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        @if ($errors->any())
            <div class="customer-auth__errors">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <button class="phase1-button customer-auth__submit">Guardar contraseña</button>
    </form>
</div></section>
@endsection
