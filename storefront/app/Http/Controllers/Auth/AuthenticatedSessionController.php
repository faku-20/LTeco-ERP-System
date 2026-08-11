<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Services\SecurityAuditLogger;
use App\Services\CartManager;
use App\Services\PanelCatalogService;

final class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request,SecurityAuditLogger $audit,CartManager $cartManager,PanelCatalogService $catalog): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $credentials = [
            'email' => Str::lower(
                trim((string) $validated['email'])
            ),
            'password' => $validated['password'],
        ];

        if (
            ! Auth::attempt(
                $credentials,
                $request->boolean('remember'),
            )
        ) {
            $audit->record($request,'session.login_failed','session',null,['result'=>'rejected']);
            throw ValidationException::withMessages([
                'email' => (
                    'Las credenciales ingresadas '
                    . 'no son correctas.'
                ),
            ]);
        }

        $request->session()->regenerate();
        $warnings = $cartManager->mergeGuestIntoUser($request, $request->user(), $catalog);
        if ($warnings !== []) {
            $request->session()->flash('cart_merge_warnings', $warnings);
        }
        $audit->record($request,'session.login_succeeded','session',null,['result'=>'authenticated'],$request->user());

        return redirect()->intended(
            route('account.dashboard')
        );
    }

    public function destroy(Request $request,SecurityAuditLogger $audit): RedirectResponse
    {
        $audit->record($request,'session.logout','session',null,['result'=>'closed'],$request->user());
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('catalogo')
            ->with(
                'status',
                'session-closed',
            );
    }
}
