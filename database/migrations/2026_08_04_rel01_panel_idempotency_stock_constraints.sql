-- REL-01: integridad transaccional, stock, concurrencia e idempotencia panel.
-- Prechecks: abortan la migracion si existen datos incompatibles.

DELIMITER //

CREATE PROCEDURE lteco_rel01_precheck()
BEGIN
    IF (SELECT COUNT(*) FROM producto WHERE Stock < 0) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: producto.Stock negativo';
    END IF;

    IF (SELECT COUNT(*) FROM venta_detalle WHERE Cantidad <= 0) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: venta_detalle.Cantidad no positiva';
    END IF;

    IF (SELECT COUNT(*) FROM distribuidor_stock WHERE Cantidad < 0) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: distribuidor_stock.Cantidad negativa';
    END IF;

    IF (SELECT COUNT(*) FROM distribuidor_pedido WHERE Cantidad <= 0) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: distribuidor_pedido.Cantidad no positiva';
    END IF;

    IF (SELECT COUNT(*) FROM ecommerce_pedido_item WHERE Cantidad <= 0) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: ecommerce_pedido_item.Cantidad no positiva';
    END IF;

    IF (
        SELECT COUNT(*)
        FROM distribuidor_stock
        WHERE (TipoItem = 'Vehiculo' AND (IdVehiculo IS NULL OR IdRepuesto IS NOT NULL))
           OR (TipoItem = 'Repuesto' AND (IdRepuesto IS NULL OR IdVehiculo IS NOT NULL))
           OR (TipoItem NOT IN ('Vehiculo', 'Repuesto'))
    ) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: distribuidor_stock item shape invalido';
    END IF;

    IF (
        SELECT COUNT(*)
        FROM (
            SELECT IdDistribuidor, IdVehiculo
            FROM distribuidor_stock
            WHERE TipoItem = 'Vehiculo'
            GROUP BY IdDistribuidor, IdVehiculo
            HAVING COUNT(*) > 1
        ) d
    ) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: distribuidor_stock vehiculo duplicado';
    END IF;

    IF (
        SELECT COUNT(*)
        FROM (
            SELECT IdDistribuidor, IdRepuesto
            FROM distribuidor_stock
            WHERE TipoItem = 'Repuesto'
            GROUP BY IdDistribuidor, IdRepuesto
            HAVING COUNT(*) > 1
        ) d
    ) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REL01 precheck failed: distribuidor_stock repuesto duplicado';
    END IF;
END//

DELIMITER ;

CALL lteco_rel01_precheck();
DROP PROCEDURE lteco_rel01_precheck;

CREATE TABLE IF NOT EXISTS panel_idempotency_key (
    IdKey BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    OperationKey CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    OperationType VARCHAR(80) NOT NULL,
    RequestHash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    Status ENUM('processing','completed') NOT NULL DEFAULT 'processing',
    IdUsuario INT NULL,
    ResultType VARCHAR(40) NULL,
    ResultId VARCHAR(80) NULL,
    RedirectUrl VARCHAR(255) NULL,
    ExpiraEn DATETIME NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CompletedAt DATETIME NULL,
    PRIMARY KEY (IdKey),
    UNIQUE KEY uq_panel_idempotency_operation_key (OperationKey),
    KEY idx_panel_idempotency_expira (ExpiraEn),
    KEY idx_panel_idempotency_type_result (OperationType, ResultType, ResultId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_rel01_producto_stock_nonnegative') = 0,
    'ALTER TABLE producto ADD CONSTRAINT chk_rel01_producto_stock_nonnegative CHECK (Stock >= 0)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_rel01_venta_detalle_cantidad_positive') = 0,
    'ALTER TABLE venta_detalle ADD CONSTRAINT chk_rel01_venta_detalle_cantidad_positive CHECK (Cantidad > 0)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_rel01_distribuidor_stock_cantidad_nonnegative') = 0,
    'ALTER TABLE distribuidor_stock ADD CONSTRAINT chk_rel01_distribuidor_stock_cantidad_nonnegative CHECK (Cantidad >= 0)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_rel01_distribuidor_stock_item_shape') = 0,
    "ALTER TABLE distribuidor_stock ADD CONSTRAINT chk_rel01_distribuidor_stock_item_shape CHECK ((TipoItem = 'Vehiculo' AND IdVehiculo IS NOT NULL AND IdRepuesto IS NULL) OR (TipoItem = 'Repuesto' AND IdRepuesto IS NOT NULL AND IdVehiculo IS NULL))",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_rel01_distribuidor_pedido_cantidad_positive') = 0,
    'ALTER TABLE distribuidor_pedido ADD CONSTRAINT chk_rel01_distribuidor_pedido_cantidad_positive CHECK (Cantidad > 0)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_rel01_ecommerce_pedido_item_cantidad_positive') = 0,
    'ALTER TABLE ecommerce_pedido_item ADD CONSTRAINT chk_rel01_ecommerce_pedido_item_cantidad_positive CHECK (Cantidad > 0)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE UNIQUE INDEX IF NOT EXISTS uq_rel01_distribuidor_stock_vehiculo
    ON distribuidor_stock (IdDistribuidor, TipoItem, IdVehiculo);

CREATE UNIQUE INDEX IF NOT EXISTS uq_rel01_distribuidor_stock_repuesto
    ON distribuidor_stock (IdDistribuidor, TipoItem, IdRepuesto);
