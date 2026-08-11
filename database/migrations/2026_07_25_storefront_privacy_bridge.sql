CREATE TABLE IF NOT EXISTS storefront_privacy_request (
    RequestUuid CHAR(36) NOT NULL PRIMARY KEY,
    UserUuid CHAR(36) NOT NULL,
    Tipo ENUM('access','correction','suppression','objection') NOT NULL,
    Estado ENUM('submitted','in_review','resolved','rejected') NOT NULL DEFAULT 'submitted',
    Nombre VARCHAR(220) NOT NULL,
    Correo VARCHAR(190) NOT NULL,
    Detalle TEXT NULL,
    VenceEn DATETIME NOT NULL,
    Respuesta TEXT NULL,
    ResueltaPor INT NULL,
    ResueltaEn DATETIME NULL,
    NotificacionEncolada TINYINT(1) NOT NULL DEFAULT 0,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_storefront_privacy_status_due (Estado,VenceEn),
    CONSTRAINT fk_storefront_privacy_user FOREIGN KEY (ResueltaPor) REFERENCES usuario (IdUsuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE storefront_privacy_request ADD COLUMN IF NOT EXISTS NotificacionEncolada TINYINT(1) NOT NULL DEFAULT 0 AFTER ResueltaEn;
