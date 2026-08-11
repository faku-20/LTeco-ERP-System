<?php

declare(strict_types=1);

namespace App\Services;

final class StorefrontReadiness
{
    /** @return array<string,bool> */
    public function checks(): array
    {
        $production = app()->environment('production');
        $accounts = (bool) config('storefront_auth.accounts_enabled');
        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp');
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

        $mailConfigured = $mailer !== 'log'
            && filter_var((string) config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;

        if ($mailer === 'smtp') {
            $mailConfigured = $mailConfigured
                && trim((string) ($smtp['host'] ?? '')) !== ''
                && trim((string) ($smtp['username'] ?? '')) !== ''
                && trim((string) ($smtp['password'] ?? '')) !== '';
        }

        return [
            'debug_disabled' => ! $production || ! config('app.debug'),
            'https_public_url' => ! $production || str_starts_with((string) config('app.url'), 'https://'),
            'secure_session_cookie' => ! $production || (bool) config('session.secure'),
            'encrypted_session' => (bool) config('session.encrypt'),
            'trusted_proxies_restricted' => ! $production
                || ($trustedProxies !== '' && $trustedProxies !== '*'),
            'blind_index_key' => strlen((string) config('storefront_privacy.blind_index_key')) >= 32,
            'audit_hash_key' => strlen((string) config('storefront_privacy.audit_hash_key')) >= 32,
            'panel_api_secret' => strlen((string) config('storefront_api.panel.secret')) >= 32,
            'internal_api_secret' => strlen((string) config('storefront_api.incoming.secret')) >= 32,
            'mail_configured' => ! $accounts || $mailConfigured,
        ];
    }
}
