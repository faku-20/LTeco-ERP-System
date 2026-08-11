<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use App\Services\ConsentRecorder;
use App\Services\SecurityAuditLogger;
use App\Services\CartManager;
use App\Services\PanelCatalogService;

final class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(
            (bool) config(
                'storefront_auth.registration_enabled'
            ),
            404,
        );

        return view('auth.register');
    }

    public function store(Request $request,ConsentRecorder $consents,SecurityAuditLogger $audit,CartManager $cartManager,PanelCatalogService $catalog): RedirectResponse
    {
        abort_unless(
            (bool) config(
                'storefront_auth.registration_enabled'
            ),
            404,
        );

        $validated = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],
            'accept_privacy'=>['accepted'],
        ]);

        $user = User::query()->create([
            'first_name' => Str::squish(
                (string) $validated['first_name']
            ),
            'last_name' => Str::squish(
                (string) $validated['last_name']
            ),
            'email' => Str::lower(
                trim((string) $validated['email'])
            ),
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();
        $warnings = $cartManager->mergeGuestIntoUser($request, $user, $catalog);
        if ($warnings !== []) {
            $request->session()->flash('cart_merge_warnings', $warnings);
        }
        $version='2026-07';$consents->record($request,$user,'account_privacy',$version,'privacy-policy:'.$version);
        $audit->record($request,'account.registered','user',$user->public_uuid,['result'=>'created'],$user);

        return redirect()->route('catalogo')->with('status', 'Creamos tu cuenta. Revisá tu correo para verificarla y poder finalizar la compra.');
    }
}
