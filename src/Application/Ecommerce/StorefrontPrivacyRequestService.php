<?php
declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use Lteco\Infrastructure\Db\Connection;

final class StorefrontPrivacyRequestService
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(array $payload): array
    {
        $uuid = strtolower(trim((string) ($payload['request_uuid'] ?? '')));
        $user = strtolower(trim((string) ($payload['user_uuid'] ?? '')));
        $type = (string) ($payload['type'] ?? '');
        $name = trim((string) ($payload['name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $details = trim((string) ($payload['details'] ?? ''));
        $due = (string) ($payload['due_at'] ?? '');

        if (!$this->uuid($uuid) || !$this->uuid($user) || !in_array($type, ['access', 'correction', 'suppression', 'objection'], true) || $name === '' || mb_strlen($name) > 220 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($details) > 2000 || strtotime($due) === false) {
            throw new StorefrontApiException(422, 'invalid_privacy_request', 'La solicitud de privacidad no es válida.');
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT IGNORE INTO storefront_privacy_request (RequestUuid,UserUuid,Tipo,Nombre,Correo,Detalle,VenceEn) VALUES (?,?,?,?,?,?,?)")->execute([$uuid, $user, $type, $name, $email, $details ?: null, date('Y-m-d H:i:s', strtotime($due))]);
            $pdo->prepare("INSERT IGNORE INTO internal_alert (Tipo,Severidad,Titulo,Cuerpo,SourceType,SourceId,FechaEvento) SELECT 'storefront_privacy_request','warning',CONCAT('Solicitud de privacidad · ',Tipo),CONCAT(Nombre,' · vence ',DATE_FORMAT(VenceEn,'%d/%m/%Y')),'storefront_privacy_request',CONV(SUBSTRING(SHA2(RequestUuid,256),1,15),16,10),NOW() FROM storefront_privacy_request WHERE RequestUuid=?")->execute([$uuid]);
            $pdo->commit();
            return ['data' => ['request_uuid' => $uuid, 'status' => 'submitted']];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public function find(string $uuid): array
    {
        if (!$this->uuid(strtolower($uuid))) {
            throw new StorefrontApiException(422, 'invalid_privacy_request', 'La solicitud no es válida.');
        }

        $stmt = $this->connection->pdo()->prepare('SELECT RequestUuid,Tipo,Estado,Respuesta,ResueltaEn,ActualizadoEn FROM storefront_privacy_request WHERE RequestUuid=?');
        $stmt->execute([strtolower($uuid)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new StorefrontApiException(404, 'privacy_request_not_found', 'La solicitud no existe.');
        }

        return ['data' => ['request_uuid' => $row['RequestUuid'], 'type' => $row['Tipo'], 'status' => $row['Estado'], 'response' => $row['Respuesta'], 'resolved_at' => $row['ResueltaEn'], 'updated_at' => $row['ActualizadoEn']]];
    }

    private function uuid(string $v): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $v) === 1;
    }
}
