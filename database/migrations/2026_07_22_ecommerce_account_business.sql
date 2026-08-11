ALTER TABLE ecommerce_cuenta
  ADD COLUMN TipoCliente ENUM('ConsumidorFinal','Empresa') NOT NULL DEFAULT 'ConsumidorFinal' AFTER IdCliente,
  ADD COLUMN Rut VARCHAR(40) NULL AFTER Cedula,
  ADD COLUMN Direccion VARCHAR(255) NULL AFTER Rut;
