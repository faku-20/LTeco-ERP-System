-- Agrega TC histórico a gastos para conversión correcta por importación
ALTER TABLE gasto
    ADD COLUMN IF NOT EXISTS TipoCambioAplicado DECIMAL(10,4) NOT NULL DEFAULT 1.0000
        COMMENT 'TC de la importación más reciente al momento de crear el gasto';

-- Poblar gastos existentes con el TC de la importación más reciente
UPDATE gasto g
SET TipoCambioAplicado = COALESCE(
    (SELECT TipoCambioUSD FROM importacion WHERE Activa = 1 ORDER BY Numero DESC LIMIT 1),
    44.00
)
WHERE TipoCambioAplicado = 1.0000 OR TipoCambioAplicado IS NULL;
