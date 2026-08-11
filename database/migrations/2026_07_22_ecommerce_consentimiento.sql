ALTER TABLE ecommerce_pedido
  ADD COLUMN AceptoTerminosEn DATETIME NULL AFTER Notas,
  ADD COLUMN VersionTerminos VARCHAR(20) NULL AFTER AceptoTerminosEn;
