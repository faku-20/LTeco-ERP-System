-- Ltecobike V3 - Remitos de distribuidores + vínculo de facturación
-- Idempotente para MariaDB.

CREATE TABLE IF NOT EXISTS `remito` (
  `IdRemito` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `IdDistribuidor` int(11) NOT NULL,
  `IdPedido` int(10) unsigned NOT NULL,
  `TipoItem` enum('Vehiculo','Repuesto') NOT NULL,
  `IdVehiculo` varchar(30) DEFAULT NULL,
  `IdRepuesto` int(11) DEFAULT NULL,
  `Cantidad` int(11) NOT NULL,
  `Estado` enum('Pendiente','Facturado','Anulado') NOT NULL DEFAULT 'Pendiente',
  `FechaEmision` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaFactura` datetime DEFAULT NULL,
  `IdVenta` int(11) DEFAULT NULL,
  `NumeroFactura` varchar(80) DEFAULT NULL,
  `ReferenciaFactura` varchar(160) DEFAULT NULL,
  PRIMARY KEY (`IdRemito`),
  KEY `idx_remito_distribuidor` (`IdDistribuidor`),
  KEY `idx_remito_pedido` (`IdPedido`),
  KEY `idx_remito_venta` (`IdVenta`),
  KEY `idx_remito_estado_fecha` (`Estado`, `FechaEmision`),
  KEY `idx_remito_numero_factura` (`NumeroFactura`),
  CONSTRAINT `fk_remito_distribuidor`
    FOREIGN KEY (`IdDistribuidor`)
    REFERENCES `distribuidor` (`IdDistribuidor`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `remito`
  ADD COLUMN IF NOT EXISTS `NumeroFactura` varchar(80) DEFAULT NULL AFTER `IdVenta`;

ALTER TABLE `remito`
  ADD COLUMN IF NOT EXISTS `ReferenciaFactura` varchar(160) DEFAULT NULL AFTER `NumeroFactura`;

CREATE INDEX IF NOT EXISTS `idx_remito_pedido` ON `remito` (`IdPedido`);
CREATE INDEX IF NOT EXISTS `idx_remito_venta` ON `remito` (`IdVenta`);
CREATE INDEX IF NOT EXISTS `idx_remito_estado_fecha` ON `remito` (`Estado`, `FechaEmision`);
CREATE INDEX IF NOT EXISTS `idx_remito_numero_factura` ON `remito` (`NumeroFactura`);
