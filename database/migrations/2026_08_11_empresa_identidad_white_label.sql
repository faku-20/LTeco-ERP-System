-- LTeco ERP System - identidad configurable de empresa cliente

ALTER TABLE empresa
  ADD COLUMN IF NOT EXISTS Favicon varchar(500) DEFAULT NULL AFTER Logo,
  ADD COLUMN IF NOT EXISTS ColorPrimario varchar(7) DEFAULT NULL AFTER Favicon,
  ADD COLUMN IF NOT EXISTS ColorSecundario varchar(7) DEFAULT NULL AFTER ColorPrimario,
  ADD COLUMN IF NOT EXISTS SitioWeb varchar(255) DEFAULT NULL AFTER Direccion,
  ADD COLUMN IF NOT EXISTS PieDocumentos text DEFAULT NULL AFTER Descripcion,
  ADD COLUMN IF NOT EXISTS PoweredByEnabled tinyint(1) NOT NULL DEFAULT 1 AFTER PieDocumentos;
