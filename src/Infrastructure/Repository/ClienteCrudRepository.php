<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

final class ClienteCrudRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Ficha de cliente para la vista de edición (set de columnas legacy).
     *
     * @return array<string,mixed>|null
     */
    public function buscar(int $idCliente): ?array
    {
        $stmt = $this->pdo->prepare("SELECT IdCliente, NombreApellido, Telefono, Correo, TipoFiscal, Cedula, Direccion, RUT FROM cliente WHERE IdCliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function listarParaSelector(): array
    {
        return $this->pdo->query("
            SELECT IdCliente, NombreApellido, Telefono, Correo, TipoFiscal, Cedula, Direccion, RUT
            FROM cliente
            ORDER BY NombreApellido ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existe(int $idCliente): bool
    {
        $stmt = $this->pdo->prepare("SELECT IdCliente FROM cliente WHERE IdCliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Replica valorUnicoOpcional(cliente, Telefono): valor vacío siempre disponible.
     */
    public function telefonoDisponible(?string $telefono, ?int $excluirId): bool
    {
        return $this->valorDisponible('Telefono', $telefono, $excluirId);
    }

    /**
     * Replica valorUnicoOpcional(cliente, Correo): valor vacío siempre disponible.
     */
    public function correoDisponible(?string $correo, ?int $excluirId): bool
    {
        return $this->valorDisponible('Correo', $correo, $excluirId);
    }

    /**
     * Replica valorUnicoOpcional(cliente, Cedula): valor vacío siempre disponible.
     */
    public function cedulaDisponible(?string $cedula, ?int $excluirId): bool
    {
        return $this->valorDisponible('Cedula', $cedula, $excluirId);
    }

    private function valorDisponible(string $columna, ?string $valor, ?int $excluirId): bool
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return true;
        }

        $sql = "SELECT COUNT(*) FROM `cliente` WHERE `{$columna}` = :valor";
        $params = [':valor' => $valor];
        if ($excluirId !== null) {
            $sql .= " AND `IdCliente` <> :excluir";
            $params[':excluir'] = $excluirId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Alta de cliente. Conserva el INSERT dinámico legacy.
     *
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos): int
    {
        $columnas = [
            'NombreApellido' => $datos['nombre_apellido'],
            'Telefono' => $datos['telefono'],
            'Correo' => $datos['correo'],
            'TipoFiscal' => $datos['tipo_fiscal'],
            'Cedula' => $datos['cedula'],
            'Direccion' => $datos['direccion'],
            'RUT' => $datos['rut'],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO cliente (' . implode(', ', array_keys($columnas)) . ') VALUES (' . implode(', ', array_fill(0, count($columnas), '?')) . ')'
        );
        $stmt->execute(array_values($columnas));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Edición de cliente. Conserva el UPDATE dinámico legacy.
     *
     * @param array<string,mixed> $datos
     */
    public function editar(int $idCliente, array $datos): void
    {
        $sets = ['NombreApellido = ?', 'Telefono = ?', 'Correo = ?', 'TipoFiscal = ?', 'Cedula = ?', 'Direccion = ?', 'RUT = ?'];
        $params = [
            $datos['nombre_apellido'],
            $datos['telefono'],
            $datos['correo'],
            $datos['tipo_fiscal'],
            $datos['cedula'],
            $datos['direccion'],
            $datos['rut'],
        ];
        $params[] = $idCliente;

        $stmt = $this->pdo->prepare('UPDATE cliente SET ' . implode(', ', $sets) . ' WHERE IdCliente = ?');
        $stmt->execute($params);
    }
}
