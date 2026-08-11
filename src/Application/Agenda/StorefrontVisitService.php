<?php

declare(strict_types=1);

namespace Lteco\Application\Agenda;

use DateTimeImmutable;
use DateTimeZone;
use Lteco\Application\Ecommerce\StorefrontApiException;
use Lteco\Infrastructure\Db\Connection;
use PDO;

final class StorefrontVisitService
{
    private const ALLOWED_TIMES = ['10:00', '11:30', '14:00', '16:00', '17:30'];
    private const ALLOWED_MODELS = ['Q8-350W', 'Q8-500W', 'SL-500W', 'LY-500W'];

    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(array $payload, string $idempotencyKey, string $requestHash): array
    {
        $requestUuid = strtolower(trim((string) ($payload['request_uuid'] ?? '')));
        if (!$this->isUuid($requestUuid)) {
            throw new StorefrontApiException(422, 'invalid_visit', 'La solicitud de visita no es válida.');
        }

        $name = $this->required($payload, 'full_name', 160);
        $phone = preg_replace('/\D+/', '', (string) ($payload['phone'] ?? ''));
        if (strlen($phone) < 8 || strlen($phone) > 15) {
            throw new StorefrontApiException(422, 'invalid_phone', 'El teléfono no es válido.');
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')), 'UTF-8');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new StorefrontApiException(422, 'invalid_email', 'El correo no es válido.');
        }

        $model = trim((string) ($payload['model'] ?? ''));
        if ($model !== '' && !in_array($model, self::ALLOWED_MODELS, true)) {
            throw new StorefrontApiException(422, 'invalid_model', 'El modelo no es válido.');
        }

        $date = trim((string) ($payload['preferred_date'] ?? ''));
        $time = trim((string) ($payload['preferred_time'] ?? ''));
        if (!in_array($time, self::ALLOWED_TIMES, true) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new StorefrontApiException(422, 'invalid_schedule', 'La fecha u hora no es válida.');
        }

        $timezone = new DateTimeZone('America/Montevideo');
        $visit = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
        $now = new DateTimeImmutable('now', $timezone);
        if (!$visit || $visit <= $now || $visit > $now->modify('+90 days')) {
            throw new StorefrontApiException(422, 'invalid_schedule', 'Elegí una fecha futura dentro de los próximos 90 días.');
        }

        $comments = trim((string) ($payload['comments'] ?? ''));
        if (mb_strlen($comments) > 1000) {
            throw new StorefrontApiException(422, 'invalid_comments', 'El comentario es demasiado largo.');
        }

        $pdo = $this->connection->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $cached = $this->claimIdempotency($pdo, $idempotencyKey, $requestHash);
            if ($cached !== null) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return $cached;
            }

            $existing = $pdo->prepare("SELECT IdVisita,FechaVisita FROM crm_visita WHERE ClienteTelefono=? AND Estado IN ('agendada','reprogramada') AND DATE(FechaVisita)=? ORDER BY IdVisita DESC LIMIT 1 FOR UPDATE");
            $existing->execute([$phone, $date]);
            $duplicate = $existing->fetch(PDO::FETCH_ASSOC);
            if ($duplicate) {
                $response = $this->response((int) $duplicate['IdVisita'], $requestUuid, (string) $duplicate['FechaVisita'], true);
                $this->storeResponse($pdo, $idempotencyKey, $response);
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return $response;
            }

            $observation = 'Solicitud creada desde la web. Horario preferido: ' . $time . '.';
            if ($comments !== '') {
                $observation .= ' Comentarios: ' . $comments;
            }

            $insert = $pdo->prepare("INSERT INTO crm_visita (StorefrontRequestUuid,ClienteNombre,ClienteTelefono,ClienteCorreo,VehiculoTexto,Canal,FechaVisita,HoraConfirmada,Estado,Observaciones) VALUES (?,?,?,?,?,'Web',?,0,'agendada',?)");
            $insert->execute([$requestUuid, $name, $phone, $email !== '' ? $email : null, $model !== '' ? $model : null, $visit->format('Y-m-d H:i:s'), mb_substr($observation, 0, 3000)]);

            $id = (int) $pdo->lastInsertId();
            $label = $visit->format('d/m/Y H:i');
            $title = 'Solicitud web de visita: ' . $name . ' - ' . $label;
            $body = trim(implode(' · ', array_filter([$model ?: null, $phone, $email ?: null, 'Confirmar horario desde Agenda'])));
            $alert = $pdo->prepare("INSERT INTO internal_alert (Tipo,Severidad,Titulo,Cuerpo,Estado,SourceType,SourceId,FechaEvento) VALUES ('visita_pendiente','warning',?,?,'abierta','crm_visita',?,?) ON DUPLICATE KEY UPDATE Titulo=VALUES(Titulo),Cuerpo=VALUES(Cuerpo),Estado='abierta',FechaEvento=VALUES(FechaEvento),FechaActualizacion=NOW()");
            $alert->execute([$title, $body, $id, $visit->format('Y-m-d H:i:s')]);

            $response = $this->response($id, $requestUuid, $visit->format('Y-m-d H:i:s'), false);
            $this->storeResponse($pdo, $idempotencyKey, $response);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $response;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function response(int $id, string $uuid, string $date, bool $duplicate): array
    {
        return ['data' => ['visit_id' => $id, 'request_uuid' => $uuid, 'status' => 'pending_confirmation', 'preferred_at' => (new DateTimeImmutable($date, new DateTimeZone('America/Montevideo')))->format(DATE_ATOM), 'duplicate' => $duplicate]];
    }

    /** @return array<string,mixed>|null */
    private function claimIdempotency(PDO $pdo, string $key, string $hash): ?array
    {
        $pdo->prepare("INSERT IGNORE INTO storefront_api_idempotency (IdempotencyKey,Operation,RequestHash,ExpiraEn) VALUES (?,'visit.create',?,DATE_ADD(NOW(),INTERVAL 30 DAY))")->execute([$key, $hash]);
        $stmt = $pdo->prepare('SELECT Operation,RequestHash,ResponseJson FROM storefront_api_idempotency WHERE IdempotencyKey=? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['Operation'] !== 'visit.create' || !hash_equals((string) $row['RequestHash'], $hash)) {
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

    /** @param array<string,mixed> $payload */
    private function required(array $payload, string $key, int $max): string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        if ($value === '' || mb_strlen($value) > $max) {
            throw new StorefrontApiException(422, 'invalid_visit', 'Faltan datos obligatorios de la visita.');
        }
        return $value;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) === 1;
    }
}
