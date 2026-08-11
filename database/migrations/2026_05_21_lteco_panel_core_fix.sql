-- Ltecobike panel - migración correctiva V2.19
-- Ejecutar sobre la base lteco_db antes de levantar el panel corregido.
-- Probado para MariaDB/MySQL modernos. Hacer backup antes de aplicar.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS vehiculo_seq (
    Id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE producto
    ADD COLUMN IF NOT EXISTS Slug VARCHAR(180) NULL AFTER Nombre,
    ADD COLUMN IF NOT EXISTS DescripcionWeb TEXT NULL AFTER Descripcion,
    ADD COLUMN IF NOT EXISTS MostrarEnWeb TINYINT(1) NOT NULL DEFAULT 0 AFTER Estado,
    ADD COLUMN IF NOT EXISTS DestacadoWeb TINYINT(1) NOT NULL DEFAULT 0 AFTER MostrarEnWeb,
    ADD COLUMN IF NOT EXISTS OrdenWeb INT NOT NULL DEFAULT 0 AFTER DestacadoWeb,
    ADD COLUMN IF NOT EXISTS TextoBotonWeb VARCHAR(80) NULL DEFAULT 'Consultar' AFTER OrdenWeb;

CREATE INDEX IF NOT EXISTS idx_producto_slug ON producto (Slug);
CREATE INDEX IF NOT EXISTS idx_producto_web ON producto (TipoProducto, MostrarEnWeb, DestacadoWeb, OrdenWeb, Estado, Stock);

ALTER TABLE vehiculo
    ADD COLUMN IF NOT EXISTS ClienteReservaId INT NULL AFTER FechaVenta,
    ADD COLUMN IF NOT EXISTS FechaReserva DATETIME NULL AFTER ClienteReservaId,
    ADD COLUMN IF NOT EXISTS SeniaReserva DECIMAL(12,2) NULL AFTER FechaReserva;

CREATE INDEX IF NOT EXISTS idx_vehiculo_producto ON vehiculo (IdProducto);
CREATE INDEX IF NOT EXISTS idx_vehiculo_reserva ON vehiculo (ClienteReservaId);

CREATE TABLE IF NOT EXISTS vehiculo_imagen (
    IdImagen INT UNSIGNED NOT NULL AUTO_INCREMENT,
    IdVehiculo VARCHAR(30) NOT NULL,
    RutaImagen VARCHAR(255) NOT NULL,
    EsPrincipal TINYINT(1) NOT NULL DEFAULT 0,
    OrdenImagen INT NOT NULL DEFAULT 0,
    FechaAlta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdImagen),
    KEY idx_vehiculo_imagen_vehiculo (IdVehiculo),
    KEY idx_vehiculo_imagen_principal (IdVehiculo, EsPrincipal),
    KEY idx_vehiculo_imagen_orden (IdVehiculo, OrdenImagen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE venta
    ADD COLUMN IF NOT EXISTS EstadoVenta VARCHAR(20) NOT NULL DEFAULT 'Confirmada' AFTER Moneda,
    ADD COLUMN IF NOT EXISTS MetodoPago VARCHAR(40) NULL DEFAULT 'Efectivo' AFTER EstadoVenta,
    ADD COLUMN IF NOT EXISTS MontoPagado DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER GananciaEstimada,
    ADD COLUMN IF NOT EXISTS SaldoPendiente DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER MontoPagado,
    ADD COLUMN IF NOT EXISTS TipoCambioAplicado DECIMAL(12,4) NULL AFTER SaldoPendiente,
    ADD COLUMN IF NOT EXISTS TipoTarjeta VARCHAR(20) NULL AFTER MetodoPago,
    ADD COLUMN IF NOT EXISTS MarcaTarjeta VARCHAR(40) NULL AFTER TipoTarjeta,
    ADD COLUMN IF NOT EXISTS CuotasTarjeta INT NULL AFTER MarcaTarjeta,
    ADD COLUMN IF NOT EXISTS FechaAnulacion DATETIME NULL,
    ADD COLUMN IF NOT EXISTS MotivoAnulacion TEXT NULL,
    ADD COLUMN IF NOT EXISTS UsuarioAnulacion VARCHAR(120) NULL;

ALTER TABLE venta
    MODIFY COLUMN EstadoVenta VARCHAR(20) NOT NULL DEFAULT 'Confirmada',
    MODIFY COLUMN MontoPagado DECIMAL(12,2) NOT NULL DEFAULT 0,
    MODIFY COLUMN SaldoPendiente DECIMAL(12,2) NOT NULL DEFAULT 0;

UPDATE venta
SET EstadoVenta = 'Confirmada'
WHERE EstadoVenta IS NULL OR EstadoVenta = '' OR EstadoVenta NOT IN ('Pendiente', 'Confirmada', 'Entregada', 'Anulada');

UPDATE venta
SET MontoPagado = Total
WHERE MontoPagado IS NULL OR MontoPagado = 0;

UPDATE venta
SET SaldoPendiente = GREATEST(0, Total - MontoPagado)
WHERE SaldoPendiente IS NULL;

CREATE INDEX IF NOT EXISTS idx_venta_cliente ON venta (Cliente_IdCliente);
CREATE INDEX IF NOT EXISTS idx_venta_estado_fecha ON venta (EstadoVenta, FechaVenta);

ALTER TABLE venta_detalle
    ADD COLUMN IF NOT EXISTS CostoUnitario DECIMAL(12,2) NULL AFTER PrecioUnitario,
    ADD COLUMN IF NOT EXISTS GananciaLinea DECIMAL(12,2) NULL AFTER Subtotal;

CREATE INDEX IF NOT EXISTS idx_venta_detalle_venta ON venta_detalle (Venta_IdVenta);
CREATE INDEX IF NOT EXISTS idx_venta_detalle_producto ON venta_detalle (Producto_IdProducto);

CREATE TABLE IF NOT EXISTS auditoria (
    IdAuditoria BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    IdUsuario INT NULL,
    Usuario VARCHAR(120) NOT NULL,
    Rol VARCHAR(40) NULL,
    Accion VARCHAR(80) NOT NULL,
    Modulo VARCHAR(80) NOT NULL,
    Detalle TEXT NULL,
    ExtraJson JSON NULL,
    Ip VARCHAR(64) NULL,
    UserAgent VARCHAR(250) NULL,
    FechaHora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdAuditoria),
    KEY idx_auditoria_fecha (FechaHora),
    KEY idx_auditoria_usuario (Usuario),
    KEY idx_auditoria_modulo_accion (Modulo, Accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
