-- Ltecobike V3
-- Agrega el evento 'NOTIFICACION_WA' al enum de service_historial.TipoEvento.
-- Sin esto, postventa/marcar_notificado_wa.php fallaba con STRICT_TRANS_TABLES
-- ("Data truncated for column 'TipoEvento'") al registrar el recordatorio WhatsApp.
--
-- Idempotente: MODIFY COLUMN reescribe la definición completa, así que volver a
-- correrlo deja el mismo resultado. No borra datos ni altera el significado de los
-- eventos existentes (sólo amplía los valores permitidos). Conserva NOT NULL y el
-- DEFAULT 'NOTA' originales.

ALTER TABLE service_historial
    MODIFY COLUMN TipoEvento
        ENUM('CREADO','NOTA','REALIZADO','CANCELADO','VENCIDO','SISTEMA','NOTIFICACION_WA')
        NOT NULL DEFAULT 'NOTA';
