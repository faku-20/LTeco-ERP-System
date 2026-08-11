<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Application\Ecommerce\ServiceNonceStore;
use Lteco\Infrastructure\Db\Connection;
use PDO;
use PDOException;

final class StorefrontApiNonceRepository implements ServiceNonceStore
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function consumeRateSlot(string $keyId, int $windowStartUnix, int $limit): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $ownsTransaction = !$this->pdo->inTransaction();
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            try {
                $insert = $this->pdo->prepare('INSERT IGNORE INTO storefront_api_rate_window (KeyId,WindowStart,RequestCount,ExpiraEn) VALUES (?,FROM_UNIXTIME(?),0,FROM_UNIXTIME(?))');
                $insert->execute([$keyId, $windowStartUnix, $windowStartUnix + 120]);
                $select = $this->pdo->prepare('SELECT RequestCount FROM storefront_api_rate_window WHERE KeyId=? AND WindowStart=FROM_UNIXTIME(?) FOR UPDATE');
                $select->execute([$keyId, $windowStartUnix]);
                $count = (int) $select->fetchColumn();
                if ($count >= $limit) {
                    if ($ownsTransaction) {
                        $this->pdo->commit();
                    }
                    return false;
                }
                $update = $this->pdo->prepare('UPDATE storefront_api_rate_window SET RequestCount=RequestCount+1 WHERE KeyId=? AND WindowStart=FROM_UNIXTIME(?)');
                $update->execute([$keyId, $windowStartUnix]);
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return true;
            } catch (\Throwable $e) {
                if ($ownsTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $transient = $e instanceof PDOException && (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true) || (string) $e->getCode() === '40001');
                if (!$transient || !$ownsTransaction || $attempt === 2) {
                    throw $e;
                }
                usleep(($attempt + 1) * 20000);
            }
        }
        return false;
    }

    public function remember(string $keyId, string $nonce, int $expiresUnix): bool
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO storefront_api_nonce (KeyId,Nonce,ExpiraEn) VALUES (?,?,FROM_UNIXTIME(?))');
            $stmt->execute([$keyId, $nonce, $expiresUnix]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function purgeExpired(int $nowUnix): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM storefront_api_nonce WHERE ExpiraEn<FROM_UNIXTIME(?) LIMIT 500');
            $stmt->execute([$nowUnix]);
            $stmt = $this->pdo->prepare('DELETE FROM storefront_api_rate_window WHERE ExpiraEn<FROM_UNIXTIME(?) LIMIT 500');
            $stmt->execute([$nowUnix]);
        } catch (PDOException $e) {
            // Es mantenimiento oportunista, nunca debe hacer fallar una compra.
            // Otra ejecución limpiará estas filas si compitió con un INSERT.
            if (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)
                || (string) $e->getCode() === '40001') {
                return;
            }
            throw $e;
        }
    }
}
