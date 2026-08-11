@extends('layouts.storefront-public')

@section('title','Agendar visita | ERP')
@section('description','Solicitá una visita coordinada con ERP en zona Belvedere. Coordinación previa obligatoria.')

@section('content')
<section class="visit-booking"><div class="phase1-container visit-booking__grid">
    <header class="visit-booking__intro">
        <p class="phase1-eyebrow">Visita coordinada · zona Belvedere</p>
        <h1>Agendá tu visita</h1>
        <p>Elegí una fecha y un horario preferido. La visita queda solicitada; nuestro equipo te confirmará por WhatsApp antes de que vengas.</p>
        <div class="visit-booking__notice"><strong>Atención con agenda previa</strong><span>No concurras sin confirmación del equipo de ERP.</span></div>
    </header>
    <form class="visit-booking__form" method="POST" action="{{ route('visits.store') }}">@csrf
        @if(session('status'))<div class="customer-auth__success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="customer-auth__errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="visit-booking__row">
            <label>Nombre completo <span>*</span><input name="full_name" value="{{ old('full_name',auth()->user()?->full_name) }}" autocomplete="name" required maxlength="160"></label>
            <label>Teléfono / WhatsApp <span>*</span><input name="phone" value="{{ old('phone',auth()->user()?->profile?->phone_encrypted) }}" autocomplete="tel" inputmode="tel" required maxlength="24"></label>
        </div>
        <label>Correo<input type="email" name="email" value="{{ old('email',auth()->user()?->email) }}" autocomplete="email" maxlength="190"><small>Opcional si todavía no tenés cuenta.</small></label>
        <label>Modelo de interés<select name="model"><option value="">Quiero recibir orientación</option>@foreach($models as $model)<option value="{{ $model }}" @selected(old('model',$selectedModel)===$model)>{{ $model }}</option>@endforeach</select></label>
        <div class="visit-booking__row">
            <label>Fecha preferida <span>*</span><input type="date" name="preferred_date" value="{{ old('preferred_date') }}" min="{{ now('America/Montevideo')->addDay()->format('Y-m-d') }}" max="{{ now('America/Montevideo')->addDays(90)->format('Y-m-d') }}" required></label>
            <label>Horario preferido <span>*</span><select name="preferred_time" required>@foreach($times as $time)<option value="{{ $time }}" @selected(old('preferred_time','11:30')===$time)>{{ $time }} hs</option>@endforeach</select></label>
        </div>
        <label>Comentarios<textarea name="comments" rows="3" maxlength="1000" placeholder="Contanos qué modelo querés conocer o qué duda tenés.">{{ old('comments') }}</textarea></label>
        <label class="visit-booking__privacy"><input type="checkbox" name="accept_privacy" value="1" required> Acepto que ERP use estos datos para coordinar la visita, según la <a href="{{ route('privacidad') }}">política de privacidad</a>.</label>
        <label class="visit-booking__honeypot" aria-hidden="true">Sitio web<input name="website" tabindex="-1" autocomplete="off"></label>
        <button class="phase1-button" type="submit">Solicitar visita</button>
    </form>
</div></section>
@endsection
