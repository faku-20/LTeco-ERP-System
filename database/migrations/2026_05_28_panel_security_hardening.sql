-- Hardening de seguridad para exponer el panel detrás del login propio.
-- Idempotente: puede ejecutarse más de una vez en MariaDB/MySQL recientes.

CREATE TABLE IF NOT EXISTS login_attempts (
    IdAttempt BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Ip VARCHAR(45) NOT NULL,
    UsuarioIntentado VARCHAR(190) NOT NULL,
    UserAgent VARCHAR(255) NULL,
    Resultado VARCHAR(30) NOT NULL,
    IntentosRecientes INT UNSIGNED NOT NULL DEFAULT 0,
    BloqueadoHasta DATETIME NULL,
    FechaHora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdAttempt),
    KEY idx_login_attempts_lookup (Ip, UsuarioIntentado, FechaHora),
    KEY idx_login_attempts_block (Ip, UsuarioIntentado, BloqueadoHasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    IdAuditoria BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    IdUsuario INT NULL,
    Usuario VARCHAR(120) NULL,
    Rol VARCHAR(60) NULL,
    Accion VARCHAR(80) NOT NULL,
    Modulo VARCHAR(80) NOT NULL,
    Detalle TEXT NULL,
    ExtraJson JSON NULL,
    Ip VARCHAR(45) NULL,
    UserAgent VARCHAR(255) NULL,
    FechaHora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdAuditoria),
    KEY idx_auditoria_fecha (FechaHora),
    KEY idx_auditoria_usuario (Usuario),
    KEY idx_auditoria_accion (Accion),
    KEY idx_auditoria_modulo (Modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS mfa_enabled TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS mfa_secret VARCHAR(190) NULL;

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS mfa_recovery_codes TEXT NULL;
