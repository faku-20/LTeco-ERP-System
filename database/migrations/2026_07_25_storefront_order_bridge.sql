-- Vincula el UUID estable del pedido Laravel con el pedido operativo del panel.
-- Los datos se limitan a lo necesario para facturación, contacto y entrega.

ALTER TABLE ecommerce_pedido
  ADD COLUMN IF NOT EXISTS StorefrontOrderUuid CHAR(36) NULL AFTER IdPedido,
  ADD COLUMN IF NOT EXISTS StorefrontReservationId CHAR(36) NULL AFTER StorefrontOrderUuid,
  ADD UNIQUE INDEX IF NOT EXISTS uq_ecommerce_pedido_storefront_uuid (StorefrontOrderUuid),
  ADD UNIQUE INDEX IF NOT EXISTS uq_ecommerce_pedido_storefront_reservation (StorefrontReservationId);
