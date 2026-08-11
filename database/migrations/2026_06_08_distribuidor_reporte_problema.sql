-- Tabla de reportes de problemas enviados por distribuidores.
-- Ejecutar sobre la base lteco_db.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS distribuidor_reporte_problema (
    IdReporte INT UNSIGNED NOT NULL AUTO_INCREMENT,
    IdDistribuidor INT NOT NULL,
    IdUsuario INT NOT NULL,
    Mensaje TEXT NOT NULL,
    ImagenRuta VARCHAR(255) NULL DEFAULT NULL,
    EstadoInterno ENUM('Nuevo','Revisado','Resuelto') NOT NULL DEFAULT 'Nuevo',
    FechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FechaResolucion DATETIME NULL DEFAULT NULL,
    UsuarioResolucionId INT NULL DEFAULT NULL,
    PRIMARY KEY (IdReporte),
    KEY idx_dist_estado (IdDistribuidor, EstadoInterno),
    KEY idx_estado_fecha (EstadoInterno, FechaCreacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
