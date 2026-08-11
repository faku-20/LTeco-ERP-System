<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CartManager
{
    public function active(Request $request, bool $create = false): ?Cart
    {
        if ($request->user()) {
            return $this->userCart($request->user(), $create);
        }

        return $this->guestCart($request, $create);
    }

    public function count(Request $request): int
    {
        return (int) ($this->active($request)?->items()->sum('quantity') ?? 0);
    }

    /** @return list<string> */
    public function mergeGuestIntoUser(Request $request, User $user, PanelCatalogService $catalog): array
    {
        $guestHash = $this->guestHash($request);
        if ($guestHash === null) {
            return [];
        }

        try {
            $live = $catalog->variants()->keyBy('variant_id');
        } catch (\Throwable) {
            return [
                'No pudimos actualizar el carrito invitado todavía. Conservamos sus productos para reintentarlo.',
            ];
        }

        $warnings = [];
        $max = max(1, (int) config('storefront_cart.max_quantity', 10));
        $merged = false;

        DB::transaction(function () use (
            $guestHash,
            $user,
            $live,
            $max,
            &$warnings,
            &$merged,
        ): void {
            // Serializa fusiones concurrentes de la misma cuenta para evitar
            // crear dos carritos activos durante dos inicios de sesión.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $guest = Cart::query()
                ->whereNull('user_id')
                ->where('guest_token_hash', $guestHash)
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('guest_expires_at')
                        ->orWhere('guest_expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            if (! $guest) {
                return;
            }

            $guest->load(['items' => fn ($query) => $query->lockForUpdate()]);

            $target = Cart::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $target ??= Cart::query()->create([
                'user_id' => $user->id,
                'status' => 'active',
                'currency' => 'UYU',
                'expires_at' => now()->addMinutes($this->lifetimeMinutes()),
            ]);

            foreach ($guest->items as $item) {
                $variant = $live->get($item->variant_id);
                $available = is_array($variant)
                    ? (int) ($variant['availability']['quantity'] ?? 0)
                    : 0;

                if (! is_array($variant) || $available < 1) {
                    $warnings[] = "Quitamos {$item->model} del carrito porque ya no está disponible.";
                    continue;
                }

                $existing = $target->items()
                    ->where('variant_id', $item->variant_id)
                    ->lockForUpdate()
                    ->first();

                $requested = (int) ($existing?->quantity ?? 0) + (int) $item->quantity;
                $quantity = min($max, $available, $requested);

                if ($quantity < $requested) {
                    $warnings[] = "Ajustamos la cantidad de {$item->model} al stock disponible.";
                }

                if ((string) $item->expected_gross !== (string) ($variant['price']['gross'] ?? '')) {
                    $warnings[] = "Actualizamos el precio de {$item->model} con el valor vigente del catálogo.";
                }

                $target->items()->updateOrCreate(
                    ['variant_id' => $item->variant_id],
                    [
                        'quantity' => $quantity,
                        'model' => (string) $variant['model'],
                        'battery_ah' => $variant['battery_ah'] ?? null,
                        'color' => trim((string) ($variant['color'] ?? '')) ?: 'A confirmar',
                        'expected_gross' => $variant['price']['gross'],
                        'currency' => $variant['price']['currency'],
                        'catalog_version' => (string) ($variant['version'] ?? $variant['catalog_version'] ?? 'panel-live'),
                    ],
                );
            }

            $guest->update([
                'status' => 'merged',
                'guest_expires_at' => now(),
                'expires_at' => now(),
            ]);
            $merged = true;
        }, 3);

        if ($merged) {
            $this->forgetCookie($request);
        }

        return array_values(array_unique($warnings));
    }

    public function forgetCookie(Request $request): void
    {
        $name = (string) config('storefront_cart.cookie_name');
        $request->cookies->remove($name);
        cookie()->queue(cookie()->forget($name));
    }

    private function userCart(User $user, bool $create): ?Cart
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if ($cart || ! $create) {
            return $cart;
        }

        return Cart::query()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'currency' => 'UYU',
            'expires_at' => now()->addMinutes($this->lifetimeMinutes()),
        ]);
    }

    private function guestCart(Request $request, bool $create): ?Cart
    {
        $hash = $this->guestHash($request);
        $cart = $hash === null
            ? null
            : Cart::query()
                ->whereNull('user_id')
                ->where('guest_token_hash', $hash)
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('guest_expires_at')
                        ->orWhere('guest_expires_at', '>', now());
                })
                ->first();

        if ($cart || ! $create) {
            return $cart;
        }

        $token = bin2hex(random_bytes(32));
        $minutes = $this->lifetimeMinutes();
        $expires = now()->addMinutes($minutes);
        $cart = Cart::query()->create([
            'user_id' => null,
            'guest_token_hash' => hash('sha256', $token),
            'status' => 'active',
            'currency' => 'UYU',
            'expires_at' => $expires,
            'guest_expires_at' => $expires,
        ]);
        $this->queueCookie($request, $token, $minutes);

        return $cart;
    }

    private function guestHash(Request $request): ?string
    {
        $token = $request->cookie((string) config('storefront_cart.cookie_name'));

        return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1
            ? hash('sha256', $token)
            : null;
    }

    private function queueCookie(Request $request, string $token, int $minutes): void
    {
        $secure = (bool) config('storefront_cart.secure_cookie', true)
            && ($request->isSecure() || app()->environment('testing'));

        cookie()->queue(cookie(
            (string) config('storefront_cart.cookie_name'),
            $token,
            $minutes,
            '/',
            null,
            $secure,
            true,
            false,
            'lax',
        ));
    }

    private function lifetimeMinutes(): int
    {
        return max(60, (int) config('storefront_cart.lifetime_minutes', 43200));
    }
}
