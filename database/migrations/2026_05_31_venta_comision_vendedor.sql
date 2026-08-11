-- Guarda en venta el monto de comision del usuario vendedor.

ALTER TABLE venta
    ADD COLUMN IF NOT EXISTS ComisionVendedor DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER ComisionDistribuidor;

UPDATE venta v
SET v.ComisionVendedor = COALESCE((
    SELECT SUM(g.Monto)
    FROM gasto g
    WHERE g.Categoria = 'Comisiones'
      AND g.Concepto IN (
          CONCAT('Comisión vendedor - Venta #', v.IdVenta),
          CONCAT('Comisión vendedor - Venta distribuidor #', v.IdVenta)
      )
), 0.00)
WHERE v.ComisionVendedor = 0.00;
