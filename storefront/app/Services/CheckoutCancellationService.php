<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CheckoutCancellationService
{
    private const CANCELLABLE_STATUSES = [
        'awaiting_payment',
        'pickup_coordination_pending',
    ];

    public function __construct(private readonly PanelApiClient $panel) {}

    public function cancel(Order $order, string $terminalStatus = 'cancelled'): Order
    {
        if (!in_array($terminalStatus, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('El estado final solicitado no es válido.');
        }

        $order->refresh();
        if ($order->status === $terminalStatus) return $order;
        if (!in_array($order->status, self::CANCELLABLE_STATUSES, true)
            || $order->panel_reservation_id === null) {
            throw new RuntimeException('Este pedido ya no se puede cancelar.');
        }

        $event = OutboxEvent::query()->firstOrCreate(
            [
                'aggregate_type' => 'order',
                'aggregate_uuid' => $order->public_uuid,
                'event_type' => 'panel.reservation.release',
            ],
            [
                'payload' => [
                    'reservation_id' => $order->panel_reservation_id,
                    'terminal_status' => $terminalStatus,
                ],
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        $response = $this->panel->releaseReservation(
            (string) $order->panel_reservation_id,
            $event->idempotency_key,
        );
        if ($response->status() !== 200) {
            $code = (string) $response->json('error.code', 'panel_unavailable');
            $event->forceFill([
                'status' => 'pending',
                'attempts' => $event->attempts + 1,
                'last_error' => $code,
                'available_at' => now()->addMinute(),
            ])->save();
            throw new RuntimeException('No fue posible liberar la reserva: ' . $code);
        }

        return DB::transaction(function () use ($order, $event, $terminalStatus): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->status, ['cancelled', 'expired'], true)) return $locked;
            if (!in_array($locked->status, self::CANCELLABLE_STATUSES, true)) {
                throw new RuntimeException('El pedido cambió mientras se liberaba la reserva.');
            }

            $locked->forceFill([
                'status' => $terminalStatus,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $locked->pickupCoordinations()->whereNotIn('status', ['completed', 'cancelled', 'expired'])
                ->update(['status' => $terminalStatus]);
            $event->forceFill([
                'status' => 'processed',
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();

            return $locked->fresh(['items', 'pickupCoordinations']);
        });
    }
}
