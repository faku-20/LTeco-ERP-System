ALTER TABLE vehiculo
  ADD COLUMN CapacidadBateriaAh SMALLINT UNSIGNED NULL AFTER Modelo,
  ADD KEY idx_vehiculo_modelo_bateria (Modelo, CapacidadBateriaAh);

ALTER TABLE ecommerce_pedido_item
  ADD COLUMN CapacidadBateriaAh SMALLINT UNSIGNED NULL AFTER Modelo;

UPDATE vehiculo v
INNER JOIN producto p ON p.IdProducto = v.IdProducto
SET v.CapacidadBateriaAh = CASE
  WHEN v.Modelo = 'Q8-500W' AND p.PrecioVenta = 63000 THEN 12
  WHEN v.Modelo = 'Q8-500W' AND p.PrecioVenta = 67000 THEN 20
  WHEN v.Modelo = 'SL-500W' THEN 20
  ELSE v.CapacidadBateriaAh
END
WHERE v.Modelo IN ('Q8-500W', 'SL-500W');

UPDATE producto p
INNER JOIN vehiculo v ON v.IdProducto = p.IdProducto
SET p.Nombre = CONCAT(v.Modelo, '-', v.CapacidadBateriaAh, 'Ah', IF(COALESCE(v.Color, '') <> '', CONCAT('-', v.Color), '')),
    p.Descripcion = CONCAT('Moto eléctrica ', v.Modelo, ' con motor de 500W y batería de ', v.CapacidadBateriaAh, 'Ah.'),
    p.DescripcionWeb = CONCAT('Moto eléctrica ', v.Modelo, ' con motor de 500W y batería de ', v.CapacidadBateriaAh, 'Ah. Consultanos por colores y formas de pago.')
WHERE v.Modelo IN ('Q8-500W', 'SL-500W');
