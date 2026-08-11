CREATE TABLE IF NOT EXISTS repuesto_caja (
    IdCaja BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Codigo VARCHAR(20) NOT NULL UNIQUE,
    TokenUuid CHAR(36) NOT NULL UNIQUE,
    Nombre VARCHAR(160) NULL,
    Ubicacion VARCHAR(160) NULL,
    Estado ENUM('Activa','Archivada') NOT NULL DEFAULT 'Activa',
    Observaciones TEXT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_repuesto_caja_estado (Estado),
    INDEX idx_repuesto_caja_codigo (Codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repuesto_caja_item (
    IdCajaItem BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdCaja BIGINT UNSIGNED NOT NULL,
    IdRepuesto INT NOT NULL,
    Cantidad INT NOT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repuesto_caja_item (IdCaja, IdRepuesto),
    INDEX idx_repuesto_caja_item_repuesto (IdRepuesto),
    CONSTRAINT fk_repuesto_caja_item_caja FOREIGN KEY (IdCaja) REFERENCES repuesto_caja (IdCaja) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_repuesto_caja_item_repuesto FOREIGN KEY (IdRepuesto) REFERENCES repuesto (IdRepuesto) ON UPDATE CASCADE,
    CONSTRAINT chk_repuesto_caja_item_cantidad CHECK (Cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repuesto_caja_movimiento (
    IdMovimiento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdCaja BIGINT UNSIGNED NOT NULL,
    IdRepuesto INT NULL,
    Tipo ENUM('CREAR_CAJA','INGRESO_NUEVO','UBICACION_EXISTENTE','ARCHIVAR_CAJA') NOT NULL,
    Cantidad INT NOT NULL DEFAULT 0,
    StockAnterior INT NULL,
    StockNuevo INT NULL,
    IdUsuario INT NULL,
    Detalle VARCHAR(700) NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_repuesto_caja_mov_caja_fecha (IdCaja, FechaAlta),
    INDEX idx_repuesto_caja_mov_repuesto_fecha (IdRepuesto, FechaAlta),
    CONSTRAINT fk_repuesto_caja_mov_caja FOREIGN KEY (IdCaja) REFERENCES repuesto_caja (IdCaja) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_repuesto_caja_mov_repuesto FOREIGN KEY (IdRepuesto) REFERENCES repuesto (IdRepuesto) ON UPDATE CASCADE,
    CONSTRAINT fk_repuesto_caja_mov_usuario FOREIGN KEY (IdUsuario) REFERENCES usuario (IdUsuario) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_repuesto_caja_mov_cantidad CHECK (Cantidad >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
