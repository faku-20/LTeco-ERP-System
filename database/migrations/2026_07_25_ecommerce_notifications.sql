CREATE TABLE IF NOT EXISTS internal_alert (
    IdAlert BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Tipo VARCHAR(80) NOT NULL,
    Severidad ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    Titulo VARCHAR(220) NOT NULL,
    Cuerpo TEXT NULL,
    Estado ENUM('abierta','leida','cerrada') NOT NULL DEFAULT 'abierta',
    SourceType VARCHAR(80) NULL,
    SourceId BIGINT NULL,
    ResponsableUsuarioId INT NULL,
    FechaEvento DATETIME NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alert_source (Tipo, SourceType, SourceId),
    INDEX idx_alert_estado_fecha (Estado, FechaAlta),
    INDEX idx_alert_responsable (ResponsableUsuarioId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
