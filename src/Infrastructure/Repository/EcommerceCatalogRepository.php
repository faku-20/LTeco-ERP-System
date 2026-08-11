<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Application\Ecommerce\CatalogDataSource;
use Lteco\Infrastructure\Db\Connection;
use PDO;

final class EcommerceCatalogRepository implements CatalogDataSource
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    public function catalogSnapshot(): array
    {
        $ownsTransaction = ! $this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $units = $this->publishedUnits();
            $vehicleIds = array_values(array_map(
                static fn (array $row): string => (string) $row['IdVehiculo'],
                $units,
            ));
            $images = $this->imagesForVehicles($vehicleIds);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return ['units' => $units, 'images' => $images];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    private function publishedUnits(): array
    {
        /*
         * Un producto de tipo Moto representa una unidad física. Por eso el
         * stock de una variante se calcula contando unidades publicadas y no
         * tomando el máximo de producto.Stock. Se incluyen las unidades en
         * estado "Sin stock" para que el storefront pueda mostrarlas como
         * agotadas, pero se excluyen Reservado, Vendido y Oculto.
         */
        $sql = "SELECT v.IdVehiculo,v.IdProducto,v.Modelo,v.CapacidadBateriaAh,v.Color,
                       p.Slug,p.DescripcionWeb,p.PrecioVenta,p.Moneda,p.OrdenWeb,p.Stock,
                       p.Estado,COALESCE(c.TasaIVA, ?) TasaIVA
                FROM vehiculo v
                INNER JOIN producto p ON p.IdProducto=v.IdProducto
                LEFT JOIN configuracion c ON c.IdConfiguracion=(SELECT MAX(c2.IdConfiguracion) FROM configuracion c2)
                WHERE p.TipoProducto='Moto'
                  AND p.MostrarEnWeb=1
                  AND p.Estado IN ('Disponible','Sin stock')
                  AND p.PrecioVenta>0
                  AND v.FechaVenta IS NULL
                  AND (p.Slug IS NULL OR p.Slug='' OR CHAR_LENGTH(p.Slug) <= 190)
                ORDER BY p.OrdenWeb ASC,v.Modelo ASC,v.CapacidadBateriaAh ASC,v.Color ASC,v.IdVehiculo ASC";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([defaultTasaIVA()]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param list<string> $vehicleIds @return list<array<string,mixed>> */
    private function imagesForVehicles(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT IdVehiculo,RutaImagen,EsPrincipal,OrdenImagen,IdImagen
             FROM vehiculo_imagen
             WHERE IdVehiculo IN ({$placeholders})
             ORDER BY IdVehiculo ASC,EsPrincipal DESC,OrdenImagen ASC,IdImagen ASC",
        );
        $statement->execute($vehicleIds);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
