-- Endurecimiento del ecommerce integrado. Ejecutar después de 2026_07_22_ecommerce.sql.

ALTER TABLE ecommerce_cuenta
  MODIFY Estado ENUM('Pendiente','Activa','Bloqueada') NOT NULL DEFAULT 'Pendiente',
  ADD COLUMN IntentosFallidos SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER UltimoAccesoEn,
  ADD COLUMN BloqueadaHasta DATETIME NULL AFTER IntentosFallidos,
  ADD COLUMN ClaveCambiadaEn DATETIME NULL AFTER BloqueadaHasta;

ALTER TABLE ecommerce_pedido
  MODIFY Estado ENUM(
    'PendientePago','PagoEnRevision','Pagado','Preparando','Listo','Entregado',
    'ExcepcionPagoSinStock','ReembolsoPendiente','Reembolsado','Cancelado','Vencido'
  ) NOT NULL DEFAULT 'PendientePago',
  MODIFY EstadoPago ENUM(
    'Pendiente','EnProceso','Aprobado','Rechazado','ReembolsoPendiente',
    'Reembolsado','ReembolsoFallido','Contracargo','Cancelado'
  ) NOT NULL DEFAULT 'Pendiente',
  ADD COLUMN EntregadoEn DATETIME NULL AFTER PagadoEn,
  ADD COLUMN EntregadoPor INT NULL AFTER EntregadoEn,
  ADD COLUMN ReceptorEntrega VARCHAR(150) NULL AFTER EntregadoPor,
  ADD COLUMN EvidenciaEntrega VARCHAR(255) NULL AFTER ReceptorEntrega,
  ADD COLUMN VersionBloqueo INT UNSIGNED NOT NULL DEFAULT 0 AFTER EvidenciaEntrega,
  ADD CONSTRAINT fk_ecommerce_pedido_entregado_por FOREIGN KEY (EntregadoPor) REFERENCES usuario (IdUsuario) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE ecommerce_pago
  ADD COLUMN IdEventoExterno VARCHAR(190) NULL AFTER IdExterno,
  ADD COLUMN Tipo VARCHAR(30) NOT NULL DEFAULT 'Cobro' AFTER Proveedor,
  ADD UNIQUE KEY uq_ecommerce_pago_evento (Proveedor, IdEventoExterno);

CREATE TABLE IF NOT EXISTS ecommerce_ocupacion_unidad (
  IdVehiculo VARCHAR(10) NOT NULL,
  IdPedido BIGINT UNSIGNED NOT NULL,
  Estado ENUM('Reservada','Consumida') NOT NULL DEFAULT 'Reservada',
  ExpiraEn DATETIME NULL,
  CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (IdVehiculo),
  KEY idx_ecommerce_ocupacion_pedido (IdPedido),
  CONSTRAINT fk_ecommerce_ocupacion_vehiculo FOREIGN KEY (IdVehiculo) REFERENCES vehiculo (IdVehiculo) ON UPDATE CASCADE,
  CONSTRAINT fk_ecommerce_ocupacion_pedido FOREIGN KEY (IdPedido) REFERENCES ecommerce_pedido (IdPedido) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_evento (
  IdEvento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  IdPedido BIGINT UNSIGNED NULL,
  Tipo VARCHAR(80) NOT NULL,
  ClaveIdempotencia VARCHAR(190) NOT NULL,
  PayloadJson JSON NULL,
  Estado ENUM('Pendiente','Procesando','Completado','Fallido') NOT NULL DEFAULT 'Pendiente',
  Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ProximoIntentoEn DATETIME NULL,
  ProcesadoEn DATETIME NULL,
  UltimoError VARCHAR(500) NULL,
  CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (IdEvento),
  UNIQUE KEY uq_ecommerce_evento_clave (ClaveIdempotencia),
  KEY idx_ecommerce_evento_cola (Estado, ProximoIntentoEn, IdEvento),
  CONSTRAINT fk_ecommerce_evento_pedido FOREIGN KEY (IdPedido) REFERENCES ecommerce_pedido (IdPedido) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_auditoria (
  IdAuditoria BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  IdPedido BIGINT UNSIGNED NULL,
  IdCuenta BIGINT UNSIGNED NULL,
  IdUsuario INT NULL,
  Accion VARCHAR(80) NOT NULL,
  EstadoAnterior VARCHAR(40) NULL,
  EstadoNuevo VARCHAR(40) NULL,
  MetadataJson JSON NULL,
  CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (IdAuditoria),
  KEY idx_ecommerce_auditoria_pedido (IdPedido, CreadoEn),
  CONSTRAINT fk_ecommerce_auditoria_pedido FOREIGN KEY (IdPedido) REFERENCES ecommerce_pedido (IdPedido) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ecommerce_auditoria_cuenta FOREIGN KEY (IdCuenta) REFERENCES ecommerce_cuenta (IdCuenta) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ecommerce_auditoria_usuario FOREIGN KEY (IdUsuario) REFERENCES usuario (IdUsuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migra reservas web existentes a la ocupación exclusiva.
INSERT IGNORE INTO ecommerce_ocupacion_unidad (IdVehiculo, IdPedido, Estado, ExpiraEn)
SELECT i.IdVehiculo, p.IdPedido,
       IF(p.Estado IN ('Pagado','Preparando','Listo','Entregado'), 'Consumida', 'Reservada'),
       IF(p.Estado = 'PendientePago', p.ExpiraEn, NULL)
FROM ecommerce_pedido p
JOIN ecommerce_pedido_item i ON i.IdPedido = p.IdPedido
WHERE p.Estado NOT IN ('Cancelado','Vencido','Reembolsado');
