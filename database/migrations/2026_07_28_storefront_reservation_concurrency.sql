-- Persistencia de la identidad de variante para bloquear solamente las unidades
-- pedidas durante checkout. La expresión replica VariantIdentity::id().

ALTER TABLE vehiculo
    ADD COLUMN IF NOT EXISTS StorefrontVariantId CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER SeniaReserva;

CREATE INDEX IF NOT EXISTS idx_vehiculo_storefront_variant
    ON vehiculo (StorefrontVariantId, IdVehiculo);

UPDATE vehiculo v
INNER JOIN producto p ON p.IdProducto = v.IdProducto
SET v.StorefrontVariantId = CASE
    WHEN p.TipoProducto = 'Moto' THEN SHA2(JSON_COMPACT(JSON_ARRAY(
        LOWER(REGEXP_REPLACE(TRIM(v.Modelo), '[[:space:]]+', ' ')),
        v.CapacidadBateriaAh,
        LOWER(REGEXP_REPLACE(TRIM(COALESCE(v.Color, '')), '[[:space:]]+', ' ')),
        UPPER(TRIM(p.Moneda)),
        CAST(CAST(ROUND(p.PrecioVenta, 2) AS DECIMAL(15,2)) AS CHAR)
    )), 256)
    ELSE NULL
END;

DROP TRIGGER IF EXISTS trg_vehiculo_storefront_variant_bi;
DROP TRIGGER IF EXISTS trg_vehiculo_storefront_variant_bu;
DROP TRIGGER IF EXISTS trg_producto_storefront_variant_au;

DELIMITER //

CREATE TRIGGER trg_vehiculo_storefront_variant_bi
BEFORE INSERT ON vehiculo
FOR EACH ROW
BEGIN
    DECLARE product_type VARCHAR(20);
    DECLARE product_currency VARCHAR(3);
    DECLARE product_price DECIMAL(15,2);

    SELECT TipoProducto, Moneda, PrecioVenta
      INTO product_type, product_currency, product_price
      FROM producto
     WHERE IdProducto = NEW.IdProducto;

    IF product_type = 'Moto' THEN
        SET NEW.StorefrontVariantId = SHA2(JSON_COMPACT(JSON_ARRAY(
            LOWER(REGEXP_REPLACE(TRIM(NEW.Modelo), '[[:space:]]+', ' ')),
            NEW.CapacidadBateriaAh,
            LOWER(REGEXP_REPLACE(TRIM(COALESCE(NEW.Color, '')), '[[:space:]]+', ' ')),
            UPPER(TRIM(product_currency)),
            CAST(CAST(ROUND(product_price, 2) AS DECIMAL(15,2)) AS CHAR)
        )), 256);
    ELSE
        SET NEW.StorefrontVariantId = NULL;
    END IF;
END//

CREATE TRIGGER trg_vehiculo_storefront_variant_bu
BEFORE UPDATE ON vehiculo
FOR EACH ROW
BEGIN
    DECLARE product_type VARCHAR(20);
    DECLARE product_currency VARCHAR(3);
    DECLARE product_price DECIMAL(15,2);

    SELECT TipoProducto, Moneda, PrecioVenta
      INTO product_type, product_currency, product_price
      FROM producto
     WHERE IdProducto = NEW.IdProducto;

    IF product_type = 'Moto' THEN
        SET NEW.StorefrontVariantId = SHA2(JSON_COMPACT(JSON_ARRAY(
            LOWER(REGEXP_REPLACE(TRIM(NEW.Modelo), '[[:space:]]+', ' ')),
            NEW.CapacidadBateriaAh,
            LOWER(REGEXP_REPLACE(TRIM(COALESCE(NEW.Color, '')), '[[:space:]]+', ' ')),
            UPPER(TRIM(product_currency)),
            CAST(CAST(ROUND(product_price, 2) AS DECIMAL(15,2)) AS CHAR)
        )), 256);
    ELSE
        SET NEW.StorefrontVariantId = NULL;
    END IF;
END//

CREATE TRIGGER trg_producto_storefront_variant_au
AFTER UPDATE ON producto
FOR EACH ROW
BEGIN
    IF NOT (NEW.TipoProducto <=> OLD.TipoProducto)
       OR NOT (NEW.Moneda <=> OLD.Moneda)
       OR NOT (NEW.PrecioVenta <=> OLD.PrecioVenta) THEN
        UPDATE vehiculo
           SET StorefrontVariantId = CASE
               WHEN NEW.TipoProducto = 'Moto' THEN SHA2(JSON_COMPACT(JSON_ARRAY(
                   LOWER(REGEXP_REPLACE(TRIM(Modelo), '[[:space:]]+', ' ')),
                   CapacidadBateriaAh,
                   LOWER(REGEXP_REPLACE(TRIM(COALESCE(Color, '')), '[[:space:]]+', ' ')),
                   UPPER(TRIM(NEW.Moneda)),
                   CAST(CAST(ROUND(NEW.PrecioVenta, 2) AS DECIMAL(15,2)) AS CHAR)
               )), 256)
               ELSE NULL
           END
         WHERE IdProducto = NEW.IdProducto;
    END IF;
END//

DELIMITER ;
