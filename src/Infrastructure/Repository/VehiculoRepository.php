<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos de vehículos y de su producto asociado.
 *
 * Origen: SQL inline en lteco-panel/ventas/guardar.php,
 * lteco-panel/distribuidores/nueva_venta.php y las acciones de
 * lteco-panel/vehiculos/ (cambiar_estado, reservar, toggles, ocultar). Movido
 * aquí SIN cambios de comportamiento (mismas sentencias y parámetros).
 *
 * TRANSACTION-AGNOSTIC: reutiliza el $pdo de Connection y NUNCA abre/cierra
 * transacción; el dueño de la transacción sigue siendo el caller.
 */
final class VehiculoRepository
{
    private PDO $pdo;

    public function __construct(Connection $conexion)
    {
        $this->pdo = $conexion->pdo();
    }

    /**
     * Marca la moto como vendida: producto 'Vendido' (Stock 0, fuera de web) y
     * vehículo con fecha de venta + reserva limpia. (alta de venta)
     */
    public function marcarVendido(string $idVehiculo, int $idProducto): void
    {
        $this->pdo->prepare(
            "UPDATE producto SET Estado = 'Vendido', Stock = 0, MostrarEnWeb = 0, DestacadoWeb = 0 WHERE IdProducto = ?"
        )->execute([$idProducto]);

        $this->pdo->prepare(
            'UPDATE vehiculo SET FechaVenta = CURDATE(), ClienteReservaId = NULL, FechaReserva = NULL, SeniaReserva = NULL WHERE IdVehiculo = ?'
        )->execute([$idVehiculo]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function datosPublicacion(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                p.IdProducto,
                p.MostrarEnWeb,
                p.DestacadoWeb,
                p.Estado,
                p.Stock,
                p.PrecioVenta,
                p.Slug,
                p.DescripcionWeb,
                v.Modelo,
                EXISTS(
                    SELECT 1
                    FROM vehiculo_imagen vi
                    WHERE vi.IdVehiculo = v.IdVehiculo
                      AND vi.EsPrincipal = 1
                ) AS TieneImagen
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE v.IdVehiculo = ?
            LIMIT 1
        ");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function datosEstado(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.IdProducto, p.MostrarEnWeb, p.DestacadoWeb
             FROM vehiculo v
             INNER JOIN producto p ON p.IdProducto = v.IdProducto
             WHERE v.IdVehiculo = ?
             LIMIT 1'
        );
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function datosOcultar(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.IdProducto, v.Modelo, v.NumeroMotor FROM vehiculo v WHERE v.IdVehiculo = ? LIMIT 1'
        );
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function datosReserva(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                v.IdVehiculo,
                v.Modelo,
                v.NumeroMotor,
                v.Color,
                p.IdProducto,
                p.Estado,
                p.MostrarEnWeb,
                p.DestacadoWeb
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE v.IdVehiculo = ?
        ");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function datosEdicion(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.IdVehiculo, v.IdProducto, v.NumeroMotor, v.Modelo, v.Color,
                   v.FechaIngreso, v.FechaVenta, v.ClienteReservaId, v.FechaReserva,
                   v.SeniaReserva, v.NumeroImportacion, v.TipoCambioImportacion,
                   p.Descripcion, p.DescripcionWeb, p.Costo, p.GastoTotal,
                   p.PrecioVenta, p.PrecioDistribuidor, p.Moneda, p.Stock, p.Estado,
                   p.Slug, p.MostrarEnWeb, p.DestacadoWeb, p.OrdenWeb, p.TextoBotonWeb
            FROM vehiculo v
            INNER JOIN producto p ON p.IdProducto = v.IdProducto
            WHERE v.IdVehiculo = ?
        ");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function garantiaReciente(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT g.*, c.NombreApellido
            FROM garantia g
            LEFT JOIN cliente c ON c.IdCliente = g.IdCliente
            WHERE g.IdVehiculo = ?
            ORDER BY g.IdGarantia DESC
            LIMIT 1
        ");
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function servicesVehiculo(string $idVehiculo): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM service_vehiculo WHERE IdVehiculo = ? ORDER BY NumeroService ASC');
        $stmt->execute([$idVehiculo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function imagenesVehiculo(string $idVehiculo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT IdImagen, RutaImagen, EsPrincipal, OrdenImagen
             FROM vehiculo_imagen
             WHERE IdVehiculo = ?
             ORDER BY EsPrincipal DESC, OrdenImagen ASC, IdImagen ASC'
        );
        $stmt->execute([$idVehiculo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Actualiza estado + stock + publicación del producto del vehículo.
     * Origen: vehiculos/cambiar_estado.php y vehiculos/reservar.php.
     */
    public function actualizarEstadoYPublicacion(
        int $idProducto,
        string $estado,
        int $stock,
        int $mostrarWeb,
        int $destacadoWeb
    ): void {
        $this->pdo->prepare(
            'UPDATE producto SET Estado = ?, Stock = ?, MostrarEnWeb = ?, DestacadoWeb = ? WHERE IdProducto = ?'
        )->execute([$estado, $stock, $mostrarWeb, $destacadoWeb, $idProducto]);
    }

    /**
     * Sincroniza columnas del vehículo según el nuevo estado.
     * Réplica exacta del switch de vehiculos/cambiar_estado.php (Disponible /
     * Vendido / Reservado / resto).
     */
    public function sincronizarVehiculoSegunEstado(string $idVehiculo, string $estado): void
    {
        if ($estado === 'Disponible') {
            $this->pdo->prepare(
                'UPDATE vehiculo SET FechaVenta = NULL, ClienteReservaId = NULL, FechaReserva = NULL, SeniaReserva = NULL WHERE IdVehiculo = ?'
            )->execute([$idVehiculo]);
        } elseif ($estado === 'Vendido') {
            $this->pdo->prepare(
                'UPDATE vehiculo SET FechaVenta = CURDATE(), ClienteReservaId = NULL, FechaReserva = NULL, SeniaReserva = NULL WHERE IdVehiculo = ?'
            )->execute([$idVehiculo]);
        } elseif ($estado === 'Reservado') {
            $this->pdo->prepare(
                'UPDATE vehiculo SET FechaVenta = NULL WHERE IdVehiculo = ?'
            )->execute([$idVehiculo]);
        } else {
            $this->pdo->prepare(
                'UPDATE vehiculo SET FechaVenta = NULL, ClienteReservaId = NULL, FechaReserva = NULL, SeniaReserva = NULL WHERE IdVehiculo = ?'
            )->execute([$idVehiculo]);
        }
    }

    /**
     * Guarda los datos de reserva del vehículo. Origen: vehiculos/reservar.php.
     */
    public function guardarReserva(string $idVehiculo, int $clienteReservaId, ?float $senia): void
    {
        $this->pdo->prepare(
            'UPDATE vehiculo SET ClienteReservaId = ?, FechaReserva = NOW(), SeniaReserva = ?, FechaVenta = NULL WHERE IdVehiculo = ?'
        )->execute([$clienteReservaId, $senia, $idVehiculo]);
    }

    /**
     * Marca/desmarca destacado web del producto. Origen: vehiculos/toggle_destacado.php.
     */
    public function setDestacadoWeb(int $idProducto, int $valor): void
    {
        $this->pdo->prepare('UPDATE producto SET DestacadoWeb = ? WHERE IdProducto = ?')
            ->execute([$valor, $idProducto]);
    }

    /**
     * Actualiza visibilidad web (Mostrar + Destacado). Origen: vehiculos/toggle_web.php.
     */
    public function setPublicacionWeb(int $idProducto, int $mostrarWeb, int $destacadoWeb): void
    {
        $this->pdo->prepare('UPDATE producto SET MostrarEnWeb = ?, DestacadoWeb = ? WHERE IdProducto = ?')
            ->execute([$mostrarWeb, $destacadoWeb, $idProducto]);
    }

    /**
     * Oculta el producto del vehículo (soft-delete). Origen: vehiculos/eliminar.php.
     */
    public function ocultar(int $idProducto): void
    {
        $this->pdo->prepare(
            "UPDATE producto SET Estado = 'Oculto', MostrarEnWeb = 0, DestacadoWeb = 0 WHERE IdProducto = ?"
        )->execute([$idProducto]);
    }

    /**
     * @return array{IdProducto:mixed,OrdenWeb:mixed}|null
     */
    public function productoOrdenWeb(string $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.IdProducto, p.OrdenWeb
             FROM vehiculo v
             INNER JOIN producto p ON p.IdProducto = v.IdProducto
             WHERE v.IdVehiculo = ? AND p.TipoProducto = 'Moto'
             LIMIT 1"
        );
        $stmt->execute([$idVehiculo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{IdProducto:mixed,OrdenWeb:mixed}|null
     */
    public function productoOrdenWebVecino(int $ordenActual, string $direccion): ?array
    {
        $operador = $direccion === 'up' ? '<' : '>';
        $ordenSql = $direccion === 'up' ? 'DESC' : 'ASC';
        $stmt = $this->pdo->prepare(
            "SELECT p.IdProducto, p.OrdenWeb
             FROM vehiculo v
             INNER JOIN producto p ON p.IdProducto = v.IdProducto
             WHERE p.TipoProducto = 'Moto' AND p.OrdenWeb {$operador} ?
             ORDER BY p.OrdenWeb {$ordenSql}, v.IdVehiculo {$ordenSql}
             LIMIT 1"
        );
        $stmt->execute([$ordenActual]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function actualizarOrdenWeb(int $idProducto, int $orden): void
    {
        $this->pdo->prepare('UPDATE producto SET OrdenWeb = ? WHERE IdProducto = ?')
            ->execute([$orden, $idProducto]);
    }

    /**
     * Reserva el próximo Id de vehículo en la secuencia y lo devuelve.
     * Origen: INSERT INTO vehiculo_seq de vehiculos/crear.php.
     */
    public function reservarSecuenciaVehiculo(): int
    {
        $this->pdo->prepare('INSERT INTO vehiculo_seq (Id) VALUES (NULL)')->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * true si el slug aún no está en uso en producto.
     * Réplica de valorUnicoEnTabla($pdo, 'producto', 'Slug', $slug) usada en
     * vehiculos/crear.php.
     */
    public function slugProductoDisponible(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `producto` WHERE `Slug` = :valor');
        $stmt->execute([':valor' => $slug]);

        return (int) $stmt->fetchColumn() === 0;
    }

    public function slugProductoDisponibleExcepto(string $slug, int $idProducto): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `producto` WHERE `Slug` = :valor AND `IdProducto` <> :excluir');
        $stmt->execute([':valor' => $slug, ':excluir' => $idProducto]);
        return (int) $stmt->fetchColumn() === 0;
    }

    public function numeroMotorDisponible(string $numeroMotor, ?string $idVehiculoExcluir = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `vehiculo` WHERE `NumeroMotor` = :valor';
        $params = [':valor' => $numeroMotor];
        if ($idVehiculoExcluir !== null) {
            $sql .= ' AND `IdVehiculo` <> :excluir';
            $params[':excluir'] = $idVehiculoExcluir;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }

    public function siguienteOrdenWeb(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(OrdenWeb), 0) + 1 FROM producto WHERE TipoProducto = 'Moto'");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Inserta el producto 'Moto' del nuevo vehículo y devuelve su IdProducto.
     * Origen: INSERT INTO producto de vehiculos/crear.php (mismas columnas y
     * orden). Los valores nullable ya vienen resueltos por el caller.
     */
    public function insertarProductoMoto(
        string $nombre,
        ?string $slug,
        ?string $descripcion,
        ?string $descripcionWeb,
        float $costo,
        float $gastoTotal,
        float $precioVenta,
        ?float $precioDistribuidor,
        string $moneda,
        int $stock,
        string $estado,
        int $mostrarEnWeb,
        int $destacadoWeb,
        int $ordenWeb,
        string $textoBotonWeb,
        string $empresaRut
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO producto
                (Nombre, Slug, TipoProducto, Descripcion, DescripcionWeb, Costo, GastoTotal, PrecioVenta, PrecioDistribuidor, Moneda, Stock, Estado, MostrarEnWeb, DestacadoWeb, OrdenWeb, TextoBotonWeb, Empresa_RUT)
                VALUES (?, ?, 'Moto', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $nombre,
            $slug,
            $descripcion,
            $descripcionWeb,
            $costo,
            $gastoTotal,
            $precioVenta,
            $precioDistribuidor,
            $moneda,
            $stock,
            $estado,
            $mostrarEnWeb,
            $destacadoWeb,
            $ordenWeb,
            $textoBotonWeb,
            $empresaRut,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza el producto del vehículo durante la edición.
     * Origen: UPDATE producto de vehiculos/editar.php (mismas columnas y orden).
     * Los valores nullable ya vienen resueltos por el caller.
     */
    public function actualizarProductoEdicion(
        int $idProducto,
        string $nombre,
        ?string $slug,
        ?string $descripcion,
        ?string $descripcionWeb,
        float $costo,
        float $gastoTotal,
        float $precioVenta,
        ?float $precioDistribuidor,
        string $moneda,
        int $stock,
        string $estado,
        int $mostrarEnWeb,
        int $destacadoWeb,
        int $ordenWeb,
        string $textoBotonWeb
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE producto
                SET
                    Nombre = ?,
                    Slug = ?,
                    Descripcion = ?,
                    DescripcionWeb = ?,
                    Costo = ?,
                    GastoTotal = ?,
                    PrecioVenta = ?,
                    PrecioDistribuidor = ?,
                    Moneda = ?,
                    Stock = ?,
                    Estado = ?,
                    MostrarEnWeb = ?,
                    DestacadoWeb = ?,
                    OrdenWeb = ?,
                    TextoBotonWeb = ?
                WHERE IdProducto = ?"
        );
        $stmt->execute([
            $nombre,
            $slug,
            $descripcion,
            $descripcionWeb,
            $costo,
            $gastoTotal,
            $precioVenta,
            $precioDistribuidor,
            $moneda,
            $stock,
            $estado,
            $mostrarEnWeb,
            $destacadoWeb,
            $ordenWeb,
            $textoBotonWeb,
            $idProducto,
        ]);
    }

    /**
     * Actualiza la fila de vehiculo durante la edición. El estado decide (vía
     * CASE WHEN = 'Reservado') si se conservan o limpian los datos de reserva.
     * Origen: UPDATE vehiculo de vehiculos/editar.php (misma sentencia y params).
     */
    public function actualizarVehiculoEdicion(
        string $idVehiculo,
        string $numeroMotor,
        string $modelo,
        ?string $color,
        ?string $fechaVenta,
        ?int $numeroImportacion,
        ?float $tipoCambioImportacion,
        string $estado
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE vehiculo
                SET
                    NumeroMotor = ?,
                    Modelo = ?,
                    Color = ?,
                    FechaVenta = ?,
                    NumeroImportacion = ?,
                    TipoCambioImportacion = ?,
                    ClienteReservaId = CASE WHEN ? = 'Reservado' THEN ClienteReservaId ELSE NULL END,
                    FechaReserva = CASE WHEN ? = 'Reservado' THEN FechaReserva ELSE NULL END,
                    SeniaReserva = CASE WHEN ? = 'Reservado' THEN SeniaReserva ELSE NULL END
                WHERE IdVehiculo = ?"
        );
        $stmt->execute([
            $numeroMotor,
            $modelo,
            $color,
            $fechaVenta,
            $numeroImportacion,
            $tipoCambioImportacion,
            $estado,
            $estado,
            $estado,
            $idVehiculo,
        ]);
    }

    /**
     * Inserta la fila de vehiculo enlazada a su producto.
     * Origen: INSERT INTO vehiculo de vehiculos/crear.php (FechaIngreso = CURDATE()).
     */
    public function insertarVehiculo(
        string $idVehiculo,
        int $idProducto,
        string $numeroMotor,
        string $modelo,
        ?string $color,
        ?int $numeroImportacion,
        ?float $tipoCambioImportacion,
        ?string $fechaVenta
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vehiculo
                (IdVehiculo, IdProducto, NumeroMotor, Modelo, Color, NumeroImportacion, TipoCambioImportacion, FechaIngreso, FechaVenta)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)"
        );
        $stmt->execute([
            $idVehiculo,
            $idProducto,
            $numeroMotor,
            $modelo,
            $color,
            $numeroImportacion,
            $tipoCambioImportacion,
            $fechaVenta,
        ]);
    }

    /**
     * Devuelve una imagen del vehículo (o null si no existe).
     * Origen: SELECT de imagen actual del bloque de imágenes de
     * vehiculos/editar.php (misma sentencia y params).
     *
     * @return array<string, mixed>|null
     */
    public function buscarImagenVehiculo(string $idVehiculo, int $idImagen): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT IdImagen, RutaImagen, EsPrincipal, OrdenImagen FROM vehiculo_imagen WHERE IdVehiculo = ? AND IdImagen = ? LIMIT 1'
        );
        $stmt->execute([$idVehiculo, $idImagen]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function maxOrdenImagen(string $idVehiculo): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(OrdenImagen), 0) AS max_orden FROM vehiculo_imagen WHERE IdVehiculo = ?');
        $stmt->execute([$idVehiculo]);
        return (int) $stmt->fetchColumn();
    }

    public function tieneImagenPrincipal(string $idVehiculo): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vehiculo_imagen WHERE IdVehiculo = ? AND EsPrincipal = 1');
        $stmt->execute([$idVehiculo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function insertarImagen(string $idVehiculo, string $ruta, int $esPrincipal, int $orden): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO vehiculo_imagen (IdVehiculo, RutaImagen, EsPrincipal, OrdenImagen) VALUES (?, ?, ?, ?)');
        $stmt->execute([$idVehiculo, $ruta, $esPrincipal, $orden]);
    }

    /**
     * Quita la marca de principal a todas las imágenes del vehículo.
     * Origen: set_principal de vehiculos/editar.php.
     */
    public function desmarcarImagenesPrincipales(string $idVehiculo): void
    {
        $this->pdo->prepare('UPDATE vehiculo_imagen SET EsPrincipal = 0 WHERE IdVehiculo = ?')
            ->execute([$idVehiculo]);
    }

    /**
     * Marca como principal una imagen del vehículo. Origen: set_principal.
     */
    public function marcarImagenPrincipal(string $idVehiculo, int $idImagen): void
    {
        $this->pdo->prepare('UPDATE vehiculo_imagen SET EsPrincipal = 1 WHERE IdVehiculo = ? AND IdImagen = ?')
            ->execute([$idVehiculo, $idImagen]);
    }

    /**
     * Marca como principal una imagen por su Id (usado al reasignar la portada
     * tras un borrado). Origen: delete_image de vehiculos/editar.php.
     */
    public function marcarImagenPrincipalPorId(int $idImagen): void
    {
        $this->pdo->prepare('UPDATE vehiculo_imagen SET EsPrincipal = 1 WHERE IdImagen = ?')
            ->execute([$idImagen]);
    }

    /**
     * Borra una imagen del vehículo. Origen: delete_image de vehiculos/editar.php.
     */
    public function eliminarImagen(string $idVehiculo, int $idImagen): void
    {
        $this->pdo->prepare('DELETE FROM vehiculo_imagen WHERE IdVehiculo = ? AND IdImagen = ?')
            ->execute([$idVehiculo, $idImagen]);
    }

    /**
     * Lista los Ids de las imágenes del vehículo en el orden en que deben
     * recompactarse. Origen: delete_image de vehiculos/editar.php.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarImagenesParaReordenar(string $idVehiculo): array
    {
        $stmt = $this->pdo->prepare('SELECT IdImagen FROM vehiculo_imagen WHERE IdVehiculo = ? ORDER BY OrdenImagen ASC, IdImagen ASC');
        $stmt->execute([$idVehiculo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Asigna un nuevo OrdenImagen a una imagen. Origen: delete_image (recompactar)
     * y move_image (swap) de vehiculos/editar.php.
     */
    public function actualizarOrdenImagen(int $idImagen, int $orden): void
    {
        $this->pdo->prepare('UPDATE vehiculo_imagen SET OrdenImagen = ? WHERE IdImagen = ?')
            ->execute([$orden, $idImagen]);
    }

    /**
     * Busca la imagen vecina hacia 'up' (orden menor) o 'down' (orden mayor)
     * para hacer el swap de orden. Origen: move_image de vehiculos/editar.php
     * (misma sentencia con operador/orden dinámico).
     *
     * @return array<string, mixed>|null
     */
    public function buscarImagenVecina(string $idVehiculo, int $orden, string $direccion): ?array
    {
        $operador = $direccion === 'up' ? '<' : '>';
        $ordenSql = $direccion === 'up' ? 'DESC' : 'ASC';

        $stmt = $this->pdo->prepare(
            "SELECT IdImagen, OrdenImagen FROM vehiculo_imagen WHERE IdVehiculo = ? AND OrdenImagen {$operador} ? ORDER BY OrdenImagen {$ordenSql}, IdImagen {$ordenSql} LIMIT 1"
        );
        $stmt->execute([$idVehiculo, $orden]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}
