-- Web Push de pedidos web: outbox idempotente y trazabilidad por dispositivo.
-- Aplicar una sola vez, después de que existan automation_event y web_push_delivery.

ALTER TABLE automation_event
  ADD COLUMN IdempotencyKey VARCHAR(160) NULL AFTER EventKey,
  ADD COLUMN Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER ErrorMessage,
  ADD COLUMN FechaUltimoIntento DATETIME NULL AFTER Intentos,
  ADD UNIQUE KEY uq_automation_event_idempotency (IdempotencyKey);

ALTER TABLE web_push_delivery
  ADD COLUMN IdPedido BIGINT UNSIGNED NULL AFTER IdSubscription,
  ADD COLUMN Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER Estado,
  ADD COLUMN CodigoError VARCHAR(64) NULL AFTER Intentos,
  ADD COLUMN FechaUltimoIntento DATETIME NULL AFTER CodigoError,
  ADD COLUMN FechaEntrega DATETIME NULL AFTER FechaUltimoIntento,
  ADD INDEX idx_push_delivery_pedido (IdPedido);
