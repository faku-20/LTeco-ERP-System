<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

final class ReservationService
{
    public function __construct(private ReservationDataSource $source) {}

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(array $payload, string $idempotencyKey, string $requestHash): array
    {
        $orderUuid = trim((string) ($payload['order_uuid'] ?? ''));
        $variantIds = $payload['variant_ids'] ?? null;
        $paymentMethod = trim((string) ($payload['payment_method'] ?? ''));
        $ttl = (int) ($payload['ttl_seconds'] ?? 0);
        if (!$this->isUuid($orderUuid) || !is_array($variantIds) || $variantIds === [] || count($variantIds) > 10) {
            throw new StorefrontApiException(422, 'invalid_reservation', 'La reserva solicitada no es válida.');
        }
        $variantIds = array_values(array_map('strval', $variantIds));
        foreach ($variantIds as $id) {
            if (preg_match('/^[a-f0-9]{64}$/', $id) !== 1) {
                throw new StorefrontApiException(422, 'invalid_variant', 'Una variante solicitada no es válida.');
            }
        }
        if (!in_array($paymentMethod, ['cash', 'card'], true)) {
            throw new StorefrontApiException(422, 'invalid_payment_method', 'El medio de pago no es válido.');
        }
        $validTtl = $paymentMethod === 'cash'
            ? ($ttl >= 14400 && $ttl <= 57600)
            : ($ttl === 1800);
        if (!$validTtl) {
            throw new StorefrontApiException(422, 'invalid_reservation_ttl', 'El plazo de reserva no corresponde al medio de pago.');
        }

        return $this->source->reserve($orderUuid, $variantIds, $paymentMethod, $ttl, $idempotencyKey, $requestHash);
    }

    /** @return array<string,mixed> */
    public function release(string $reservationId, string $idempotencyKey, string $requestHash): array
    {
        if (!$this->isUuid($reservationId)) {
            throw new StorefrontApiException(404, 'reservation_not_found', 'La reserva no existe.');
        }
        return $this->source->release(strtolower($reservationId), $idempotencyKey, $requestHash);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
