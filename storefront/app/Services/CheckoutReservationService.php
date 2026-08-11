<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\PickupCoordination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CheckoutReservationService
{
    public function __construct(private readonly PanelApiClient $panel) {}

    /** @param list<string> $variantIds */
    public function reserve(Order $order, array $variantIds): Order
    {
        if ($order->status !== 'reservation_pending' || !in_array($order->payment_method, ['cash', 'card'], true)) {
            throw new RuntimeException('El pedido no está listo para reservar stock.');
        }
        $event = OutboxEvent::query()->firstOrCreate(
            ['aggregate_type' => 'order', 'aggregate_uuid' => $order->public_uuid, 'event_type' => 'panel.reservation.create'],
            [
                'payload' => ['variant_ids' => array_values($variantIds), 'payment_method' => $order->payment_method],
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ],
        );

        $response = $this->panel->createReservation(
            $order->public_uuid,
            array_values($variantIds),
            (string) $order->payment_method,
            $event->idempotency_key,
        );
        if ($response->status() !== 201) {
            $code = (string) $response->json('error.code', 'panel_unavailable');
            $event->forceFill(['attempts' => $event->attempts + 1, 'last_error' => $code])->save();
            throw new RuntimeException('No fue posible reservar el stock: ' . $code);
        }
        $data = $response->json('data');
        $this->validateResponse($order, $variantIds, $data);

        return DB::transaction(function () use ($order, $event, $data): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'reservation_pending') {
                if ($locked->panel_reservation_id === $data['reservation_id']) return $locked;
                throw new RuntimeException('El pedido cambió mientras se reservaba el stock.');
            }
            $locked->forceFill([
                'status' => $locked->payment_method === 'cash' ? 'pickup_coordination_pending' : 'awaiting_payment',
                'currency' => $data['currency'],
                'subtotal' => $data['subtotal'],
                'discount' => $data['discount'],
                'total' => $data['total'],
                'panel_reservation_id' => $data['reservation_id'],
                'expires_at' => $data['expires_at'],
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            foreach ($data['items'] as $item) {
                $gross = (float) $item['gross'];
                $vatRate = (float) $item['vat_rate'];
                $vat = round($gross * $vatRate / (100 + $vatRate), 2);
                OrderItem::query()->create([
                    'order_id' => $locked->id,
                    'vehicle_id' => $item['vehicle_id'],
                    'product_id' => $item['product_id'],
                    'model' => $item['model'],
                    'battery_ah' => $item['battery_ah'],
                    'color' => $item['color'],
                    'gross' => $item['gross'],
                    'vat_included' => number_format($vat, 2, '.', ''),
                    'currency' => $data['currency'],
                    'vehicle_snapshot' => $item,
                ]);
            }
            if ($locked->payment_method === 'cash') {
                PickupCoordination::query()->create([
                    'order_id' => $locked->id,
                    'status' => 'pending_contact',
                    'reservation_expires_at' => $data['expires_at'],
                ]);
            }
            $event->forceFill(['status' => 'processed', 'attempts' => $event->attempts + 1, 'processed_at' => now(), 'last_error' => null])->save();
            return $locked->fresh(['items', 'pickupCoordinations']);
        });
    }

    /** @param list<string> $variantIds */
    private function validateResponse(Order $order, array $variantIds, mixed $data): void
    {
        if (!is_array($data)
            || ($data['order_uuid'] ?? null) !== $order->public_uuid
            || !Str::isUuid((string) ($data['reservation_id'] ?? ''))
            || ($data['status'] ?? null) !== 'active'
            || !isset($data['items'])
            || !is_array($data['items'])
            || count($data['items']) !== count($variantIds)
            || !preg_match('/^[A-Z]{3}$/', (string) ($data['currency'] ?? ''))
        ) {
            throw new RuntimeException('El panel devolvió una reserva inválida.');
        }
        $returned = array_map(static fn(array $item): string => (string) ($item['variant_id'] ?? ''), $data['items']);
        sort($returned);
        $expected = $variantIds;
        sort($expected);
        if ($returned !== $expected) throw new RuntimeException('El panel reservó una variante diferente.');
        foreach (['subtotal', 'discount', 'total'] as $amount) {
            if (!isset($data[$amount]) || !preg_match('/^\d+\.\d{2}$/', (string) $data[$amount])) {
                throw new RuntimeException('El panel devolvió importes inválidos.');
            }
        }
    }
}
