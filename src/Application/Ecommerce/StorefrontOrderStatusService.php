<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class StorefrontOrderStatusService
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return array<string,mixed> */
    public function find(string $orderUuid): array
    {
        $orderUuid = strtolower(trim($orderUuid));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $orderUuid) !== 1) {
            throw new StorefrontApiException(404, 'order_not_found', 'El pedido no existe.');
        }

        $stmt = $this->connection->pdo()->prepare('SELECT IdPedido,StorefrontOrderUuid,NumeroPedido,Estado,EstadoPago,IdVenta,PagadoEn,EntregadoEn,ActualizadoEn FROM ecommerce_pedido WHERE StorefrontOrderUuid=? LIMIT 1');
        $stmt->execute([$orderUuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StorefrontApiException(404, 'order_not_found', 'El pedido no existe.');
        }

        return ['data' => [
            'panel_order_id' => (int) $row['IdPedido'],
            'order_uuid' => (string) $row['StorefrontOrderUuid'],
            'order_number' => (string) $row['NumeroPedido'],
            'status' => (string) $row['Estado'],
            'payment_status' => (string) $row['EstadoPago'],
            'panel_sale_id' => $row['IdVenta'] !== null ? (int) $row['IdVenta'] : null,
            'paid_at' => $this->iso($row['PagadoEn']),
            'delivered_at' => $this->iso($row['EntregadoEn']),
            'updated_at' => $this->iso($row['ActualizadoEn']),
        ]];
    }

    private function iso(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        $local = new \DateTimeImmutable((string) $value, new \DateTimeZone('America/Montevideo'));
        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
