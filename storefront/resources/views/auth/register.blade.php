@extends('layouts.storefront-public')

@section('title', 'Crear cuenta | ERP')
@section('robots', 'noindex,nofollow')

@section(
    'description',
    'Creá tu cuenta de cliente de ERP.'
)

@section('content')
    <section class="customer-auth">
        <div class="customer-auth__container">
            <header class="customer-auth__heading">
                <p class="phase1-eyebrow">
                    Cuenta de cliente
                </p>

                <h1>Crear cuenta</h1>

                <p>
                    Completá tus datos básicos. El resto
                    se solicitará únicamente al comprar.
                </p>
            </header>

            <form
                class="customer-auth__form"
                method="POST"
                action="{{ route('register.store') }}"
            >
                @csrf

                <div class="customer-auth__row">
                    <label>
                        <span>Nombre</span>

                        <input
                            type="text"
                            name="first_name"
                            value="{{ old('first_name') }}"
                            autocomplete="given-name"
                            maxlength="100"
                            required
                            autofocus
                        >

                        @error('first_name')
                            <small class="customer-auth__error">
                                {{ $message }}
                            </small>
                        @enderror
                    </label>

                    <label>
                        <span>Apellido</span>

                        <input
                            type="text"
                            name="last_name"
                            value="{{ old('last_name') }}"
                            autocomplete="family-name"
                            maxlength="100"
                            required
                        >

                        @error('last_name')
                            <small class="customer-auth__error">
                                {{ $message }}
                            </small>
                        @enderror
                    </label>
                </div>

                <label>
                    <span>Correo electrónico</span>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                    >

                    @error('email')
                        <small class="customer-auth__error">
                            {{ $message }}
                        </small>
                    @enderror
                </label>

                <label>
                    <input type="checkbox" name="accept_privacy" value="1" required>
                    Leí la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener">política de privacidad</a> y acepto el tratamiento necesario para crear y administrar mi cuenta.
                    @error('accept_privacy')<small class="customer-auth__error">{{ $message }}</small>@enderror
                </label>

                <label>
                    <span>Contraseña</span>

                    <input
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >

                    <small class="customer-auth__hint">
                        Mínimo 10 caracteres, con mayúsculas,
                        minúsculas y números.
                    </small>

                    @error('password')
                        <small class="customer-auth__error">
                            {{ $message }}
                        </small>
                    @enderror
                </label>

                <label>
                    <span>Confirmar contraseña</span>

                    <input
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                </label>

                <button
                    class="phase1-button customer-auth__submit"
                    type="submit"
                >
                    Crear mi cuenta
                </button>

                <p class="customer-auth__secondary">
                    ¿Ya tenés una cuenta?

                    <a href="{{ route('login') }}">
                        Ingresar
                    </a>
                </p>
            </form>
        </div>
    </section>
@endsection
