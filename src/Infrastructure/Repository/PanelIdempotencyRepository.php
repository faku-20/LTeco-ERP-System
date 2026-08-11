<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use PDO;
use RuntimeException;

final class PanelIdempotencyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function claim(string $operationType, string $operationKey, string $requestHash, ?int $idUsuario): ?array
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('La idempotencia del panel requiere una transacción activa.');
        }

        $insert = $this->pdo->prepare("
            INSERT IGNORE INTO panel_idempotency_key
                (OperationKey, OperationType, RequestHash, Status, IdUsuario, ExpiraEn)
            VALUES (?, ?, ?, 'processing', ?, DATE_ADD(NOW(), INTERVAL 1 DAY))
        ");
        $insert->execute([$operationKey, $operationType, $requestHash, $idUsuario ?: null]);
        $inserted = $insert->rowCount() === 1;

        $select = $this->pdo->prepare('SELECT * FROM panel_idempotency_key WHERE OperationKey = ? LIMIT 1 FOR UPDATE');
        $select->execute([$operationKey]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('No se pudo registrar la clave de idempotencia.');
        }

        if ((string)$row['OperationType'] !== $operationType || !hash_equals((string)$row['RequestHash'], $requestHash)) {
            throw new RuntimeException('La operación ya fue enviada con otros datos. Recargá el formulario e intentá nuevamente.');
        }

        if (!$inserted && (string)$row['Status'] === 'completed') {
            return $row;
        }

        if (!$inserted) {
            throw new RuntimeException('La operación ya está siendo procesada. Esperá unos segundos y revisá el resultado antes de reenviar.');
        }

        return null;
    }

    public function complete(string $operationKey, string $resultType, int|string $resultId, string $redirectUrl): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('La idempotencia del panel requiere una transacción activa.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE panel_idempotency_key
            SET Status = 'completed',
                ResultType = ?,
                ResultId = ?,
                RedirectUrl = ?,
                CompletedAt = NOW()
            WHERE OperationKey = ? AND Status = 'processing'
        ");
        $stmt->execute([$resultType, (string)$resultId, $redirectUrl, $operationKey]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No se pudo completar la clave de idempotencia.');
        }
    }
}
