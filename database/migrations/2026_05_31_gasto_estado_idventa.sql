-- Ltecobike V3
-- Permite vincular gastos automáticos con ventas y anularlos sin borrarlos.
-- Los totales operativos de gastos deben considerar solo Estado = 'Activo'.

ALTER TABLE gasto
  ADD COLUMN IF NOT EXISTS IdVenta INT NULL AFTER IdGasto,
  ADD COLUMN IF NOT EXISTS Estado ENUM('Activo','Anulado') NOT NULL DEFAULT 'Activo' AFTER TipoCambioAplicado,
  ADD COLUMN IF NOT EXISTS FechaAnulacion DATETIME NULL AFTER Estado,
  ADD COLUMN IF NOT EXISTS Origen VARCHAR(50) NOT NULL DEFAULT 'Manual' AFTER FechaAnulacion;

UPDATE gasto
SET
  IdVenta = CAST(REGEXP_REPLACE(SUBSTRING_INDEX(Concepto, '#', -1), '[^0-9].*$', '') AS UNSIGNED),
  Estado = 'Activo',
  Origen = 'Venta'
WHERE IdVenta IS NULL
  AND Categoria = 'Comisiones'
  AND Concepto LIKE '%Venta%#%'
  AND REGEXP_REPLACE(SUBSTRING_INDEX(Concepto, '#', -1), '[^0-9].*$', '') REGEXP '^[0-9]+$';

-- Sincroniza gastos de ventas que ya estaban anuladas antes de esta migración.
UPDATE gasto g
INNER JOIN venta v ON v.IdVenta = g.IdVenta
SET
  g.Estado = 'Anulado',
  g.FechaAnulacion = COALESCE(g.FechaAnulacion, v.FechaAnulacion, NOW()),
  g.Observaciones = TRIM(CONCAT(
    COALESCE(NULLIF(g.Observaciones, ''), ''),
    CASE
      WHEN COALESCE(NULLIF(g.Observaciones, ''), '') = '' THEN ''
      ELSE '\n'
    END,
    '[ANULADO MIGRACION] Venta ya anulada'
  ))
WHERE COALESCE(v.EstadoVenta, 'Confirmada') = 'Anulada'
  AND COALESCE(g.Estado, 'Activo') <> 'Anulado';
