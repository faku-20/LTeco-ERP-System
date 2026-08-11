<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\SecurityAuditLogger;

final class EmailVerificationController extends Controller
{
    public function notice(
        Request $request
    ): View|RedirectResponse {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route(
                'account.dashboard'
            );
        }

        return view('auth.verify-email');
    }

    public function verify(
        EmailVerificationRequest $request,SecurityAuditLogger $audit
    ): RedirectResponse {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
            $audit->record($request,'account.email_verified','user',$request->user()->public_uuid,['result'=>'verified'],$request->user());
        }

        return redirect()->intended(route('account.dashboard'))
            ->with(
                'status',
                'email-verified',
            );
    }

    public function send(Request $request,SecurityAuditLogger $audit): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route(
                'account.dashboard'
            );
        }

        $request
            ->user()
            ->sendEmailVerificationNotification();
        $audit->record($request,'account.verification_resent','user',$request->user()->public_uuid,['result'=>'sent'],$request->user());

        return back()->with(
            'status',
            'verification-link-sent',
        );
    }
}
