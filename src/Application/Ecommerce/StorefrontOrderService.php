<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class StorefrontOrderService
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(array $payload, string $idempotencyKey, string $requestHash): array
    {
        $orderUuid = strtolower(trim((string) ($payload['order_uuid'] ?? '')));
        $reservationId = strtolower(trim((string) ($payload['reservation_id'] ?? '')));
        $customer = $payload['customer'] ?? null;
        $address = $payload['billing_address'] ?? null;
        if (!$this->isUuid($orderUuid) || !$this->isUuid($reservationId)
            || !is_array($customer) || !is_array($address)) {
            throw new StorefrontApiException(422, 'invalid_order', 'Los datos del pedido no son válidos.');
        }

        $firstName = $this->required($customer, 'first_name', 100);
        $lastName = $this->required($customer, 'last_name', 100);
        $email = strtolower($this->required($customer, 'email', 190));
        $phone = $this->required($customer, 'phone', 30);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new StorefrontApiException(422, 'invalid_customer_email', 'El correo del cliente no es válido.');
        }

        $line1 = $this->required($address, 'line1', 190);
        $city = $this->required($address, 'city', 100);
        $department = $this->required($address, 'department', 100);
        $line2 = trim((string) ($address['line2'] ?? ''));
        $postalCode = trim((string) ($address['postal_code'] ?? ''));
        $cedula = preg_replace('/\D+/', '', (string) ($customer['cedula'] ?? '')) ?: null;

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $cached = $this->claimIdempotency($pdo, $idempotencyKey, $requestHash);
            if ($cached !== null) {
                $pdo->commit();
                return $cached;
            }

            $reservation = $pdo->prepare('SELECT * FROM storefront_reservation WHERE ReservationId=? AND OrderUuid=? FOR UPDATE');
            $reservation->execute([$reservationId, $orderUuid]);
            $reserved = $reservation->fetch(PDO::FETCH_ASSOC);
            if (!$reserved || (string) $reserved['Estado'] !== 'active') {
                throw new StorefrontApiException(409, 'reservation_not_active', 'La reserva del pedido ya no está activa.');
            }

            $existing = $pdo->prepare('SELECT IdPedido,NumeroPedido,Estado FROM ecommerce_pedido WHERE StorefrontOrderUuid=? FOR UPDATE');
            $existing->execute([$orderUuid]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            $shouldNotifyCashReservationWhatsapp = false;
            if (!$row) {
                $number = 'WEB-' . strtoupper(substr(str_replace('-', '', $orderUuid), 0, 20));
                $insert = $pdo->prepare("INSERT INTO ecommerce_pedido
                    (StorefrontOrderUuid,StorefrontReservationId,NumeroPedido,TokenPublico,IdCuenta,IdCliente,Estado,EstadoPago,Nombre,Apellido,Correo,Telefono,Cedula,Entrega,Direccion,Ciudad,Departamento,CodigoPostal,Moneda,Subtotal,CostoEnvio,Total,ProveedorPago,ExpiraEn)
                    VALUES (?,?,?,?,NULL,NULL,'PagoEnRevision','Pendiente',?,?,?,?,?,'Retiro',?,?,?,?,?,?,0,?,?,?)");
                $insert->execute([
                    $orderUuid,
                    $reservationId,
                    $number,
                    hash('sha256', $orderUuid),
                    $firstName,
                    $lastName,
                    $email,
                    $phone,
                    $cedula,
                    trim($line1 . ($line2 !== '' ? ', ' . $line2 : '')),
                    $city,
                    $department,
                    $postalCode !== '' ? $postalCode : null,
                    $reserved['Moneda'],
                    $reserved['Subtotal'],
                    $reserved['Total'],
                    (string) $reserved['PaymentMethod'],
                    $reserved['ExpiraEn'],
                ]);
                $orderId = (int) $pdo->lastInsertId();
                $items = $pdo->prepare('SELECT * FROM storefront_reservation_item WHERE ReservationId=? ORDER BY IdVehiculo');
                $items->execute([$reservationId]);
                $insertItem = $pdo->prepare('INSERT INTO ecommerce_pedido_item (IdPedido,IdVehiculo,IdProducto,Modelo,CapacidadBateriaAh,Color,PrecioUnitario,Cantidad,Total) VALUES (?,?,?,?,?,?,?,1,?)');
                foreach ($items->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
                    $insertItem->execute([$orderId, $item['IdVehiculo'], $item['IdProducto'], $item['Modelo'], $item['CapacidadBateriaAh'], $item['Color'], $item['PrecioBruto'], $item['PrecioBruto']]);
                }

                $pdo->prepare("INSERT INTO ecommerce_auditoria (IdPedido,Accion,EstadoNuevo,MetadataJson) VALUES (?,'PedidoWebCreado','PagoEnRevision',?)")
                    ->execute([$orderId, json_encode(['reservation_id' => $reservationId, 'payment_method' => (string) $reserved['PaymentMethod']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
                if ((string) $reserved['PaymentMethod'] === 'cash') {
                    $pdo->prepare("INSERT IGNORE INTO ecommerce_notificacion (IdPedido,Tipo,Destinatario) VALUES (?,'ReservaCreada',?)")
                        ->execute([$orderId, $email]);
                    $shouldNotifyCashReservationWhatsapp = true;
                }

                $pdo->prepare("INSERT IGNORE INTO internal_alert (Tipo,Severidad,Titulo,Cuerpo,SourceType,SourceId,FechaEvento) VALUES ('pedido_web_nuevo','info',?,?, 'ecommerce_pedido',?,NOW())")
                    ->execute(['Nuevo pedido web ' . $number, $firstName . ' ' . $lastName . ' reservó una compra por ' . $reserved['Moneda'] . ' ' . number_format((float) $reserved['Total'], 2, ',', '.'), $orderId]);
                // Outbox transaccional: solo queda visible para el worker si el pedido
                // confirma su transacción. La clave evita duplicados ante reintentos.
                $pdo->prepare("INSERT IGNORE INTO automation_event (EventKey,IdempotencyKey,SourceType,SourceId,Payload) VALUES ('pedido_web_creado',?,?,?,?)")
                    ->execute([
                        'pedido_web_creado:' . $orderId,
                        'ecommerce_pedido',
                        $orderId,
                        json_encode(['id_pedido' => $orderId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    ]);
                $internalRecipients = trim((string) \configEnv('LTECO_WEB_SALES_NOTIFY_EMAILS', (string) \configEnv('LTECO_MAIL_TO', '')));
                if ($internalRecipients !== '') {
                    $pdo->prepare("INSERT IGNORE INTO ecommerce_notificacion (IdPedido,Tipo,Destinatario) VALUES (?,'PedidoWebInterno',?)")
                        ->execute([$orderId, mb_substr($internalRecipients, 0, 190)]);
                }
                $row = ['IdPedido' => $orderId, 'NumeroPedido' => $number, 'Estado' => 'PagoEnRevision'];
            }

            $response = ['data' => [
                'panel_order_id' => (int) $row['IdPedido'],
                'order_uuid' => $orderUuid,
                'order_number' => (string) $row['NumeroPedido'],
                'status' => (string) $row['Estado'],
            ]];
            $this->storeResponse($pdo, $idempotencyKey, $response);
            $pdo->commit();
            if ($shouldNotifyCashReservationWhatsapp) {
                $this->enviarWhatsappReservaEfectivo(
                    (int) $row['IdPedido'],
                    (string) $row['NumeroPedido'],
                    $phone,
                    trim($firstName . ' ' . $lastName),
                    (string) $reserved['Moneda'],
                    (float) $reserved['Total'],
                    (string) $reserved['ExpiraEn'],
                );
            }
            return $response;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $values */
    private function required(array $values, string $key, int $max): string
    {
        $value = trim((string) ($values[$key] ?? ''));
        if ($value === '' || mb_strlen($value) > $max) {
            throw new StorefrontApiException(422, 'invalid_order', 'Falta información obligatoria del pedido.');
        }
        return $value;
    }

    /** @return array<string,mixed>|null */
    private function claimIdempotency(PDO $pdo, string $key, string $hash): ?array
    {
        $pdo->prepare("INSERT IGNORE INTO storefront_api_idempotency (IdempotencyKey,Operation,RequestHash,ExpiraEn) VALUES (?,'order.create',?,DATE_ADD(NOW(),INTERVAL 30 DAY))")->execute([$key, $hash]);
        $stmt = $pdo->prepare('SELECT Operation,RequestHash,ResponseJson FROM storefront_api_idempotency WHERE IdempotencyKey=? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['Operation'] !== 'order.create' || !hash_equals((string) $row['RequestHash'], $hash)) {
            throw new StorefrontApiException(409, 'idempotency_conflict', 'La clave de idempotencia ya fue usada para otra solicitud.');
        }
        if ($row['ResponseJson'] === null) {
            return null;
        }
        $decoded = json_decode((string) $row['ResponseJson'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $response */
    private function storeResponse(PDO $pdo, string $key, array $response): void
    {
        $pdo->prepare('UPDATE storefront_api_idempotency SET HttpStatus=201,ResponseJson=? WHERE IdempotencyKey=?')->execute([json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $key]);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) === 1;
    }

    private function enviarWhatsappReservaEfectivo(
        int $idPedido,
        string $numeroPedido,
        string $telefono,
        string $cliente,
        string $moneda,
        float $total,
        string $expiraEn,
    ): void {
        if ($idPedido <= 0 || trim($telefono) === '') {
            return;
        }

        try {
            $whatsappPath = dirname(__DIR__, 3) . '/lteco-panel/includes/whatsapp.php';
            if (!is_file($whatsappPath)) {
                return;
            }
            require_once $whatsappPath;
            $pdo = $this->connection->pdo();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacion_whatsapp WHERE Tipo='venta' AND IdReferencia=? AND Template='pedido_web_reserva_cliente' AND Estado='enviado'");
            $stmt->execute([$idPedido]);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }

            $vence = 'a confirmar';
            if ($expiraEn !== '') {
                try {
                    $vence = (new \DateTimeImmutable($expiraEn))->format('d/m/Y H:i');
                } catch (\Throwable) {
                    $vence = 'a confirmar';
                }
            }

            $mensaje = "Hola " . ($cliente !== '' ? $cliente : 'buenas') . ". Confirmamos tu reserva web {$numeroPedido} por {$moneda} " . number_format($total, 2, ',', '.') . ".\n"
                . "Te esperamos en nuestro showroom en zona Belvedere para continuar con el pago y coordinar la entrega.\n"
                . "La reserva queda vigente hasta {$vence}. Podés escribirnos por este WhatsApp ante cualquier consulta.";

            \enviarWhatsAppTextoGratisConPdo($pdo, $telefono, $mensaje, $idPedido, 'pedido_web_reserva_cliente');
        } catch (\Throwable) {
            // La reserva y el correo no deben fallar por WhatsApp.
        }
    }
}
