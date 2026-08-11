@extends('layouts.storefront-public')

@section(
    'title',
    'Verificar correo electrónico | CommerceOps'
)

@section('robots', 'noindex,nofollow')

@section('content')
    <section class="customer-auth">
        <div class="customer-auth__container">
            <header class="customer-auth__heading">
                <p class="phase1-eyebrow">
                    Último paso
                </p>

                <h1>Verificá tu correo</h1>

                <p>
                    Enviamos un enlace de verificación a
                    <strong>{{ auth()->user()->email }}</strong>.
                    Debés abrirlo antes de usar tu cuenta.
                </p>
            </header>

            <div class="customer-auth__form">
                @if (
                    session('status')
                    === 'verification-link-sent'
                )
                    <div class="customer-auth__success">
                        Enviamos un nuevo enlace de verificación.
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('verification.send') }}"
                >
                    @csrf

                    <button
                        class="phase1-button customer-auth__submit"
                        type="submit"
                    >
                        Reenviar enlace
                    </button>
                </form>

                <form
                    class="customer-auth__logout"
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button type="submit">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
