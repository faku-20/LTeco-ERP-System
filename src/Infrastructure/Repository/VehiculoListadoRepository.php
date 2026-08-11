<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Consulta de SOLO LECTURA del listado de vehículos (lteco-panel/vehiculos/index.php).
 *
 * SQL extraído verbatim para que la página quede como vista fina. No abre
 * transacciones; no muta nada. El flag $puedeGestionarCatalogoWeb (derivado de
 * esSuperadmin() en la vista) decide qué filtros y qué orden se aplican, igual que
 * el legacy.
 */
final class VehiculoListadoRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(string $estado, string $web, string $destacado, string $busqueda, bool $puedeGestionarCatalogoWeb): array
    {
        $sql = "
    SELECT
        v.IdVehiculo,
        v.Modelo,
        v.NumeroMotor,
        v.Color,
        v.ClienteReservaId,
        v.SeniaReserva,
        p.IdProducto,
        p.Estado,
        p.Stock,
        p.PrecioVenta,
        p.Moneda,
        v.TipoCambioImportacion,
        p.MostrarEnWeb,
        p.DestacadoWeb,
        p.OrdenWeb,
        p.Slug,
        p.DescripcionWeb,
        c.NombreApellido AS ClienteReservaNombre,
        (
            SELECT vi.RutaImagen
            FROM vehiculo_imagen vi
            WHERE vi.IdVehiculo = v.IdVehiculo
            ORDER BY vi.EsPrincipal DESC, vi.OrdenImagen ASC
            LIMIT 1
        ) AS ImagenPrincipal
    FROM vehiculo v
    INNER JOIN producto p ON p.IdProducto = v.IdProducto
    LEFT JOIN cliente c ON c.IdCliente = v.ClienteReservaId
    WHERE 1 = 1
";

        $params = [];

        if ($estado !== '') {
            $sql .= " AND p.Estado = ? ";
            $params[] = $estado;
        }

        if ($puedeGestionarCatalogoWeb && $web !== '') {
            switch ($web) {
                case 'visible':
                    $sql .= " AND p.MostrarEnWeb = 1 ";
                    break;
                case 'oculto':
                    $sql .= " AND p.MostrarEnWeb = 0 ";
                    break;
                case 'publicable':
                    $sql .= " AND p.Estado = 'Disponible' AND p.Stock > 0 ";
                    break;
                case 'bloqueado':
                    $sql .= " AND NOT (p.Estado = 'Disponible' AND p.Stock > 0) ";
                    break;
            }
        }

        if ($puedeGestionarCatalogoWeb && $destacado !== '') {
            $sql .= " AND p.DestacadoWeb = ? ";
            $params[] = $destacado === '1' ? 1 : 0;
        }

        if ($busqueda !== '') {
            $sql .= "
        AND (
            CAST(v.IdVehiculo AS CHAR) LIKE ?
            OR v.Modelo LIKE ?
            OR v.NumeroMotor LIKE ?
            OR v.Color LIKE ?
            OR COALESCE(p.Slug, '') LIKE ?
        )
    ";
            $like = '%' . $busqueda . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql .= "
    ORDER BY
        CASE p.Estado
            WHEN 'Disponible' THEN 1
            WHEN 'Reservado' THEN 2
            WHEN 'Oculto' THEN 3
            WHEN 'Sin stock' THEN 4
            WHEN 'Vendido' THEN 5
            ELSE 6
        END,
        " . ($puedeGestionarCatalogoWeb ? "p.MostrarEnWeb DESC,\n        p.DestacadoWeb DESC,\n        p.OrdenWeb ASC," : '') . "
        v.IdVehiculo DESC
";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
