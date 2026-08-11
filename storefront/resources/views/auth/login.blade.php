@extends('layouts.storefront-public')

@section('title', 'Ingresar | CommerceOps')
@section('robots', 'noindex,nofollow')

@section(
    'description',
    'Ingresá a tu cuenta de cliente de CommerceOps.'
)

@section('content')
    <section class="customer-auth">
        <div class="customer-auth__container">
            <header class="customer-auth__heading">
                <p class="phase1-eyebrow">
                    Cuenta de cliente
                </p>

                <h1>Ingresar</h1>

                <p>
                    Accedé con el correo electrónico
                    asociado a tu cuenta.
                </p>
            </header>

            <form
                class="customer-auth__form"
                method="POST"
                action="{{ route('login.store') }}"
            >
                @csrf

                <label>
                    <span>Correo electrónico</span>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                    >

                    @error('email')
                        <small class="customer-auth__error">
                            {{ $message }}
                        </small>
                    @enderror
                </label>

                <label>
                    <span>Contraseña</span>

                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                    @error('password')
                        <small class="customer-auth__error">
                            {{ $message }}
                        </small>
                    @enderror
                </label>

                <label class="customer-auth__checkbox">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                    >

                    <span>Mantener la sesión iniciada</span>
                </label>

                <button
                    class="phase1-button customer-auth__submit"
                    type="submit"
                >
                    Ingresar a mi cuenta
                </button>
                <p class="customer-auth__secondary"><a href="{{ route('password.request') }}">Olvidé mi contraseña</a></p>

                @if (
                    config(
                        'storefront_auth.registration_enabled'
                    )
                )
                    <p class="customer-auth__secondary">
                        ¿Todavía no tenés una cuenta?

                        <a href="{{ route('register') }}">
                            Registrarte
                        </a>
                    </p>
                @endif
            </form>
        </div>
    </section>
@endsection
