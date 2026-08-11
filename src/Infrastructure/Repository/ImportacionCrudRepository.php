<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Escrituras de importaciones (E4): alta y edición. No abre transacciones.
 * Preserva textualmente el INSERT/UPDATE legacy de importaciones/crear.php y
 * editar.php. La edición NO modifica el Numero (igual que el legacy).
 */
final class ImportacionCrudRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Importación por IdImportacion (vista de edición).
     *
     * @return array<string,mixed>|null
     */
    public function buscar(int $idImportacion): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM importacion WHERE IdImportacion = ?");
        $stmt->execute([$idImportacion]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Inserta una importación y devuelve su IdImportacion.
     *
     * @param array{numero:int,tipo_cambio_usd:float,fecha:?string,descripcion:?string,activa:int} $datos
     */
    public function crear(array $datos): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO importacion (Numero, TipoCambioUSD, Fecha, Descripcion, Activa) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $datos['numero'],
            $datos['tipo_cambio_usd'],
            $datos['fecha'],
            $datos['descripcion'],
            $datos['activa'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza una importación (sin tocar el Numero).
     *
     * @param array{tipo_cambio_usd:float,fecha:?string,descripcion:?string,activa:int} $datos
     */
    public function editar(int $idImportacion, array $datos): void
    {
        $stmt = $this->pdo->prepare("UPDATE importacion SET TipoCambioUSD=?, Fecha=?, Descripcion=?, Activa=? WHERE IdImportacion=?");
        $stmt->execute([
            $datos['tipo_cambio_usd'],
            $datos['fecha'],
            $datos['descripcion'],
            $datos['activa'],
            $idImportacion,
        ]);
    }
}
