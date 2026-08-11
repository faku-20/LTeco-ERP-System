<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PanelApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class VisitController extends Controller
{
    private const TIMES=['10:00','11:30','14:00','16:00','17:30'];

    public function create(Request$request):View
    {
        $models=collect(config('storefront_content.models',[]))->pluck('name')->filter()->values()->all();
        return view('visits.create',['models'=>$models,'times'=>self::TIMES,'selectedModel'=>$request->string('modelo')->toString()]);
    }

    public function store(Request$request,PanelApiClient$panel):RedirectResponse
    {
        if($request->filled('website'))return back()->with('status','Recibimos tu solicitud. Te contactaremos por WhatsApp para confirmar el horario.');
        $models=collect(config('storefront_content.models',[]))->pluck('name')->filter()->values()->all();
        $validated=$request->validate([
            'full_name'=>['required','string','max:160'],
            'phone'=>['required','string','regex:/^[0-9 +()\-]{8,24}$/'],
            'email'=>['nullable','email','max:190'],
            'model'=>['nullable',Rule::in($models)],
            'preferred_date'=>['required','date_format:Y-m-d','after:today','before_or_equal:'.now('America/Montevideo')->addDays(90)->format('Y-m-d')],
            'preferred_time'=>['required',Rule::in(self::TIMES)],
            'comments'=>['nullable','string','max:1000'],
            'accept_privacy'=>['accepted'],
        ],[
            'preferred_date.after'=>'Elegí una fecha futura.',
            'preferred_date.before_or_equal'=>'La agenda permite reservar hasta 90 días hacia adelante.',
            'accept_privacy.accepted'=>'Tenés que aceptar la política de privacidad.',
        ]);
        $requestUuid=(string)Str::uuid();$idempotencyKey=(string)Str::uuid();
        $accountEmail=(string)($request->user()?->email??'');
        try{$response=$panel->createVisit([
            'request_uuid'=>$requestUuid,
            'full_name'=>Str::squish((string)$validated['full_name']),
            'phone'=>(string)$validated['phone'],
            'email'=>strtolower(trim((string)($validated['email']??$accountEmail))),
            'model'=>(string)($validated['model']??''),
            'preferred_date'=>(string)$validated['preferred_date'],
            'preferred_time'=>(string)$validated['preferred_time'],
            'comments'=>trim((string)($validated['comments']??'')),
        ],$idempotencyKey);}catch(\Throwable$e){report($e);return back()->withInput()->withErrors(['visit'=>'No pudimos registrar la visita. Intentá nuevamente en unos minutos.']);}
        if($response->status()!==201||$response->json('data.request_uuid')!==$requestUuid)return back()->withInput()->withErrors(['visit'=>'No pudimos registrar la visita. Intentá nuevamente en unos minutos.']);
        return redirect()->route('visits.create')->with('status','Recibimos tu solicitud. Te contactaremos por WhatsApp para confirmar el horario.');
    }
}
