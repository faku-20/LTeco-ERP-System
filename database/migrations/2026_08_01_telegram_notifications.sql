-- Telegram interno para avisos de nuevas ventas web.
-- Aplicar una sola vez antes de activar LTECO_TELEGRAM_ENABLED=1.

CREATE TABLE IF NOT EXISTS telegram_delivery (
    IdDelivery BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Tipo VARCHAR(80) NOT NULL,
    IdReferencia BIGINT UNSIGNED NULL,
    ChatId VARCHAR(80) NOT NULL,
    Estado VARCHAR(32) NOT NULL,
    TelegramMessageId BIGINT NULL,
    ErrorMessage TEXT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_telegram_tipo_ref_chat (Tipo, IdReferencia, ChatId),
    INDEX idx_telegram_estado_fecha (Estado, FechaAlta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
