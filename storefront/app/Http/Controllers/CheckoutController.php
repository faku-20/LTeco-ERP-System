<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Rules\UruguayCedula;
use App\Rules\UruguayRut;
use App\Services\CheckoutCancellationService;
use App\Services\CheckoutPanelOrderService;
use App\Services\CheckoutReservationService;
use App\Services\CheckoutSimulatedPaymentService;
use App\Services\ConsentRecorder;
use App\Services\PanelCatalogService;
use App\Services\SecurityAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class CheckoutController extends Controller
{
    public function index(Request $request, PanelCatalogService $catalog): View|RedirectResponse
    {
        $cart = $this->cart($request);

        if ($cart === null || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')
                ->withErrors(['cart' => 'Agregá al menos una moto antes de continuar.']);
        }

        try {
            $live = $catalog->variants()->keyBy('variant_id');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('cart.index')
                ->withErrors(['cart' => 'No pudimos verificar precio y stock en este momento. Probá nuevamente en unos minutos.']);
        }

        return view('checkout.index', [
            'cart' => $cart,
            'liveVariants' => $live,
            'profile' => $request->user()->profile,
            'paymentSimulatorEnabled' => $this->paymentSimulatorEnabled(),
            'address' => $request->user()->addresses()
                ->where('type', 'billing')
                ->where('is_primary', true)
                ->latest('id')
                ->first(),
        ]);
    }

    public function store(
        Request $request,
        PanelCatalogService $catalog,
        CheckoutReservationService $reservations,
        CheckoutPanelOrderService $panelOrders,
        CheckoutSimulatedPaymentService $simulatedPayments,
        ConsentRecorder $consents,
        SecurityAuditLogger $audit,
    ): RedirectResponse {
        $paymentMethods = ['cash'];
        if ($this->paymentSimulatorEnabled()) {
            $paymentMethods[] = 'card';
        }

        $validated = $request->validate([
            'customer_type' => ['required', Rule::in(['consumer', 'business'])],
            'legal_name' => [
                'exclude_unless:customer_type,business',
                'required',
                'string',
                'max:190',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9][0-9\s().-]{6,29}$/',
            ],
            'cedula' => ['exclude_unless:customer_type,consumer', 'required', new UruguayCedula],
            'rut' => ['exclude_unless:customer_type,business', 'required', new UruguayRut],
            'address_line1' => [
                'required',
                'string',
                'max:190',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'address_line2' => [
                'nullable',
                'string',
                'max:190',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'city' => [
                'required',
                'string',
                'max:100',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'department' => [
                'required',
                'string',
                'max:100',
                'not_regex:/[<>\x00-\x1F\x7F]/u',
            ],
            'postal_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z -]*$/'],
            'payment_method' => ['required', Rule::in($paymentMethods)],
            'accept_terms' => ['accepted'],
        ], [
            'phone.regex' => 'Ingresá un teléfono válido.',
            'payment_method.in' => 'Elegí una forma de pago válida.',
            'accept_terms.accepted' => 'Tenés que aceptar los términos de compra y privacidad.',
        ]);

        $cart = $this->cart($request);
        if ($cart === null || $cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'El carrito está vacío.']);
        }

        try {
            $live = $catalog->variants()->keyBy('variant_id');
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'cart' => 'No pudimos verificar precio y stock en este momento. Probá nuevamente en unos minutos.',
            ]);
        }

        $variantIds = [];
        $currency = null;
        $changed = false;

        foreach ($cart->items as $item) {
            $variant = $live->get($item->variant_id);
            if (! is_array($variant)
                || (int) ($variant['availability']['quantity'] ?? 0) < $item->quantity
            ) {
                throw ValidationException::withMessages([
                    'cart' => 'Una de las motos del carrito ya no tiene stock suficiente.',
                ]);
            }

            $gross = (string) ($variant['price']['gross'] ?? '');
            $variantCurrency = (string) ($variant['price']['currency'] ?? '');
            if ($gross === '' || ! preg_match('/^[A-Z]{3}$/', $variantCurrency)) {
                throw ValidationException::withMessages([
                    'cart' => 'Una de las variantes devolvió información comercial inválida.',
                ]);
            }

            if ((string) $item->expected_gross !== $gross || $item->currency !== $variantCurrency) {
                $item->update([
                    'expected_gross' => $gross,
                    'currency' => $variantCurrency,
                ]);
                $changed = true;
            }

            $currency ??= $variantCurrency;
            if ($currency !== $variantCurrency) {
                throw ValidationException::withMessages([
                    'cart' => 'No se pueden mezclar monedas en una compra.',
                ]);
            }

            for ($i = 0; $i < $item->quantity; $i++) {
                $variantIds[] = $item->variant_id;
            }
        }

        if (count($variantIds) > (int) config('storefront_cart.max_units_per_order', 10)) {
            throw ValidationException::withMessages([
                'cart' => 'Superaste el máximo de unidades permitidas por pedido.',
            ]);
        }

        if ($changed) {
            return redirect()->route('checkout.index')
                ->withErrors(['cart' => 'Actualizamos un precio del carrito. Revisá el total antes de confirmar.']);
        }

        $user = $request->user();
        $customerType = (string) $validated['customer_type'];
        $cedula = $customerType === 'consumer'
            ? (preg_replace('/\D+/', '', (string) ($validated['cedula'] ?? '')) ?: null)
            : null;
        $rut = $customerType === 'business'
            ? (preg_replace('/\D+/', '', (string) ($validated['rut'] ?? '')) ?: null)
            : null;
        $legalName = $customerType === 'business'
            ? Str::squish((string) ($validated['legal_name'] ?? ''))
            : null;
        $phone = Str::squish((string) $validated['phone']);
        $line1 = Str::squish((string) $validated['address_line1']);
        $line2 = isset($validated['address_line2']) && trim((string) $validated['address_line2']) !== ''
            ? Str::squish((string) $validated['address_line2'])
            : null;
        $city = Str::squish((string) $validated['city']);
        $department = Str::squish((string) $validated['department']);
        $postalCode = isset($validated['postal_code']) && trim((string) $validated['postal_code']) !== ''
            ? trim((string) $validated['postal_code'])
            : null;

        $blindKey = (string) config('storefront_privacy.blind_index_key');
        if ($blindKey === '') {
            throw new RuntimeException('La protección de datos del checkout no está configurada.');
        }

        try {
            $order = DB::transaction(function () use (
                $cart,
                $cedula,
                $rut,
                $legalName,
                $phone,
                $line1,
                $line2,
                $city,
                $department,
                $postalCode,
                $currency,
                $user,
                $customerType,
                $validated,
                $blindKey,
            ): Order {
                $lockedCart = Cart::query()
                    ->whereKey($cart->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($lockedCart === null) {
                    throw ValidationException::withMessages([
                        'cart' => 'El carrito cambió mientras confirmabas la compra. Revisalo nuevamente.',
                    ]);
                }

                $existingOrder = Order::query()
                    ->where('cart_id', $lockedCart->id)
                    ->where('user_id', $user->id)
                    ->whereNotIn('status', ['cancelled', 'expired', 'failed'])
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder !== null) {
                    throw ValidationException::withMessages([
                        'cart' => 'Este carrito ya tiene una reserva iniciada.',
                    ]);
                }

                CustomerProfile::query()->updateOrCreate(['user_id' => $user->id], [
                    'customer_type' => $customerType,
                    'legal_name' => $legalName,
                    'phone_encrypted' => $phone,
                    'cedula_encrypted' => $cedula,
                    'cedula_blind_index' => $cedula !== null ? hash_hmac('sha256', $cedula, $blindKey) : null,
                    'rut_encrypted' => $rut,
                    'rut_blind_index' => $rut !== null ? hash_hmac('sha256', $rut, $blindKey) : null,
                    'status' => 'active',
                ]);

                CustomerAddress::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'billing')
                    ->update(['is_primary' => false]);

                CustomerAddress::query()->create([
                    'user_id' => $user->id,
                    'type' => 'billing',
                    'line1_encrypted' => $line1,
                    'line2_encrypted' => $line2,
                    'city_encrypted' => $city,
                    'department_encrypted' => $department,
                    'postal_code_encrypted' => $postalCode,
                    'country' => 'UY',
                    'is_primary' => true,
                ]);

                return Order::query()->create([
                    'user_id' => $user->id,
                    'cart_id' => $lockedCart->id,
                    'status' => 'reservation_pending',
                    'payment_method' => $validated['payment_method'],
                    'currency' => (string) $currency,
                    'billing_snapshot_encrypted' => [
                        'customer_type' => $customerType,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'phone' => $phone,
                        'cedula' => $cedula,
                        'rut' => $rut,
                        'legal_name' => $legalName,
                        'address' => [
                            'line1' => $line1,
                            'line2' => $line2,
                            'city' => $city,
                            'department' => $department,
                            'postal_code' => $postalCode,
                            'country' => 'UY',
                        ],
                    ],
                    'terms_snapshot' => [
                        'accepted_at' => now()->toISOString(),
                        'version' => '2026-07',
                    ],
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'cedula' => 'La cédula o RUT ya está vinculado a otra cuenta. Contactanos para verificarla.',
                ]);
            }

            throw $exception;
        }

        try {
            $order = $reservations->reserve($order, $variantIds);
        } catch (RuntimeException $exception) {
            return redirect()->route('checkout.index')
                ->withInput()
                ->withErrors(['checkout' => $exception->getMessage()]);
        }

        try {
            $order = $panelOrders->sync($order);
        } catch (RuntimeException $exception) {
            Log::warning('La reserva se creó, pero el pedido quedó pendiente de sincronizar con el panel.', [
                'order_uuid' => $order->public_uuid,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($order->payment_method === 'card') {
            try {
                $order = $simulatedPayments->confirm($order);
            } catch (RuntimeException $exception) {
                Log::warning('El pedido se registró, pero el simulador de pago no pudo aprobarlo.', [
                    'order_uuid' => $order->public_uuid,
                    'error' => $exception->getMessage(),
                ]);

                return redirect()->route('orders.show', ['order' => $order->public_uuid])
                    ->withErrors(['checkout' => 'El pedido quedó reservado, pero el simulador de pago no pudo aprobarlo. Probá nuevamente o contactanos.']);
            }
        }

        $cart->update([
            'status' => 'converted',
            'expires_at' => now(),
        ]);

        $consents->record(
            $request,
            $user,
            'checkout_terms',
            '2026-07',
            'checkout-terms-and-privacy:2026-07',
        );
        $audit->record(
            $request,
            'order.created',
            'order',
            $order->public_uuid,
            ['order_uuid' => $order->public_uuid],
            $user,
        );

        return redirect()->route('orders.show', ['order' => $order->public_uuid]);
    }

    public function show(Request $request, string $order): View
    {
        $model = Order::query()
            ->with(['items', 'pickupCoordinations'])
            ->where('public_uuid', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return view('checkout.show', ['order' => $model]);
    }

    public function receipt(Request $request, string $order): View
    {
        $model = Order::query()
            ->with(['items', 'user'])
            ->where('public_uuid', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless(
            in_array($model->status, ['paid', 'preparing', 'ready_for_pickup', 'delivered'], true)
                || $model->panel_sale_id !== null,
            404,
        );

        return view('checkout.receipt', ['order' => $model]);
    }

    public function cancel(
        Request $request,
        string $order,
        CheckoutCancellationService $cancellations,
    ): RedirectResponse {
        $model = Order::query()
            ->where('public_uuid', $order)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $cancellations->cancel($model);
        } catch (RuntimeException $exception) {
            return redirect()->route('orders.show', ['order' => $model->public_uuid])
                ->withErrors(['cancel' => $exception->getMessage()]);
        }

        return redirect()->route('orders.show', ['order' => $model->public_uuid])
            ->with('status', 'La reserva fue cancelada y la unidad volvió a quedar disponible.');
    }

    private function cart(Request $request): ?Cart
    {
        return Cart::query()
            ->with('items')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    private function paymentSimulatorEnabled(): bool
    {
        return (bool) config('storefront.payment_simulator.enabled', false)
            && ! app()->environment('production');
    }
}
