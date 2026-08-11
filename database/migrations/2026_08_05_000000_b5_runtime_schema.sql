-- B5: schema extraido de ensureSchema runtime.
-- No destructiva; debe ejecutarse desde scripts/migrate.sh, nunca desde request web.

CREATE TABLE IF NOT EXISTS ai_instruction_entry (
    IdInstruction INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Clave VARCHAR(80) NOT NULL UNIQUE,
    Titulo VARCHAR(160) NOT NULL,
    Cuerpo TEXT NULL,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    ActualizadoPor INT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_instruction_activo (Activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commercial_lead (
    IdLead BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdCliente INT NULL,
    IdVehiculo VARCHAR(10) NULL,
    IdVenta INT NULL,
    Origen VARCHAR(64) NOT NULL DEFAULT 'whatsapp',
    Estado VARCHAR(32) NOT NULL DEFAULT 'nuevo',
    Prioridad VARCHAR(24) NOT NULL DEFAULT 'media',
    Nombre VARCHAR(160) NOT NULL,
    Telefono VARCHAR(40) NULL,
    Email VARCHAR(160) NULL,
    Mensaje TEXT NULL,
    ResumenInteres TEXT NULL,
    ResponsableUsuarioId INT NULL,
    ProximoContacto DATETIME NULL,
    FechaCierre DATETIME NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lead_estado_fecha (Estado, ProximoContacto),
    INDEX idx_lead_origen_fecha (Origen, FechaAlta),
    INDEX idx_lead_telefono (Telefono),
    INDEX idx_lead_responsable (ResponsableUsuarioId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commercial_inbox_message (
    IdInbox BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdLead BIGINT UNSIGNED NULL,
    IdCliente INT NULL,
    IdVehiculo VARCHAR(10) NULL,
    Canal VARCHAR(32) NOT NULL DEFAULT 'whatsapp',
    Direccion VARCHAR(16) NOT NULL DEFAULT 'inbound',
    Estado VARCHAR(32) NOT NULL DEFAULT 'nuevo',
    ExternalId VARCHAR(160) NULL,
    CuentaOrigen VARCHAR(120) NULL,
    RemitenteNombre VARCHAR(160) NULL,
    RemitenteHandle VARCHAR(160) NULL,
    Telefono VARCHAR(40) NULL,
    Email VARCHAR(160) NULL,
    Mensaje TEXT NOT NULL,
    ReplyToWaMessageId VARCHAR(255) NULL,
    ReplyToModelo VARCHAR(64) NULL,
    AiIntent VARCHAR(64) NULL,
    AiPrioridad VARCHAR(32) NULL,
    AiResumen TEXT NULL,
    AiRespuestaSugerida TEXT NULL,
    AiError TEXT NULL,
    FechaClasificacion DATETIME NULL,
    RawPayload LONGTEXT NULL,
    FechaRecibido DATETIME NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inbox_channel_external (Canal, ExternalId),
    INDEX idx_inbox_canal_estado_fecha (Canal, Estado, FechaRecibido),
    INDEX idx_inbox_direccion (Direccion),
    INDEX idx_inbox_telefono (Telefono),
    INDEX idx_inbox_lead (IdLead)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE commercial_inbox_message ADD COLUMN IF NOT EXISTS ReplyToWaMessageId VARCHAR(255) NULL AFTER Mensaje;
ALTER TABLE commercial_inbox_message ADD COLUMN IF NOT EXISTS ReplyToModelo VARCHAR(64) NULL AFTER ReplyToWaMessageId;

CREATE TABLE IF NOT EXISTS ai_conversation_example (
    IdExample BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdInboxPregunta BIGINT UNSIGNED NOT NULL,
    IdInboxRespuesta BIGINT UNSIGNED NOT NULL,
    Canal VARCHAR(32) NOT NULL DEFAULT 'whatsapp',
    Pregunta TEXT NOT NULL,
    Respuesta TEXT NOT NULL,
    FechaConversacion DATETIME NOT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_example_respuesta (IdInboxRespuesta),
    INDEX idx_ai_example_pregunta (IdInboxPregunta),
    INDEX idx_ai_example_fecha (FechaConversacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ia_accion_sugerida (
    IdAccion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    TipoAccion VARCHAR(80) NOT NULL,
    IdLead BIGINT UNSIGNED NULL,
    IdInbox BIGINT UNSIGNED NULL,
    IdCliente INT NULL,
    ClienteNombre VARCHAR(160) NULL,
    ClienteTelefono VARCHAR(40) NULL,
    IdVehiculo VARCHAR(10) NULL,
    VehiculoTexto VARCHAR(160) NULL,
    ResponsableUsuarioId INT NULL,
    ResponsableNombre VARCHAR(160) NULL,
    Prioridad ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
    Estado ENUM('pendiente','confirmada','rechazada','ejecutada','error') NOT NULL DEFAULT 'pendiente',
    MensajeOrigen TEXT NULL,
    Motivo TEXT NULL,
    Payload LONGTEXT NULL,
    FechaSugerida DATETIME NULL,
    FechaConfirmacion DATETIME NULL,
    FechaEjecucion DATETIME NULL,
    ConfirmadaPor INT NULL,
    EjecutadaPor INT NULL,
    ResultadoEjecucion TEXT NULL,
    ErrorMessage TEXT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ia_tipo_estado_fecha (TipoAccion, Estado, FechaAlta),
    INDEX idx_ia_lead (IdLead),
    INDEX idx_ia_inbox (IdInbox)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_usage_log (
    IdUsage BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdUsuario INT NULL,
    Ruta VARCHAR(120) NOT NULL,
    Accion VARCHAR(120) NULL,
    Provider VARCHAR(80) NULL,
    PromptChars INT UNSIGNED NOT NULL DEFAULT 0,
    Estado VARCHAR(40) NOT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_usage_usuario_ruta_fecha (IdUsuario, Ruta, FechaAlta),
    INDEX idx_ai_usage_estado (Estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crm_visita (
    IdVisita BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdLead BIGINT UNSIGNED NULL,
    IdInboxOrigen BIGINT UNSIGNED NULL,
    IdCliente INT NULL,
    IdVehiculo VARCHAR(10) NULL,
    ResponsableUsuarioId INT NULL,
    ClienteNombre VARCHAR(160) NOT NULL,
    ClienteTelefono VARCHAR(40) NULL,
    ClienteCorreo VARCHAR(190) NULL,
    VehiculoTexto VARCHAR(160) NULL,
    ResponsableNombre VARCHAR(160) NULL,
    Canal VARCHAR(40) NOT NULL DEFAULT 'WhatsApp',
    FechaVisita DATETIME NOT NULL,
    HoraConfirmada TINYINT(1) NOT NULL DEFAULT 1,
    StorefrontRequestUuid CHAR(36) NULL,
    Estado ENUM('agendada','reprogramada','asistio','no_asistio','cancelada','cerrada') NOT NULL DEFAULT 'agendada',
    Observaciones TEXT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_visita_storefront_request (StorefrontRequestUuid),
    INDEX idx_visita_fecha_estado (FechaVisita, Estado),
    INDEX idx_visita_telefono (ClienteTelefono),
    INDEX idx_visita_responsable (ResponsableUsuarioId),
    INDEX idx_visita_lead (IdLead)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE crm_visita ADD COLUMN IF NOT EXISTS HoraConfirmada TINYINT(1) NOT NULL DEFAULT 1 AFTER FechaVisita;
ALTER TABLE crm_visita ADD COLUMN IF NOT EXISTS StorefrontRequestUuid CHAR(36) NULL AFTER IdVisita;
ALTER TABLE crm_visita ADD COLUMN IF NOT EXISTS ClienteCorreo VARCHAR(190) NULL AFTER ClienteTelefono;
CREATE UNIQUE INDEX IF NOT EXISTS uq_crm_visita_storefront_request ON crm_visita (StorefrontRequestUuid);

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

CREATE TABLE IF NOT EXISTS n8n_webhook_setting (
    IdSetting INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    EventKey VARCHAR(80) NOT NULL UNIQUE,
    Label VARCHAR(160) NOT NULL,
    WebhookUrl TEXT NULL,
    Enabled TINYINT(1) NOT NULL DEFAULT 0,
    TimeoutSeconds INT UNSIGNED NOT NULL DEFAULT 10,
    Secret VARCHAR(255) NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_n8n_setting_enabled (Enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS n8n_webhook_log (
    IdLog BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    EventKey VARCHAR(80) NOT NULL,
    Status VARCHAR(32) NOT NULL,
    SourceType VARCHAR(80) NULL,
    SourceId BIGINT NULL,
    WebhookUrl TEXT NULL,
    HttpStatus INT NULL,
    ResponseBody LONGTEXT NULL,
    ErrorMessage TEXT NULL,
    Payload LONGTEXT NULL,
    SentAt DATETIME NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_n8n_log_event_fecha (EventKey, FechaAlta),
    INDEX idx_n8n_log_status (Status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automation_event (
    IdEvent BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    EventKey VARCHAR(80) NOT NULL,
    IdempotencyKey VARCHAR(160) NULL UNIQUE,
    Status VARCHAR(32) NOT NULL DEFAULT 'pending',
    SourceType VARCHAR(80) NULL,
    SourceId BIGINT NULL,
    Payload LONGTEXT NULL,
    ErrorMessage TEXT NULL,
    Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FechaUltimoIntento DATETIME NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaProcesado DATETIME NULL,
    INDEX idx_automation_status_fecha (Status, FechaAlta),
    INDEX idx_automation_event_key (EventKey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS web_push_subscription (
    IdSubscription BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdUsuario INT NOT NULL,
    Endpoint TEXT NOT NULL,
    EndpointHash CHAR(64) NOT NULL UNIQUE,
    PublicKey VARCHAR(255) NOT NULL,
    AuthToken VARCHAR(255) NOT NULL,
    ContentEncoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
    UserAgent VARCHAR(500) NULL,
    Activa TINYINT(1) NOT NULL DEFAULT 1,
    UltimoEnvio DATETIME NULL,
    UltimoError TEXT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_push_usuario_activa (IdUsuario, Activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS web_push_delivery (
    IdDelivery BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IdEvent BIGINT UNSIGNED NOT NULL,
    IdSubscription BIGINT UNSIGNED NOT NULL,
    IdPedido BIGINT UNSIGNED NULL,
    Estado VARCHAR(32) NOT NULL,
    Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    CodigoError VARCHAR(64) NULL,
    FechaUltimoIntento DATETIME NULL,
    FechaEntrega DATETIME NULL,
    ErrorMessage TEXT NULL,
    FechaAlta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_event_subscription (IdEvent, IdSubscription),
    INDEX idx_push_delivery_estado (Estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS notificacion_whatsapp (
    IdNotificacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Tipo ENUM('venta','service') NOT NULL,
    IdReferencia INT NOT NULL,
    Telefono VARCHAR(30) NOT NULL,
    Template VARCHAR(100) NOT NULL,
    Estado ENUM('enviado','error','omitido') NOT NULL DEFAULT 'omitido',
    WaMessageId VARCHAR(255) NULL,
    EstadoEntrega VARCHAR(40) NULL,
    RespuestaMeta TEXT NULL,
    RespuestaWebhook TEXT NULL,
    FechaEstadoMeta DATETIME NULL,
    FechaEnvio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE notificacion_whatsapp ADD COLUMN IF NOT EXISTS WaMessageId VARCHAR(255) NULL;
ALTER TABLE notificacion_whatsapp ADD COLUMN IF NOT EXISTS EstadoEntrega VARCHAR(40) NULL;
ALTER TABLE notificacion_whatsapp ADD COLUMN IF NOT EXISTS RespuestaWebhook TEXT NULL;
ALTER TABLE notificacion_whatsapp ADD COLUMN IF NOT EXISTS FechaEstadoMeta DATETIME NULL;

CREATE TABLE IF NOT EXISTS whatsapp_test_reset (
    Telefono VARCHAR(30) PRIMARY KEY,
    Estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    FechaSolicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FechaClaim DATETIME NULL,
    FechaConsumo DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_media_cache (
    SourceKey CHAR(64) PRIMARY KEY,
    Url TEXT NOT NULL,
    LocalPath VARCHAR(500) NULL,
    FileHash CHAR(64) NULL,
    MimeType VARCHAR(80) NOT NULL,
    MediaId VARCHAR(255) NOT NULL,
    RespuestaMeta TEXT NULL,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_media_media_id (MediaId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS WaEnabled TINYINT(1) NULL DEFAULT NULL;
ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS WaPhoneId VARCHAR(80) NULL;
ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS WaToken TEXT NULL;
ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS WaTplVenta VARCHAR(100) NULL;
ALTER TABLE configuracion ADD COLUMN IF NOT EXISTS WaTplService VARCHAR(100) NULL;
ALTER TABLE configuracion MODIFY COLUMN WaEnabled TINYINT(1) NULL DEFAULT NULL;
