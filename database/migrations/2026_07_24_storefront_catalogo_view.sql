-- database/migrations/2026_07_24_storefront_catalogo_view.sql
-- Vista de solo lectura para el storefront Laravel (App\Models\CatalogoMoto,
-- conexión 'catalog', tabla storefront_catalogo_motos). No crea datos nuevos,
-- solo reexpone vehiculo+producto con los nombres que espera ese modelo.
-- Reutiliza la misma regla de "publicable" que ya usa public-web/catalogo.php
-- (vehiculoChecklistPublicacion en shared/vehiculo_logic.php).

CREATE OR REPLACE VIEW storefront_catalogo_motos AS
SELECT
    p.IdProducto                              AS id_producto,
    COALESCE(p.Nombre, v.Modelo)               AS nombre,
    v.Modelo                                   AS modelo,
    p.Slug                                     AS slug,
    p.PrecioVenta                              AS precio,
    p.Moneda                                   AS moneda,
    p.Stock                                    AS stock,
    COALESCE(p.DestacadoWeb, 0)                AS destacado,
    COALESCE(p.OrdenWeb, 0)                    AS orden,
    v.CapacidadBateriaAh                       AS capacidad_bateria_ah,
    v.Color                                    AS color,
    COALESCE(p.DescripcionWeb, p.Descripcion)  AS descripcion,
    (p.MostrarEnWeb = 1 AND p.Estado = 'Disponible' AND p.Stock > 0) AS disponible
FROM producto p
INNER JOIN vehiculo v ON v.IdProducto = p.IdProducto
WHERE p.TipoProducto = 'Moto'
  AND p.Slug IS NOT NULL AND p.Slug <> ''
  AND p.DescripcionWeb IS NOT NULL AND p.DescripcionWeb <> ''
  AND p.PrecioVenta > 0;

-- Paso manual pendiente (no versionado, ejecutar aparte contra el servidor):
--   GRANT SELECT ON lteco_db_poo.storefront_catalogo_motos TO 'storefront_reader'@'%';
--   FLUSH PRIVILEGES;
