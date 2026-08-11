<?php

declare(strict_types=1);

use Lteco\Application\Ecommerce\ReservationDataSource;
use Lteco\Application\Ecommerce\ReservationService;
use Lteco\Application\Ecommerce\StorefrontApiException;

final class StorefrontReservationServiceTest
{
    public static function run(): void
    {
        $source = new class implements ReservationDataSource {
            public array $received = [];
            public function reserve(string $orderUuid, array $variantIds, string $paymentMethod, int $ttlSeconds, string $idempotencyKey, string $requestHash): array
            {
                $this->received = compact('orderUuid','variantIds','paymentMethod','ttlSeconds','idempotencyKey','requestHash');
                return ['data' => ['status' => 'active']];
            }
            public function release(string $reservationId, string $idempotencyKey, string $requestHash): array
            {
                return ['data' => ['reservation_id' => $reservationId, 'status' => 'released']];
            }
        };
        $service = new ReservationService($source);
        $result = $service->create([
            'order_uuid' => 'de9859c7-7e4c-4d24-a78f-2640bda7a38f',
            'variant_ids' => [str_repeat('a', 64)],
            'payment_method' => 'cash',
            'ttl_seconds' => 14400,
        ], 'a7c19e0c-1a42-4b7f-b63c-ddef241196b6', str_repeat('b', 64));
        Assert::same('Storefront reservation', 'crea reserva válida', 'active', $result['data']['status']);
        Assert::same('Storefront reservation', 'efectivo acepta 4 horas', 14400, $source->received['ttlSeconds']);

        try {
            $service->create([
                'order_uuid' => 'de9859c7-7e4c-4d24-a78f-2640bda7a38f',
                'variant_ids' => [str_repeat('a', 64)],
                'payment_method' => 'card',
                'ttl_seconds' => 86400,
            ], 'a7c19e0c-1a42-4b7f-b63c-ddef241196b6', str_repeat('b', 64));
            Assert::isTrue('Storefront reservation', 'rechaza TTL incorrecto', false);
        } catch (StorefrontApiException $e) {
            Assert::same('Storefront reservation', 'rechaza TTL incorrecto', 'invalid_reservation_ttl', $e->errorCode);
        }
    }
}
