<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use InvalidArgumentException;
use PDO;

final class SchemaRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function tablaExiste(string $tabla): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tabla]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function valorUnico(
        string $tabla,
        string $columna,
        mixed $valor,
        ?string $columnaExcluir = null,
        mixed $valorExcluir = null
    ): bool {
        $this->validarIdentificador($tabla);
        $this->validarIdentificador($columna);
        if ($columnaExcluir !== null) {
            $this->validarIdentificador($columnaExcluir);
        }

        $sql = "SELECT COUNT(*) FROM `{$tabla}` WHERE `{$columna}` = :valor";
        $params = [':valor' => $valor];
        if ($columnaExcluir !== null) {
            $sql .= " AND `{$columnaExcluir}` <> :excluir";
            $params[':excluir'] = $valorExcluir;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }

    private function validarIdentificador(string $identificador): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identificador)) {
            throw new InvalidArgumentException('Identificador SQL inválido.');
        }
    }
}
