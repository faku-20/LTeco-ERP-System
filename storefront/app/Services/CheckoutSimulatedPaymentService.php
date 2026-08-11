<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CheckoutSimulatedPaymentService
{
    private PanelApiClient $panel;

    public function __construct(PanelApiClient $panel)
    {
        $this->panel = $panel;
    }

    public function confirm(Order $order): Order
    {
        if (! $this->simulatorEnabled()) {
            throw new RuntimeException('El pago con tarjeta no está disponible.');
        }
        if ($order->payment_method !== 'card') {
            throw new RuntimeException('El simulador sólo aplica a pagos con tarjeta.');
        }
        if ($order->panel_order_id === null) {
            throw new RuntimeException('El pedido todavía no fue registrado en el panel.');
        }
        if ($order->panel_sale_id !== null || $order->status === 'paid') {
            return $order;
        }

        $event = OutboxEvent::query()->firstOrCreate(
            ['aggregate_type' => 'order', 'aggregate_uuid' => $order->public_uuid, 'event_type' => 'panel.payment.simulate'],
            [
                'payload' => ['gateway' => 'card_simulator'],
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        $response = $this->panel->simulateCardPayment($order->public_uuid, $event->idempotency_key);
        if ($response->status() !== 200 || $response->json('data.order_uuid') !== $order->public_uuid) {
            $code = (string) $response->json('error.code', 'panel_unavailable');
            $event->forceFill([
                'status' => 'pending',
                'attempts' => $event->attempts + 1,
                'last_error' => $code,
                'available_at' => now()->addMinute(),
            ])->save();
            throw new RuntimeException('No fue posible aprobar el pago simulado: '.$code);
        }

        $data = $response->json('data');

        return DB::transaction(function () use ($order, $event, $data): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->forceFill([
                'status' => 'paid',
                'panel_sale_id' => isset($data['panel_sale_id']) ? (int) $data['panel_sale_id'] : $locked->panel_sale_id,
                'panel_order_number' => trim((string) ($data['order_number'] ?? '')) ?: $locked->panel_order_number,
                'paid_at' => !empty($data['paid_at']) ? $data['paid_at'] : now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            PaymentAttempt::query()->firstOrCreate(
                ['order_id' => $locked->id, 'gateway' => 'card_simulator', 'gateway_reference' => 'SIM-CARD-'.$locked->panel_order_id],
                [
                    'status' => 'approved',
                    'amount' => $locked->total,
                    'currency' => $locked->currency,
                    'sanitized_payload' => ['simulated' => true],
                    'authorized_at' => now(),
                ],
            );

            $event->forceFill([
                'status' => 'processed',
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked->fresh(['items', 'pickupCoordinations']);
        });
    }

    private function simulatorEnabled(): bool
    {
        return (bool) config('storefront.payment_simulator.enabled', false)
            && ! app()->environment('production');
    }
}
