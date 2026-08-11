<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class DistribuidorCrudRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buscar(int $idDistribuidor): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM distribuidor WHERE IdDistribuidor = ? LIMIT 1');
        $stmt->execute([$idDistribuidor]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function crear(array $form, float $comision): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO distribuidor
                (Nombre, ComisionPct, Contacto, Telefono, Correo, Observaciones, Activo)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $form['nombre'],
            $comision,
            $form['contacto'] ?: null,
            $form['telefono'] ?: null,
            $form['correo'] ?: null,
            $form['observaciones'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function editar(int $idDistribuidor, array $form, float $comision): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE distribuidor
            SET Nombre = ?,
                ComisionPct = ?,
                Contacto = ?,
                Telefono = ?,
                Correo = ?,
                Observaciones = ?,
                Activo = ?
            WHERE IdDistribuidor = ?
        ");
        $stmt->execute([
            $form['nombre'],
            $comision,
            $form['contacto'] ?: null,
            $form['telefono'] ?: null,
            $form['correo'] ?: null,
            $form['observaciones'] ?: null,
            (int) $form['activo'],
            $idDistribuidor,
        ]);
    }
}
