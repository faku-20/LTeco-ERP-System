<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Application\Ecommerce\ReservationDataSource;
use Lteco\Application\Ecommerce\StorefrontApiException;
use Lteco\Application\Ecommerce\VariantIdentity;
use Lteco\Domain\Venta\ConfiguracionComercial;
use Lteco\Infrastructure\Db\Connection;
use PDO;
use PDOException;

final class EcommerceReservationRepository implements ReservationDataSource
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function reserve(string $orderUuid, array $variantIds, string $paymentMethod, int $ttlSeconds, string $idempotencyKey, string $requestHash): array
    {
        return $this->reserveAttempt($orderUuid, $variantIds, $paymentMethod, $ttlSeconds, $idempotencyKey, $requestHash, 0);
    }

    private function reserveAttempt(string $orderUuid, array $variantIds, string $paymentMethod, int $ttlSeconds, string $idempotencyKey, string $requestHash, int $attempt): array
    {
        $this->pdo->beginTransaction();
        try {
            $cached = $this->claimIdempotency($idempotencyKey, 'reservation.create', $requestHash, 30);
            if ($cached !== null) {
                $this->pdo->commit();
                return $cached;
            }
            $this->expireActiveReservations();

            $existing = $this->pdo->prepare('SELECT ReservationId FROM storefront_reservation WHERE OrderUuid=? FOR UPDATE');
            $existing->execute([$orderUuid]);
            if ($existing->fetchColumn()) {
                throw new StorefrontApiException(409, 'order_already_reserved', 'El pedido ya tiene una reserva.');
            }

            $rows = $this->availableUnitsForUpdate($variantIds, $this->vatRate());
            $selected = [];
            foreach ($variantIds as $variantId) {
                $matchedIndex = null;
                foreach ($rows as $index => $row) {
                    if (isset($selected[(string) $row['IdVehiculo']])) continue;
                    $candidate = VariantIdentity::id(
                        (string) $row['Modelo'],
                        $row['CapacidadBateriaAh'] !== null ? (int) $row['CapacidadBateriaAh'] : null,
                        (string) ($row['Color'] ?? ''),
                        (string) $row['Moneda'],
                        $row['PrecioVenta'],
                    );
                    if (hash_equals($variantId, (string) $row['VariantId'])
                        && hash_equals($variantId, $candidate)) {
                        $matchedIndex = $index;
                        break;
                    }
                }
                if ($matchedIndex === null) {
                    throw new StorefrontApiException(409, 'stock_unavailable', 'Una unidad ya no está disponible.');
                }
                $row = $rows[$matchedIndex];
                $row['VariantId'] = $variantId;
                $selected[(string) $row['IdVehiculo']] = $row;
            }

            $currencies = array_values(array_unique(array_map(static fn(array $row): string => strtoupper((string) $row['Moneda']), $selected)));
            if (count($currencies) !== 1) {
                throw new StorefrontApiException(422, 'mixed_currency', 'No se pueden reservar unidades en monedas diferentes.');
            }
            $subtotal = array_sum(array_map(static fn(array $row): float => (float) $row['PrecioVenta'], $selected));
            $discountRate = $paymentMethod === 'cash' ? $this->cashDiscountRate() : 0.0;
            $discount = round($subtotal * $discountRate / 100, 2);
            $total = round($subtotal - $discount, 2);
            $reservationId = self::uuidV4();
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

            $insert = $this->pdo->prepare("INSERT INTO storefront_reservation (ReservationId,OrderUuid,PaymentMethod,Moneda,Subtotal,Descuento,Total,ExpiraEn) VALUES (?,?,?,?,?,?,?,?)");
            $insert->execute([$reservationId, strtolower($orderUuid), $paymentMethod, $currencies[0], $subtotal, $discount, $total, $expiresAt]);

            $item = $this->pdo->prepare('INSERT INTO storefront_reservation_item (ReservationId,IdVehiculo,IdProducto,VariantId,Modelo,CapacidadBateriaAh,Color,PrecioBruto,TasaIVA,MostrarEnWebAnterior,DestacadoWebAnterior) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $lock = $this->pdo->prepare("UPDATE producto SET Estado='Reservado',Stock=0,MostrarEnWeb=0,DestacadoWeb=0 WHERE IdProducto=? AND Estado='Disponible' AND Stock>0 AND MostrarEnWeb=1");
            $vehicle = $this->pdo->prepare('UPDATE vehiculo SET FechaReserva=NOW(),SeniaReserva=0,FechaVenta=NULL WHERE IdVehiculo=? AND FechaVenta IS NULL');
            $items = [];
            foreach ($selected as $row) {
                $item->execute([$reservationId,$row['IdVehiculo'],$row['IdProducto'],$row['VariantId'],$row['Modelo'],$row['CapacidadBateriaAh'],$row['Color'] ?? '',$row['PrecioVenta'],$row['TasaIVA'],$row['MostrarEnWeb'],$row['DestacadoWeb']]);
                $lock->execute([(int) $row['IdProducto']]);
                if ($lock->rowCount() !== 1) throw new StorefrontApiException(409, 'stock_unavailable', 'Una unidad ya no está disponible.');
                $vehicle->execute([(string) $row['IdVehiculo']]);
                $items[] = [
                    'vehicle_id' => (string) $row['IdVehiculo'],
                    'product_id' => (int) $row['IdProducto'],
                    'engine_number' => (string) ($row['NumeroMotor'] ?? ''),
                    'variant_id' => (string) $row['VariantId'],
                    'model' => (string) $row['Modelo'],
                    'battery_ah' => $row['CapacidadBateriaAh'] !== null ? (int) $row['CapacidadBateriaAh'] : null,
                    'color' => (string) ($row['Color'] ?? ''),
                    'gross' => VariantIdentity::decimal($row['PrecioVenta']),
                    'vat_rate' => VariantIdentity::decimal($row['TasaIVA']),
                ];
            }
            $response = ['data' => [
                'reservation_id' => $reservationId,
                'order_uuid' => strtolower($orderUuid),
                'status' => 'active',
                'payment_method' => $paymentMethod,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($expiresAt . ' UTC')),
                'currency' => $currencies[0],
                'subtotal' => VariantIdentity::decimal($subtotal),
                'discount' => VariantIdentity::decimal($discount),
                'total' => VariantIdentity::decimal($total),
                'items' => $items,
            ]];
            $this->storeIdempotentResponse($idempotencyKey, 201, $response);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof PDOException
                && (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)
                    || (string) $e->getCode() === '40001')) {
                if ($attempt < 2) {
                    usleep(20_000 * ($attempt + 1));
                    return $this->reserveAttempt($orderUuid, $variantIds, $paymentMethod, $ttlSeconds, $idempotencyKey, $requestHash, $attempt + 1);
                }
                error_log('[STOREFRONT_RESERVATION_LOCK] mysql_errno=' . (int) ($e->errorInfo[1] ?? 0) . ' retries_exhausted=1');
                throw new StorefrontApiException(503, 'reservation_busy', 'La reserva está ocupada temporalmente. Intentá nuevamente.', true);
            }
            throw $e;
        }
    }

    public function release(string $reservationId, string $idempotencyKey, string $requestHash): array
    {
        $this->pdo->beginTransaction();
        try {
            $cached = $this->claimIdempotency($idempotencyKey, 'reservation.release:' . $reservationId, $requestHash, 30);
            if ($cached !== null) {
                $this->pdo->commit();
                return $cached;
            }
            $stmt = $this->pdo->prepare('SELECT * FROM storefront_reservation WHERE ReservationId=? FOR UPDATE');
            $stmt->execute([$reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reservation) {
                throw new StorefrontApiException(404, 'reservation_not_found', 'La reserva no existe.');
            }
            if ((string) $reservation['Estado'] === 'consumed') {
                throw new StorefrontApiException(409, 'reservation_consumed', 'La reserva ya fue utilizada.');
            }
            if ((string) $reservation['Estado'] === 'active') {
                $this->releaseLocked($reservationId, 'released');
            }
            $response = ['data' => ['reservation_id' => $reservationId, 'status' => (string) ($reservation['Estado'] === 'active' ? 'released' : $reservation['Estado'])]];
            $this->storeIdempotentResponse($idempotencyKey, 200, $response);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    private function availableUnitsForUpdate(array $variantIds, float $vatRate): array
    {
        $ids = array_values(array_unique(array_map('strval', $variantIds)));
        sort($ids, SORT_STRING);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Descubrimiento sin lock por el índice de variante. El segundo SELECT
        // revalida todo bajo lock y solo toca los productos candidatos.
        $candidates = $this->pdo->prepare(
            "SELECT IdProducto FROM vehiculo FORCE INDEX (idx_vehiculo_storefront_variant)
             WHERE StorefrontVariantId IN ({$placeholders})
             ORDER BY StorefrontVariantId,IdVehiculo"
        );
        $candidates->execute($ids);
        $productIds = array_values(array_unique(array_map('intval', $candidates->fetchAll(PDO::FETCH_COLUMN))));
        sort($productIds, SORT_NUMERIC);
        if ($productIds === []) return [];

        $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT v.IdVehiculo,v.IdProducto,v.NumeroMotor,v.StorefrontVariantId VariantId,v.Modelo,v.CapacidadBateriaAh,v.Color,
                       p.PrecioVenta,p.Moneda,p.MostrarEnWeb,p.DestacadoWeb,? TasaIVA
                FROM producto p FORCE INDEX (PRIMARY)
                STRAIGHT_JOIN vehiculo v ON v.IdProducto=p.IdProducto
                WHERE p.IdProducto IN ({$productPlaceholders})
                  AND v.StorefrontVariantId IN ({$placeholders})
                  AND p.TipoProducto='Moto' AND p.MostrarEnWeb=1 AND p.Estado='Disponible'
                  AND p.Stock>0 AND p.PrecioVenta>0 AND v.FechaVenta IS NULL
                ORDER BY p.IdProducto,v.IdVehiculo FOR UPDATE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$vatRate], $productIds, $ids));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function vatRate(): float
    {
        $value = $this->pdo->query('SELECT TasaIVA FROM configuracion ORDER BY IdConfiguracion DESC LIMIT 1')->fetchColumn();
        return ConfiguracionComercial::normalizar(['TasaIVA' => $value !== false ? $value : null], 22.0)['TasaIVA'];
    }

    private function cashDiscountRate(): float
    {
        $value = $this->pdo->query('SELECT COALESCE(DescuentoContado,0) FROM configuracion ORDER BY IdConfiguracion DESC LIMIT 1')->fetchColumn();
        return max(0.0, min(100.0, (float) $value));
    }

    /** @return array<string,mixed>|null */
    private function claimIdempotency(string $key, string $operation, string $hash, int $days): ?array
    {
        $insert = $this->pdo->prepare('INSERT IGNORE INTO storefront_api_idempotency (IdempotencyKey,Operation,RequestHash,ExpiraEn) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? DAY))');
        $insert->execute([$key, $operation, $hash, $days]);
        $stmt = $this->pdo->prepare('SELECT Operation,RequestHash,HttpStatus,ResponseJson FROM storefront_api_idempotency WHERE IdempotencyKey=? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals((string) $row['RequestHash'], $hash) || (string) $row['Operation'] !== $operation) {
            throw new StorefrontApiException(409, 'idempotency_conflict', 'La clave de idempotencia ya fue usada para otra solicitud.');
        }
        if ($row['ResponseJson'] === null) return null;
        $decoded = json_decode((string) $row['ResponseJson'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $response */
    private function storeIdempotentResponse(string $key, int $status, array $response): void
    {
        $stmt = $this->pdo->prepare('UPDATE storefront_api_idempotency SET HttpStatus=?,ResponseJson=? WHERE IdempotencyKey=?');
        $stmt->execute([$status, json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $key]);
    }

    private function expireActiveReservations(): void
    {
        $ids = $this->pdo->query("SELECT ReservationId FROM storefront_reservation WHERE Estado='active' AND ExpiraEn<=NOW() FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->releaseLocked((string) $id, 'expired');
        }
    }

    private function releaseLocked(string $reservationId, string $state): void
    {
        // Orden canónico de locks: reserva (caller) -> pedido -> inventario.
        $find = $this->pdo->prepare("SELECT IdPedido,IdCuenta,Correo,Estado,NumeroPedido FROM ecommerce_pedido WHERE StorefrontReservationId=? AND Estado IN ('PendientePago','PagoEnRevision') FOR UPDATE");
        $find->execute([$reservationId]);
        $panelOrder = $find->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare('SELECT i.* FROM storefront_reservation_item i WHERE i.ReservationId=? ORDER BY i.IdVehiculo FOR UPDATE');
        $stmt->execute([$reservationId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
            $product = $this->pdo->prepare("UPDATE producto SET Estado='Disponible',Stock=1,MostrarEnWeb=?,DestacadoWeb=? WHERE IdProducto=? AND Estado='Reservado'");
            $product->execute([(int) $item['MostrarEnWebAnterior'], (int) $item['DestacadoWebAnterior'], (int) $item['IdProducto']]);
            $vehicle = $this->pdo->prepare('UPDATE vehiculo SET ClienteReservaId=NULL,FechaReserva=NULL,SeniaReserva=NULL WHERE IdVehiculo=? AND FechaVenta IS NULL');
            $vehicle->execute([(string) $item['IdVehiculo']]);
        }
        $update = $this->pdo->prepare('UPDATE storefront_reservation SET Estado=?,LiberadaEn=NOW() WHERE ReservationId=? AND Estado=\'active\'');
        $update->execute([$state, $reservationId]);
        $panelState = $state === 'expired' ? 'Vencido' : 'Cancelado';
        if ($panelOrder) {
            $reason = $state === 'expired' ? 'Reserva web vencida' : 'Reserva web cancelada por el cliente';
            $order = $this->pdo->prepare("UPDATE ecommerce_pedido SET Estado=?,EstadoPago='Cancelado',CanceladoEn=NOW(),MotivoCancelacion=?,VersionBloqueo=VersionBloqueo+1 WHERE IdPedido=?");
            $order->execute([$panelState, $reason, (int) $panelOrder['IdPedido']]);
            $type = $state === 'expired' ? 'ReservaVencida' : 'PedidoCancelado';
            $this->pdo->prepare('INSERT IGNORE INTO ecommerce_notificacion (IdCuenta,IdPedido,Tipo,Destinatario) VALUES (?,?,?,?)')->execute([(int) $panelOrder['IdCuenta'] ?: null, (int) $panelOrder['IdPedido'], $type, (string) $panelOrder['Correo']]);
            $this->pdo->prepare('INSERT INTO ecommerce_auditoria (IdPedido,Accion,EstadoAnterior,EstadoNuevo,MetadataJson) VALUES (?,?,?,?,?)')->execute([(int) $panelOrder['IdPedido'], $state === 'expired' ? 'ReservaWebVencida' : 'ReservaWebCancelada', (string) $panelOrder['Estado'], $panelState, json_encode(['reservation_id' => $reservationId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
            $this->pdo->prepare("INSERT IGNORE INTO internal_alert (Tipo,Severidad,Titulo,Cuerpo,SourceType,SourceId,FechaEvento) VALUES (?,?,?,?, 'ecommerce_pedido',?,NOW())")->execute([$state === 'expired' ? 'pedido_web_vencido' : 'pedido_web_cancelado', 'warning', $state === 'expired' ? 'Reserva web vencida' : 'Pedido web cancelado', $reason . ' · ' . $panelOrder['NumeroPedido'], (int) $panelOrder['IdPedido']]);
        }
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
