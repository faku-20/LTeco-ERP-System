ALTER TABLE repuesto_caja
    ADD COLUMN IF NOT EXISTS Nombre VARCHAR(160) NULL AFTER TokenUuid;

UPDATE repuesto_caja
   SET Nombre = Codigo
 WHERE Nombre IS NULL OR TRIM(Nombre) = '';
