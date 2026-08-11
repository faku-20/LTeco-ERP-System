<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ContactController extends Controller
{
    public function show(): View
    {
        return view('pages.contacto');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return redirect()->route('contacto')->with(
                'status',
                'Recibimos tu consulta. Te responderemos por nuestros canales oficiales.',
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s.-]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'reason' => ['required', Rule::in(['modelos', 'compra', 'visita', 'postventa', 'otro'])],
            'message' => ['required', 'string', 'min:10', 'max:1200', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'website' => ['nullable', 'max:0'],
        ]);

        $contact = config('storefront_content.contact');
        $number = preg_replace('/\D+/', '', (string) ($contact['whatsapp_number'] ?? ''));
        if ($number === '') {
            return back()->withInput()->withErrors([
                'contact' => 'El canal de WhatsApp no está configurado. Usá los datos de contacto publicados.',
            ]);
        }

        $reasonLabels = [
            'modelos' => 'Consulta por modelos',
            'compra' => 'Consulta de compra',
            'visita' => 'Agendar una visita',
            'postventa' => 'Postventa',
            'otro' => 'Otra consulta',
        ];
        $lines = [
            'Hola, soy '.Str::squish((string) $validated['name']).'.',
            $reasonLabels[(string) $validated['reason']],
            'Teléfono: '.Str::squish((string) $validated['phone']),
        ];

        if (! empty($validated['email'])) {
            $lines[] = 'Correo: '.Str::lower(trim((string) $validated['email']));
        }

        $lines[] = '';
        $lines[] = Str::squish((string) $validated['message']);

        return redirect()->away(
            'https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines)),
        );
    }
}
