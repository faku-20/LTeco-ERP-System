<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartManager;
use App\Services\PanelCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class CartController extends Controller
{
    public function index(Request $request, CartManager $manager): View
    {
        $cart = $manager->active($request, false);

        return view('cart.index', [
            'cart' => $cart?->load('items'),
            'guest' => ! $request->user(),
        ]);
    }

    public function store(
        Request $request,
        PanelCatalogService $catalog,
        CartManager $manager,
    ): RedirectResponse {
        $max = max(1, (int) config('storefront_cart.max_quantity', 10));
        $validated = $request->validate([
            'variant_id' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.$max],
        ]);

        $variant = $this->variantOrFail($catalog, (string) $validated['variant_id']);
        $quantity = (int) ($validated['quantity'] ?? 1);
        $available = (int) ($variant['availability']['quantity'] ?? 0);
        $cart = $manager->active($request, true);

        if (! $cart) {
            throw ValidationException::withMessages([
                'cart' => 'No pudimos iniciar el carrito. Recargá la página e intentá nuevamente.',
            ]);
        }

        DB::transaction(function () use ($cart, $variant, $quantity, $available, $max): void {
            $lockedCart = Cart::query()
                ->whereKey($cart->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $lockedCart) {
                throw ValidationException::withMessages([
                    'cart' => 'El carrito ya no está activo. Recargá la página para continuar.',
                ]);
            }

            $item = $lockedCart->items()
                ->where('variant_id', $variant['variant_id'])
                ->lockForUpdate()
                ->first();
            $newQuantity = $quantity + (int) ($item?->quantity ?? 0);

            if ($newQuantity > $max || $newQuantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => 'No hay tantas unidades disponibles de esta variante.',
                ]);
            }

            $lockedCart->items()->updateOrCreate(
                ['variant_id' => $variant['variant_id']],
                [
                    'quantity' => $newQuantity,
                    'model' => (string) $variant['model'],
                    'battery_ah' => $variant['battery_ah'] ?? null,
                    'color' => trim((string) ($variant['color'] ?? '')) ?: 'A confirmar',
                    'expected_gross' => $variant['price']['gross'],
                    'currency' => $variant['price']['currency'],
                    'catalog_version' => (string) ($variant['version'] ?? $variant['catalog_version'] ?? 'panel-live'),
                ],
            );
        }, 3);

        return redirect()->route('cart.index')->with('status', 'Agregamos la moto al carrito.');
    }

    public function update(
        Request $request,
        CartItem $item,
        PanelCatalogService $catalog,
        CartManager $manager,
    ): RedirectResponse {
        $max = max(1, (int) config('storefront_cart.max_quantity', 10));
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$max],
        ]);
        $cart = $manager->active($request, false);

        abort_unless(
            $cart && $item->cart_id === $cart->id && $cart->status === 'active',
            404,
        );

        $variant = $this->variantOrFail($catalog, $item->variant_id);
        $quantity = (int) $validated['quantity'];
        if ($quantity > (int) ($variant['availability']['quantity'] ?? 0)) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad solicitada ya no está disponible.',
            ]);
        }

        DB::transaction(function () use ($cart, $item, $variant, $quantity): void {
            $lockedCart = Cart::query()
                ->whereKey($cart->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            $lockedItem = CartItem::query()
                ->whereKey($item->id)
                ->where('cart_id', $cart->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedCart || ! $lockedItem) {
                abort(404);
            }

            $lockedItem->update([
                'quantity' => $quantity,
                'model' => (string) $variant['model'],
                'battery_ah' => $variant['battery_ah'] ?? null,
                'color' => trim((string) ($variant['color'] ?? '')) ?: 'A confirmar',
                'expected_gross' => $variant['price']['gross'],
                'currency' => $variant['price']['currency'],
                'catalog_version' => (string) ($variant['version'] ?? $variant['catalog_version'] ?? 'panel-live'),
            ]);
        }, 3);

        return back()->with('status', 'Actualizamos el carrito.');
    }

    public function destroy(
        Request $request,
        CartItem $item,
        CartManager $manager,
    ): RedirectResponse {
        $cart = $manager->active($request, false);
        abort_unless(
            $cart && $item->cart_id === $cart->id && $cart->status === 'active',
            404,
        );

        CartItem::query()
            ->whereKey($item->id)
            ->where('cart_id', $cart->id)
            ->delete();

        return back()->with('status', 'Quitamos el artículo del carrito.');
    }

    /** @return array<string,mixed> */
    private function variantOrFail(PanelCatalogService $catalog, string $variantId): array
    {
        try {
            $variant = $catalog->findVariant($variantId);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'variant_id' => 'No pudimos validar el stock en este momento. Intentá nuevamente en unos minutos.',
            ]);
        }

        if ($variant === null) {
            throw ValidationException::withMessages([
                'variant_id' => 'La variante ya no está disponible.',
            ]);
        }

        return $variant;
    }
}
