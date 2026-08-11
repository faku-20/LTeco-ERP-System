-- Datos completos de cliente/empresa y pagos nuevos.
-- Ejecutar sobre la base lteco_db.

SET NAMES utf8mb4;

ALTER TABLE cliente
    ADD COLUMN IF NOT EXISTS TipoFiscal VARCHAR(30) NOT NULL DEFAULT 'Consumidor final' AFTER NombreApellido,
    ADD COLUMN IF NOT EXISTS Cedula VARCHAR(40) NULL AFTER Correo,
    ADD COLUMN IF NOT EXISTS Direccion VARCHAR(255) NULL AFTER Cedula,
    ADD COLUMN IF NOT EXISTS RUT VARCHAR(40) NULL AFTER Direccion;

UPDATE cliente
SET TipoFiscal = 'Empresa/RUT'
WHERE (RUT IS NOT NULL AND RUT <> '')
  AND (TipoFiscal IS NULL OR TipoFiscal = '' OR TipoFiscal = 'Consumidor final');

ALTER TABLE empresa
    ADD COLUMN IF NOT EXISTS RazonSocial VARCHAR(160) NULL AFTER Nombre,
    ADD COLUMN IF NOT EXISTS Direccion VARCHAR(255) NULL AFTER Telefono;
