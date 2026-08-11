-- Stock, pedidos y trazabilidad de ventas para distribuidores.
-- Ejecutar sobre la base lteco_db.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS distribuidor_stock (
    IdStock INT UNSIGNED NOT NULL AUTO_INCREMENT,
    IdDistribuidor INT NOT NULL,
    TipoItem ENUM('Vehiculo','Repuesto') NOT NULL DEFAULT 'Repuesto',
    IdVehiculo VARCHAR(30) NULL,
    IdRepuesto INT NULL,
    Cantidad INT NOT NULL DEFAULT 0,
    PrecioVenta DECIMAL(12,2) NOT NULL DEFAULT 0,
    PrecioMinimo DECIMAL(12,2) NOT NULL DEFAULT 0,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (IdStock),
    KEY idx_distribuidor_stock_distribuidor (IdDistribuidor),
    KEY idx_distribuidor_stock_vehiculo (IdVehiculo),
    KEY idx_distribuidor_stock_repuesto (IdRepuesto),
    CONSTRAINT fk_distribuidor_stock_distribuidor
        FOREIGN KEY (IdDistribuidor) REFERENCES distribuidor(IdDistribuidor)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distribuidor_pedido (
    IdPedido INT UNSIGNED NOT NULL AUTO_INCREMENT,
    IdDistribuidor INT NOT NULL,
    TipoItem ENUM('Vehiculo','Repuesto') NOT NULL DEFAULT 'Repuesto',
    IdVehiculo VARCHAR(30) NULL,
    IdRepuesto INT NULL,
    Cantidad INT NOT NULL DEFAULT 1,
    Estado ENUM('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
    Observaciones TEXT NULL,
    IdUsuarioSolicita INT NULL,
    FechaPedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaResolucion DATETIME NULL,
    PRIMARY KEY (IdPedido),
    KEY idx_distribuidor_pedido_distribuidor (IdDistribuidor),
    KEY idx_distribuidor_pedido_estado (Estado, FechaPedido),
    KEY idx_distribuidor_pedido_vehiculo (IdVehiculo),
    KEY idx_distribuidor_pedido_repuesto (IdRepuesto),
    CONSTRAINT fk_distribuidor_pedido_distribuidor
        FOREIGN KEY (IdDistribuidor) REFERENCES distribuidor(IdDistribuidor)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE venta
    ADD COLUMN IF NOT EXISTS DistribuidorVendedorId INT NULL AFTER Distribuidor_IdDistribuidor,
    ADD COLUMN IF NOT EXISTS UsuarioVendedorId INT NULL AFTER DistribuidorVendedorId;

CREATE INDEX IF NOT EXISTS idx_venta_distribuidor_vendedor ON venta (DistribuidorVendedorId, FechaVenta);
CREATE INDEX IF NOT EXISTS idx_venta_usuario_vendedor ON venta (UsuarioVendedorId, FechaVenta);
