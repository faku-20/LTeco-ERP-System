-- ERP V3
-- Agrega comisión interna del usuario para ventas realizadas por distribuidores.
-- ComisionPct queda como comisión por venta directa.
-- ComisionDistribuidorPct se usa para comisión interna cuando vende un distribuidor.

ALTER TABLE usuario
  ADD COLUMN IF NOT EXISTS ComisionDistribuidorPct DECIMAL(5,2) NOT NULL DEFAULT 0.00
  AFTER ComisionPct;
