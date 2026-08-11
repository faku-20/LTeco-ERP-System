-- Comisiones de distribuidores, MFA y postventa técnica extendida.
-- Idempotente para MariaDB/MySQL recientes.

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS mfa_enabled TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS mfa_secret VARCHAR(190) NULL;

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS mfa_recovery_codes TEXT NULL;

CREATE TABLE IF NOT EXISTS distribuidor_comision (
    IdComision INT NOT NULL AUTO_INCREMENT,
    IdDistribuidor INT NOT NULL,
    IdVenta INT NOT NULL,
    BaseComision DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    Porcentaje DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    Monto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    Estado ENUM('Pendiente','Aprobada','Pagada','Anulada') NOT NULL DEFAULT 'Pendiente',
    FechaGenerada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaPago DATETIME NULL,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (IdComision),
    UNIQUE KEY uq_distribuidor_comision_venta (IdVenta),
    KEY idx_distribuidor_comision_distribuidor (IdDistribuidor, Estado, FechaGenerada),
    CONSTRAINT fk_distribuidor_comision_distribuidor
        FOREIGN KEY (IdDistribuidor) REFERENCES distribuidor(IdDistribuidor)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_distribuidor_comision_venta
        FOREIGN KEY (IdVenta) REFERENCES venta(IdVenta)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postventa_historial_tecnico (
    IdHistorialTecnico INT NOT NULL AUTO_INCREMENT,
    IdVehiculo VARCHAR(10) NOT NULL,
    IdVenta INT NULL,
    IdCliente INT NULL,
    IdService INT NULL,
    Diagnostico VARCHAR(700) NOT NULL,
    SolucionAplicada VARCHAR(700) NULL,
    Tecnico VARCHAR(120) NULL,
    Estado ENUM('Abierta','En reparación','En espera','Cerrada','Cancelada') NOT NULL DEFAULT 'Abierta',
    FechaApertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaInicioReparacion DATETIME NULL,
    FechaCierre DATETIME NULL,
    TiempoTotalMinutos INT NULL,
    TiempoEsperaMinutos INT NULL,
    Observaciones VARCHAR(700) NULL,
    IdUsuarioRegistra INT NULL,
    FechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdHistorialTecnico),
    KEY idx_postventa_historial_vehiculo (IdVehiculo, FechaApertura),
    KEY idx_postventa_historial_estado (Estado),
    KEY idx_postventa_historial_service (IdService)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postventa_repuesto_usado (
    IdRepuestoUsado INT NOT NULL AUTO_INCREMENT,
    IdHistorialTecnico INT NOT NULL,
    IdRepuesto INT NOT NULL,
    IdProducto INT NOT NULL,
    Cantidad INT NOT NULL,
    CostoUnitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    Moneda ENUM('UYU','USD') NOT NULL DEFAULT 'UYU',
    FechaUso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdRepuestoUsado),
    KEY idx_postventa_repuesto_historial (IdHistorialTecnico),
    KEY idx_postventa_repuesto_repuesto (IdRepuesto),
    CONSTRAINT fk_postventa_repuesto_historial
        FOREIGN KEY (IdHistorialTecnico) REFERENCES postventa_historial_tecnico(IdHistorialTecnico)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_postventa_repuesto_repuesto
        FOREIGN KEY (IdRepuesto) REFERENCES repuesto(IdRepuesto)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO distribuidor_comision (IdDistribuidor, IdVenta, BaseComision, Porcentaje, Monto, Estado, FechaGenerada)
SELECT
    v.DistribuidorVendedorId,
    v.IdVenta,
    COALESCE(v.Total, 0),
    COALESCE(d.ComisionPct, 0),
    ROUND(COALESCE(v.Total, 0) * COALESCE(d.ComisionPct, 0) / 100, 2),
    CASE WHEN COALESCE(v.EstadoVenta, 'Confirmada') = 'Anulada' THEN 'Anulada' ELSE 'Pendiente' END,
    COALESCE(v.FechaVenta, NOW())
FROM venta v
INNER JOIN distribuidor d ON d.IdDistribuidor = v.DistribuidorVendedorId
LEFT JOIN distribuidor_comision dc ON dc.IdVenta = v.IdVenta
WHERE v.DistribuidorVendedorId IS NOT NULL
  AND dc.IdComision IS NULL;
