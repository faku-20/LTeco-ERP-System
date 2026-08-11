ALTER TABLE ecommerce_pedido
  ADD COLUMN CanceladoEn DATETIME NULL AFTER EntregadoEn,
  ADD COLUMN MotivoCancelacion VARCHAR(500) NULL AFTER CanceladoEn;

ALTER TABLE ecommerce_evento
  ADD COLUMN IdCuenta BIGINT UNSIGNED NULL AFTER IdPedido,
  ADD CONSTRAINT fk_ecommerce_evento_cuenta FOREIGN KEY (IdCuenta) REFERENCES ecommerce_cuenta (IdCuenta) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS ecommerce_solicitud_privacidad (
  IdSolicitud BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  IdCuenta BIGINT UNSIGNED NOT NULL,
  Tipo ENUM('Acceso','Correccion','Supresion') NOT NULL,
  Detalle VARCHAR(1000) NULL,
  Estado ENUM('Pendiente','EnProceso','Resuelta','Rechazada') NOT NULL DEFAULT 'Pendiente',
  Respuesta VARCHAR(1000) NULL,
  ResueltaPor INT NULL,
  CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ResueltoEn DATETIME NULL,
  PRIMARY KEY (IdSolicitud),
  KEY idx_ecommerce_privacidad_estado (Estado,CreadoEn),
  CONSTRAINT fk_ecommerce_privacidad_cuenta FOREIGN KEY (IdCuenta) REFERENCES ecommerce_cuenta (IdCuenta) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ecommerce_privacidad_usuario FOREIGN KEY (ResueltaPor) REFERENCES usuario (IdUsuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_notificacion (
  IdNotificacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  IdCuenta BIGINT UNSIGNED NULL,
  IdPedido BIGINT UNSIGNED NULL,
  IdService INT NULL,
  Tipo VARCHAR(80) NOT NULL,
  Destinatario VARCHAR(190) NOT NULL,
  Estado ENUM('Pendiente','Enviada','Fallida') NOT NULL DEFAULT 'Pendiente',
  Intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UltimoError VARCHAR(500) NULL,
  EnviadaEn DATETIME NULL,
  CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (IdNotificacion),
  UNIQUE KEY uq_ecommerce_notificacion_pedido_tipo (IdPedido,Tipo),
  UNIQUE KEY uq_ecommerce_notificacion_service_tipo (IdService,Tipo),
  KEY idx_ecommerce_notificacion_cola (Estado,CreadoEn),
  CONSTRAINT fk_ecommerce_notificacion_cuenta FOREIGN KEY (IdCuenta) REFERENCES ecommerce_cuenta (IdCuenta) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ecommerce_notificacion_pedido FOREIGN KEY (IdPedido) REFERENCES ecommerce_pedido (IdPedido) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ecommerce_notificacion_service FOREIGN KEY (IdService) REFERENCES service_vehiculo (IdService) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
