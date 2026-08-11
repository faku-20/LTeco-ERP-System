-- Permite que un pedido reserve varias unidades físicas.
--
-- Orden obligatorio:
-- 1. Crear un índice normal sobre IdPedido.
-- 2. Eliminar el índice UNIQUE anterior.
--
-- Crear primero el índice normal evita romper la foreign key.
-- La exclusividad de cada unidad se mantiene mediante:
-- PRIMARY KEY (IdVehiculo)
--
-- La migración es idempotente.

SET @lteco_schema := DATABASE();

SET @lteco_table_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = @lteco_schema
      AND table_name = 'ecommerce_ocupacion_unidad'
);

SET @lteco_normal_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @lteco_schema
      AND table_name = 'ecommerce_ocupacion_unidad'
      AND index_name = 'idx_ecommerce_ocupacion_pedido'
);

SET @lteco_sql := IF(
    @lteco_table_exists = 1
    AND @lteco_normal_index_exists = 0,
    'ALTER TABLE ecommerce_ocupacion_unidad
        ADD INDEX idx_ecommerce_ocupacion_pedido (IdPedido)',
    'SELECT 1'
);

PREPARE lteco_stmt FROM @lteco_sql;
EXECUTE lteco_stmt;
DEALLOCATE PREPARE lteco_stmt;

SET @lteco_unique_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @lteco_schema
      AND table_name = 'ecommerce_ocupacion_unidad'
      AND index_name = 'uq_ecommerce_ocupacion_pedido'
);

SET @lteco_sql := IF(
    @lteco_table_exists = 1
    AND @lteco_unique_index_exists > 0,
    'ALTER TABLE ecommerce_ocupacion_unidad
        DROP INDEX uq_ecommerce_ocupacion_pedido',
    'SELECT 1'
);

PREPARE lteco_stmt FROM @lteco_sql;
EXECUTE lteco_stmt;
DEALLOCATE PREPARE lteco_stmt;
