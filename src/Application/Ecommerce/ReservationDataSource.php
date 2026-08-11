<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

interface ReservationDataSource
{
    /** @param list<string> $variantIds @return array<string,mixed> */
    public function reserve(string $orderUuid, array $variantIds, string $paymentMethod, int $ttlSeconds, string $idempotencyKey, string $requestHash): array;

    /** @return array<string,mixed> */
    public function release(string $reservationId, string $idempotencyKey, string $requestHash): array;
}
