/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_conversation_example` (
  `IdExample` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdInboxPregunta` bigint(20) unsigned NOT NULL,
  `IdInboxRespuesta` bigint(20) unsigned NOT NULL,
  `Canal` varchar(32) NOT NULL DEFAULT 'whatsapp',
  `Pregunta` text NOT NULL,
  `Respuesta` text NOT NULL,
  `FechaConversacion` datetime NOT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdExample`),
  UNIQUE KEY `uq_ai_example_respuesta` (`IdInboxRespuesta`),
  KEY `idx_ai_example_pregunta` (`IdInboxPregunta`),
  KEY `idx_ai_example_fecha` (`FechaConversacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_instruction_entry` (
  `IdInstruction` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Clave` varchar(80) NOT NULL,
  `Titulo` varchar(160) NOT NULL,
  `Cuerpo` text DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `ActualizadoPor` int(11) DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdInstruction`),
  UNIQUE KEY `Clave` (`Clave`),
  KEY `idx_ai_instruction_activo` (`Activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage_log` (
  `IdUsage` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdUsuario` int(11) DEFAULT NULL,
  `Ruta` varchar(120) NOT NULL,
  `Accion` varchar(120) DEFAULT NULL,
  `Provider` varchar(80) DEFAULT NULL,
  `PromptChars` int(10) unsigned NOT NULL DEFAULT 0,
  `Estado` varchar(40) NOT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdUsage`),
  KEY `idx_ai_usage_usuario_ruta_fecha` (`IdUsuario`,`Ruta`,`FechaAlta`),
  KEY `idx_ai_usage_estado` (`Estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `IdAuditoria` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdUsuario` int(11) DEFAULT NULL,
  `Usuario` varchar(100) DEFAULT NULL,
  `Rol` varchar(50) DEFAULT NULL,
  `Accion` varchar(80) NOT NULL,
  `Modulo` varchar(80) NOT NULL,
  `Detalle` text DEFAULT NULL,
  `ExtraJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ExtraJson`)),
  `Ip` varchar(45) DEFAULT NULL,
  `UserAgent` varchar(250) DEFAULT NULL,
  `FechaHora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdAuditoria`),
  KEY `idx_auditoria_fecha` (`FechaHora`),
  KEY `idx_auditoria_modulo` (`Modulo`),
  KEY `idx_auditoria_accion` (`Accion`),
  KEY `idx_auditoria_usuario` (`IdUsuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_event` (
  `IdEvent` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `EventKey` varchar(80) NOT NULL,
  `IdempotencyKey` varchar(160) DEFAULT NULL,
  `Status` varchar(32) NOT NULL DEFAULT 'pending',
  `SourceType` varchar(80) DEFAULT NULL,
  `SourceId` bigint(20) DEFAULT NULL,
  `Payload` longtext DEFAULT NULL,
  `ErrorMessage` text DEFAULT NULL,
  `Intentos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `FechaUltimoIntento` datetime DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaProcesado` datetime DEFAULT NULL,
  PRIMARY KEY (`IdEvent`),
  UNIQUE KEY `uq_automation_event_idempotency` (`IdempotencyKey`),
  KEY `idx_automation_status_fecha` (`Status`,`FechaAlta`),
  KEY `idx_automation_event_key` (`EventKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente` (
  `IdCliente` int(11) NOT NULL AUTO_INCREMENT,
  `NombreApellido` varchar(100) NOT NULL,
  `TipoFiscal` varchar(30) NOT NULL DEFAULT 'Consumidor final',
  `Telefono` varchar(20) DEFAULT NULL,
  `Correo` varchar(100) DEFAULT NULL,
  `Cedula` varchar(40) DEFAULT NULL,
  `Direccion` varchar(255) DEFAULT NULL,
  `RUT` varchar(40) DEFAULT NULL,
  `IdVehiculo` varchar(10) DEFAULT NULL,
  `NumeroMotor` varchar(50) DEFAULT NULL,
  `RepuestosVendidos` varchar(500) DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdCliente`),
  UNIQUE KEY `uq_cliente_cedula` (`Cedula`),
  KEY `idx_cliente_idvehiculo` (`IdVehiculo`),
  CONSTRAINT `fk_cliente_vehiculo` FOREIGN KEY (`IdVehiculo`) REFERENCES `vehiculo` (`IdVehiculo`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commercial_inbox_message` (
  `IdInbox` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdLead` bigint(20) unsigned DEFAULT NULL,
  `IdCliente` int(11) DEFAULT NULL,
  `IdVehiculo` varchar(10) DEFAULT NULL,
  `Canal` varchar(32) NOT NULL DEFAULT 'whatsapp',
  `Direccion` varchar(16) NOT NULL DEFAULT 'inbound',
  `Estado` varchar(32) NOT NULL DEFAULT 'nuevo',
  `ExternalId` varchar(160) DEFAULT NULL,
  `CuentaOrigen` varchar(120) DEFAULT NULL,
  `RemitenteNombre` varchar(160) DEFAULT NULL,
  `RemitenteHandle` varchar(160) DEFAULT NULL,
  `Telefono` varchar(40) DEFAULT NULL,
  `Email` varchar(160) DEFAULT NULL,
  `Mensaje` text NOT NULL,
  `ReplyToWaMessageId` varchar(255) DEFAULT NULL,
  `ReplyToModelo` varchar(64) DEFAULT NULL,
  `AiIntent` varchar(64) DEFAULT NULL,
  `AiPrioridad` varchar(32) DEFAULT NULL,
  `AiResumen` text DEFAULT NULL,
  `AiRespuestaSugerida` text DEFAULT NULL,
  `AiError` text DEFAULT NULL,
  `FechaClasificacion` datetime DEFAULT NULL,
  `RawPayload` longtext DEFAULT NULL,
  `FechaRecibido` datetime DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdInbox`),
  UNIQUE KEY `uq_inbox_channel_external` (`Canal`,`ExternalId`),
  KEY `idx_inbox_canal_estado_fecha` (`Canal`,`Estado`,`FechaRecibido`),
  KEY `idx_inbox_direccion` (`Direccion`),
  KEY `idx_inbox_telefono` (`Telefono`),
  KEY `idx_inbox_lead` (`IdLead`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commercial_lead` (
  `IdLead` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdCliente` int(11) DEFAULT NULL,
  `IdVehiculo` varchar(10) DEFAULT NULL,
  `IdVenta` int(11) DEFAULT NULL,
  `Origen` varchar(64) NOT NULL DEFAULT 'whatsapp',
  `Estado` varchar(32) NOT NULL DEFAULT 'nuevo',
  `Prioridad` varchar(24) NOT NULL DEFAULT 'media',
  `Nombre` varchar(160) NOT NULL,
  `Telefono` varchar(40) DEFAULT NULL,
  `Email` varchar(160) DEFAULT NULL,
  `Mensaje` text DEFAULT NULL,
  `ResumenInteres` text DEFAULT NULL,
  `ResponsableUsuarioId` int(11) DEFAULT NULL,
  `ProximoContacto` datetime DEFAULT NULL,
  `FechaCierre` datetime DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdLead`),
  KEY `idx_lead_estado_fecha` (`Estado`,`ProximoContacto`),
  KEY `idx_lead_origen_fecha` (`Origen`,`FechaAlta`),
  KEY `idx_lead_telefono` (`Telefono`),
  KEY `idx_lead_responsable` (`ResponsableUsuarioId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion` (
  `IdConfiguracion` int(11) NOT NULL AUTO_INCREMENT,
  `NombreEmpresa` varchar(100) NOT NULL DEFAULT 'Lteco',
  `Correo` varchar(100) DEFAULT NULL,
  `Telefono` varchar(20) DEFAULT NULL,
  `WhatsApp` varchar(20) DEFAULT NULL,
  `Instagram` varchar(100) DEFAULT NULL,
  `Logo` varchar(255) DEFAULT NULL,
  `TextoComprobante` text DEFAULT NULL,
  `MonedaPrincipal` varchar(3) NOT NULL DEFAULT 'UYU',
  `DescuentoContado` decimal(5,2) NOT NULL DEFAULT 5.00,
  `RecargoTarjeta` decimal(5,2) NOT NULL DEFAULT 5.00,
  `ComisionDistribuidor` decimal(5,2) NOT NULL DEFAULT 6.67,
  `TasaIVA` decimal(5,2) NOT NULL DEFAULT 22.00,
  `ComisionVendedor` decimal(5,2) NOT NULL DEFAULT 0.00,
  `UsuarioComisionDistribuidorId` int(11) DEFAULT NULL,
  `WaEnabled` tinyint(1) DEFAULT NULL,
  `WaPhoneId` varchar(80) DEFAULT NULL,
  `WaToken` text DEFAULT NULL,
  `WaTplVenta` varchar(100) DEFAULT NULL,
  `WaTplService` varchar(100) DEFAULT NULL,
  `StorefrontUpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdConfiguracion`),
  KEY `idx_configuracion_usuario_comision_distribuidor` (`UsuarioComisionDistribuidorId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_negocio` (
  `IdConfig` int(11) NOT NULL AUTO_INCREMENT,
  `NombreEmpresa` varchar(150) NOT NULL DEFAULT 'Ltecobike',
  `Whatsapp` varchar(30) DEFAULT NULL,
  `Direccion` varchar(255) DEFAULT NULL,
  `Logo` varchar(255) DEFAULT NULL,
  `TipoCambioUSD` decimal(10,2) NOT NULL DEFAULT 40.00,
  `TextoComprobante` text DEFAULT NULL,
  `FechaActualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdConfig`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_visita` (
  `IdVisita` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `StorefrontRequestUuid` char(36) DEFAULT NULL,
  `IdLead` bigint(20) unsigned DEFAULT NULL,
  `IdInboxOrigen` bigint(20) unsigned DEFAULT NULL,
  `IdCliente` int(11) DEFAULT NULL,
  `IdVehiculo` varchar(10) DEFAULT NULL,
  `ResponsableUsuarioId` int(11) DEFAULT NULL,
  `ClienteNombre` varchar(160) NOT NULL,
  `ClienteTelefono` varchar(40) DEFAULT NULL,
  `ClienteCorreo` varchar(190) DEFAULT NULL,
  `VehiculoTexto` varchar(160) DEFAULT NULL,
  `ResponsableNombre` varchar(160) DEFAULT NULL,
  `Canal` varchar(40) NOT NULL DEFAULT 'WhatsApp',
  `FechaVisita` datetime NOT NULL,
  `HoraConfirmada` tinyint(1) NOT NULL DEFAULT 1,
  `Estado` enum('agendada','reprogramada','asistio','no_asistio','cancelada','cerrada') NOT NULL DEFAULT 'agendada',
  `Observaciones` text DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdVisita`),
  UNIQUE KEY `uq_crm_visita_storefront_request` (`StorefrontRequestUuid`),
  KEY `idx_visita_fecha_estado` (`FechaVisita`,`Estado`),
  KEY `idx_visita_telefono` (`ClienteTelefono`),
  KEY `idx_visita_responsable` (`ResponsableUsuarioId`),
  KEY `idx_visita_lead` (`IdLead`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribuidor` (
  `IdDistribuidor` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) NOT NULL,
  `Contacto` varchar(150) DEFAULT NULL,
  `Telefono` varchar(30) DEFAULT NULL,
  `Correo` varchar(150) DEFAULT NULL,
  `ComisionPct` decimal(5,2) NOT NULL DEFAULT 6.67,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `Observaciones` text DEFAULT NULL,
  PRIMARY KEY (`IdDistribuidor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribuidor_comision` (
  `IdComision` int(11) NOT NULL AUTO_INCREMENT,
  `IdDistribuidor` int(11) NOT NULL,
  `IdVenta` int(11) NOT NULL,
  `BaseComision` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Porcentaje` decimal(6,2) NOT NULL DEFAULT 0.00,
  `Monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Estado` enum('Pendiente','Aprobada','Pagada','Anulada') NOT NULL DEFAULT 'Pendiente',
  `FechaGenerada` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaPago` datetime DEFAULT NULL,
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdComision`),
  UNIQUE KEY `uq_distribuidor_comision_venta` (`IdVenta`),
  KEY `idx_distribuidor_comision_distribuidor` (`IdDistribuidor`,`Estado`,`FechaGenerada`),
  CONSTRAINT `fk_distribuidor_comision_distribuidor` FOREIGN KEY (`IdDistribuidor`) REFERENCES `distribuidor` (`IdDistribuidor`) ON UPDATE CASCADE,
  CONSTRAINT `fk_distribuidor_comision_venta` FOREIGN KEY (`IdVenta`) REFERENCES `venta` (`IdVenta`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribuidor_pedido` (
  `IdPedido` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `IdDistribuidor` int(11) NOT NULL,
  `TipoItem` enum('Vehiculo','Repuesto') NOT NULL DEFAULT 'Repuesto',
  `IdVehiculo` varchar(30) DEFAULT NULL,
  `IdRepuesto` int(11) DEFAULT NULL,
  `Cantidad` int(11) NOT NULL DEFAULT 1,
  `Estado` enum('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  `Observaciones` text DEFAULT NULL,
  `IdUsuarioSolicita` int(11) DEFAULT NULL,
  `FechaPedido` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaResolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`IdPedido`),
  KEY `idx_distribuidor_pedido_distribuidor` (`IdDistribuidor`),
  KEY `idx_distribuidor_pedido_estado` (`Estado`,`FechaPedido`),
  KEY `idx_distribuidor_pedido_vehiculo` (`IdVehiculo`),
  KEY `idx_distribuidor_pedido_repuesto` (`IdRepuesto`),
  CONSTRAINT `fk_distribuidor_pedido_distribuidor` FOREIGN KEY (`IdDistribuidor`) REFERENCES `distribuidor` (`IdDistribuidor`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_rel01_distribuidor_pedido_cantidad_positive` CHECK (`Cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribuidor_reporte_problema` (
  `IdReporte` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `IdDistribuidor` int(11) NOT NULL,
  `IdUsuario` int(11) NOT NULL,
  `Mensaje` text NOT NULL,
  `ImagenRuta` varchar(255) DEFAULT NULL,
  `EstadoInterno` enum('Nuevo','Revisado','Resuelto') NOT NULL DEFAULT 'Nuevo',
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `FechaResolucion` datetime DEFAULT NULL,
  `UsuarioResolucionId` int(11) DEFAULT NULL,
  PRIMARY KEY (`IdReporte`),
  KEY `idx_dist_estado` (`IdDistribuidor`,`EstadoInterno`),
  KEY `idx_estado_fecha` (`EstadoInterno`,`FechaCreacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `distribuidor_stock` (
  `IdStock` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `IdDistribuidor` int(11) NOT NULL,
  `TipoItem` enum('Vehiculo','Repuesto') NOT NULL DEFAULT 'Repuesto',
  `IdVehiculo` varchar(30) DEFAULT NULL,
  `IdRepuesto` int(11) DEFAULT NULL,
  `Cantidad` int(11) NOT NULL DEFAULT 0,
  `PrecioVenta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `PrecioMinimo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdStock`),
  UNIQUE KEY `uq_rel01_distribuidor_stock_vehiculo` (`IdDistribuidor`,`TipoItem`,`IdVehiculo`),
  UNIQUE KEY `uq_rel01_distribuidor_stock_repuesto` (`IdDistribuidor`,`TipoItem`,`IdRepuesto`),
  KEY `idx_distribuidor_stock_distribuidor` (`IdDistribuidor`),
  KEY `idx_distribuidor_stock_vehiculo` (`IdVehiculo`),
  KEY `idx_distribuidor_stock_repuesto` (`IdRepuesto`),
  CONSTRAINT `fk_distribuidor_stock_distribuidor` FOREIGN KEY (`IdDistribuidor`) REFERENCES `distribuidor` (`IdDistribuidor`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_rel01_distribuidor_stock_cantidad_nonnegative` CHECK (`Cantidad` >= 0),
  CONSTRAINT `chk_rel01_distribuidor_stock_item_shape` CHECK (`TipoItem` = 'Vehiculo' and `IdVehiculo` is not null and `IdRepuesto` is null or `TipoItem` = 'Repuesto' and `IdRepuesto` is not null and `IdVehiculo` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_auditoria` (
  `IdAuditoria` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdPedido` bigint(20) unsigned DEFAULT NULL,
  `IdCuenta` bigint(20) unsigned DEFAULT NULL,
  `IdUsuario` int(11) DEFAULT NULL,
  `Accion` varchar(80) NOT NULL,
  `EstadoAnterior` varchar(40) DEFAULT NULL,
  `EstadoNuevo` varchar(40) DEFAULT NULL,
  `MetadataJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`MetadataJson`)),
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdAuditoria`),
  KEY `idx_ecommerce_auditoria_pedido` (`IdPedido`,`CreadoEn`),
  KEY `fk_ecommerce_auditoria_cuenta` (`IdCuenta`),
  KEY `fk_ecommerce_auditoria_usuario` (`IdUsuario`),
  CONSTRAINT `fk_ecommerce_auditoria_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_auditoria_pedido` FOREIGN KEY (`IdPedido`) REFERENCES `ecommerce_pedido` (`IdPedido`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_auditoria_usuario` FOREIGN KEY (`IdUsuario`) REFERENCES `usuario` (`IdUsuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_cuenta` (
  `IdCuenta` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdCliente` int(11) DEFAULT NULL,
  `TipoCliente` enum('ConsumidorFinal','Empresa') NOT NULL DEFAULT 'ConsumidorFinal',
  `Correo` varchar(190) NOT NULL,
  `ClaveHash` varchar(255) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Apellido` varchar(100) DEFAULT NULL,
  `Telefono` varchar(30) DEFAULT NULL,
  `Cedula` varchar(40) DEFAULT NULL,
  `Rut` varchar(40) DEFAULT NULL,
  `Direccion` varchar(255) DEFAULT NULL,
  `Estado` enum('Pendiente','Activa','Bloqueada') NOT NULL DEFAULT 'Pendiente',
  `CorreoVerificadoEn` datetime DEFAULT NULL,
  `UltimoAccesoEn` datetime DEFAULT NULL,
  `IntentosFallidos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `BloqueadaHasta` datetime DEFAULT NULL,
  `ClaveCambiadaEn` datetime DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdCuenta`),
  UNIQUE KEY `uq_ecommerce_cuenta_correo` (`Correo`),
  KEY `idx_ecommerce_cuenta_cliente` (`IdCliente`),
  CONSTRAINT `fk_ecommerce_cuenta_cliente` FOREIGN KEY (`IdCliente`) REFERENCES `cliente` (`IdCliente`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_direccion` (
  `IdDireccion` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdCuenta` bigint(20) unsigned NOT NULL,
  `Etiqueta` varchar(60) NOT NULL DEFAULT 'Principal',
  `Direccion` varchar(255) NOT NULL,
  `Ciudad` varchar(100) NOT NULL,
  `Departamento` varchar(100) NOT NULL,
  `CodigoPostal` varchar(20) DEFAULT NULL,
  `Referencias` varchar(255) DEFAULT NULL,
  `EsPrincipal` tinyint(1) NOT NULL DEFAULT 0,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdDireccion`),
  KEY `idx_ecommerce_direccion_cuenta` (`IdCuenta`),
  CONSTRAINT `fk_ecommerce_direccion_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_evento` (
  `IdEvento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdPedido` bigint(20) unsigned DEFAULT NULL,
  `IdCuenta` bigint(20) unsigned DEFAULT NULL,
  `Tipo` varchar(80) NOT NULL,
  `ClaveIdempotencia` varchar(190) NOT NULL,
  `PayloadJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`PayloadJson`)),
  `Estado` enum('Pendiente','Procesando','Completado','Fallido') NOT NULL DEFAULT 'Pendiente',
  `Intentos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `ProximoIntentoEn` datetime DEFAULT NULL,
  `ProcesadoEn` datetime DEFAULT NULL,
  `UltimoError` varchar(500) DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdEvento`),
  UNIQUE KEY `uq_ecommerce_evento_clave` (`ClaveIdempotencia`),
  KEY `idx_ecommerce_evento_cola` (`Estado`,`ProximoIntentoEn`,`IdEvento`),
  KEY `fk_ecommerce_evento_pedido` (`IdPedido`),
  KEY `fk_ecommerce_evento_cuenta` (`IdCuenta`),
  CONSTRAINT `fk_ecommerce_evento_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_evento_pedido` FOREIGN KEY (`IdPedido`) REFERENCES `ecommerce_pedido` (`IdPedido`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_limite_acceso` (
  `ClaveHash` char(64) NOT NULL,
  `VentanaInicio` datetime NOT NULL,
  `Intentos` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`ClaveHash`),
  KEY `idx_ecommerce_limite_ventana` (`VentanaInicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_metrica_diaria` (
  `Fecha` date NOT NULL,
  `Evento` varchar(50) NOT NULL,
  `Cantidad` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`Fecha`,`Evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_notificacion` (
  `IdNotificacion` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdCuenta` bigint(20) unsigned DEFAULT NULL,
  `IdPedido` bigint(20) unsigned DEFAULT NULL,
  `IdService` int(11) DEFAULT NULL,
  `Tipo` varchar(80) NOT NULL,
  `Destinatario` varchar(190) NOT NULL,
  `Estado` enum('Pendiente','Enviada','Fallida') NOT NULL DEFAULT 'Pendiente',
  `Intentos` smallint(5) unsigned NOT NULL DEFAULT 0,
  `UltimoError` varchar(500) DEFAULT NULL,
  `EnviadaEn` datetime DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdNotificacion`),
  UNIQUE KEY `uq_ecommerce_notificacion_pedido_tipo` (`IdPedido`,`Tipo`),
  UNIQUE KEY `uq_ecommerce_notificacion_service_tipo` (`IdService`,`Tipo`),
  KEY `idx_ecommerce_notificacion_cola` (`Estado`,`CreadoEn`),
  KEY `fk_ecommerce_notificacion_cuenta` (`IdCuenta`),
  CONSTRAINT `fk_ecommerce_notificacion_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_notificacion_pedido` FOREIGN KEY (`IdPedido`) REFERENCES `ecommerce_pedido` (`IdPedido`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_notificacion_service` FOREIGN KEY (`IdService`) REFERENCES `service_vehiculo` (`IdService`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_ocupacion_unidad` (
  `IdVehiculo` varchar(10) NOT NULL,
  `IdPedido` bigint(20) unsigned NOT NULL,
  `Estado` enum('Reservada','Consumida') NOT NULL DEFAULT 'Reservada',
  `ExpiraEn` datetime DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdVehiculo`),
  KEY `idx_ecommerce_ocupacion_pedido` (`IdPedido`),
  CONSTRAINT `fk_ecommerce_ocupacion_pedido` FOREIGN KEY (`IdPedido`) REFERENCES `ecommerce_pedido` (`IdPedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_ocupacion_vehiculo` FOREIGN KEY (`IdVehiculo`) REFERENCES `vehiculo` (`IdVehiculo`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_pago` (
  `IdPago` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdPedido` bigint(20) unsigned NOT NULL,
  `Proveedor` varchar(40) NOT NULL,
  `Tipo` varchar(30) NOT NULL DEFAULT 'Cobro',
  `IdExterno` varchar(190) DEFAULT NULL,
  `IdEventoExterno` varchar(190) DEFAULT NULL,
  `IdPreferencia` varchar(190) DEFAULT NULL,
  `Estado` varchar(40) NOT NULL DEFAULT 'pending',
  `Monto` decimal(12,2) NOT NULL,
  `Moneda` varchar(10) NOT NULL,
  `PayloadJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`PayloadJson`)),
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdPago`),
  UNIQUE KEY `uq_ecommerce_pago_externo` (`Proveedor`,`IdExterno`),
  UNIQUE KEY `uq_ecommerce_pago_evento` (`Proveedor`,`IdEventoExterno`),
  KEY `idx_ecommerce_pago_pedido` (`IdPedido`),
  CONSTRAINT `fk_ecommerce_pago_pedido` FOREIGN KEY (`IdPedido`) REFERENCES `ecommerce_pedido` (`IdPedido`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_pedido` (
  `IdPedido` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `StorefrontOrderUuid` char(36) DEFAULT NULL,
  `StorefrontReservationId` char(36) DEFAULT NULL,
  `NumeroPedido` varchar(32) NOT NULL,
  `TokenPublico` char(64) NOT NULL,
  `IdCuenta` bigint(20) unsigned DEFAULT NULL,
  `IdCliente` int(11) DEFAULT NULL,
  `Estado` enum('PendientePago','PagoEnRevision','Pagado','Preparando','Listo','Entregado','ExcepcionPagoSinStock','ReembolsoPendiente','Reembolsado','Cancelado','Vencido') NOT NULL DEFAULT 'PendientePago',
  `EstadoPago` enum('Pendiente','EnProceso','Aprobado','Rechazado','ReembolsoPendiente','Reembolsado','ReembolsoFallido','Contracargo','Cancelado') NOT NULL DEFAULT 'Pendiente',
  `Nombre` varchar(100) NOT NULL,
  `Apellido` varchar(100) NOT NULL,
  `Correo` varchar(190) NOT NULL,
  `Telefono` varchar(30) NOT NULL,
  `Cedula` varchar(40) DEFAULT NULL,
  `Entrega` enum('Retiro','Envio') NOT NULL DEFAULT 'Retiro',
  `Direccion` varchar(255) DEFAULT NULL,
  `Ciudad` varchar(100) DEFAULT NULL,
  `Departamento` varchar(100) DEFAULT NULL,
  `CodigoPostal` varchar(20) DEFAULT NULL,
  `Notas` varchar(500) DEFAULT NULL,
  `AceptoTerminosEn` datetime DEFAULT NULL,
  `VersionTerminos` varchar(20) DEFAULT NULL,
  `Moneda` enum('UYU','USD') NOT NULL DEFAULT 'UYU',
  `Subtotal` decimal(12,2) NOT NULL,
  `CostoEnvio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Total` decimal(12,2) NOT NULL,
  `ProveedorPago` varchar(40) NOT NULL DEFAULT 'mercadopago',
  `ExpiraEn` datetime NOT NULL,
  `PagadoEn` datetime DEFAULT NULL,
  `EntregadoEn` datetime DEFAULT NULL,
  `CanceladoEn` datetime DEFAULT NULL,
  `MotivoCancelacion` varchar(500) DEFAULT NULL,
  `EntregadoPor` int(11) DEFAULT NULL,
  `ReceptorEntrega` varchar(150) DEFAULT NULL,
  `EvidenciaEntrega` varchar(255) DEFAULT NULL,
  `VersionBloqueo` int(10) unsigned NOT NULL DEFAULT 0,
  `IdVenta` int(11) DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdPedido`),
  UNIQUE KEY `uq_ecommerce_pedido_numero` (`NumeroPedido`),
  UNIQUE KEY `uq_ecommerce_pedido_token` (`TokenPublico`),
  UNIQUE KEY `uq_ecommerce_pedido_storefront_uuid` (`StorefrontOrderUuid`),
  UNIQUE KEY `uq_ecommerce_pedido_storefront_reservation` (`StorefrontReservationId`),
  KEY `idx_ecommerce_pedido_cuenta` (`IdCuenta`,`CreadoEn`),
  KEY `idx_ecommerce_pedido_estado` (`Estado`,`ExpiraEn`),
  KEY `fk_ecommerce_pedido_cliente` (`IdCliente`),
  KEY `fk_ecommerce_pedido_venta` (`IdVenta`),
  KEY `fk_ecommerce_pedido_entregado_por` (`EntregadoPor`),
  CONSTRAINT `fk_ecommerce_pedido_cliente` FOREIGN KEY (`IdCliente`) REFERENCES `cliente` (`IdCliente`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_pedido_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_pedido_entregado_por` FOREIGN KEY (`EntregadoPor`) REFERENCES `usuario` (`IdUsuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_pedido_venta` FOREIGN KEY (`IdVenta`) REFERENCES `venta` (`IdVenta`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_pedido_item` (
  `IdItem` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdPedido` bigint(20) unsigned NOT NULL,
  `IdVehiculo` varchar(10) NOT NULL,
  `IdProducto` int(11) NOT NULL,
  `Modelo` varchar(100) NOT NULL,
  `CapacidadBateriaAh` smallint(5) unsigned DEFAULT NULL,
  `Color` varchar(60) DEFAULT NULL,
  `PrecioUnitario` decimal(12,2) NOT NULL,
  `Cantidad` smallint(5) unsigned NOT NULL DEFAULT 1,
  `Total` decimal(12,2) NOT NULL,
  PRIMARY KEY (`IdItem`),
  UNIQUE KEY `uq_ecommerce_item_vehiculo_activo` (`IdPedido`,`IdVehiculo`),
  KEY `idx_ecommerce_item_vehiculo` (`IdVehiculo`),
  KEY `fk_ecommerce_item_producto` (`IdProducto`),
  CONSTRAINT `fk_ecommerce_item_pedido` FOREIGN KEY (`IdPedido`) REFERENCES `ecommerce_pedido` (`IdPedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_item_producto` FOREIGN KEY (`IdProducto`) REFERENCES `producto` (`IdProducto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_item_vehiculo` FOREIGN KEY (`IdVehiculo`) REFERENCES `vehiculo` (`IdVehiculo`) ON UPDATE CASCADE,
  CONSTRAINT `chk_rel01_ecommerce_pedido_item_cantidad_positive` CHECK (`Cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_solicitud_privacidad` (
  `IdSolicitud` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdCuenta` bigint(20) unsigned NOT NULL,
  `Tipo` enum('Acceso','Correccion','Supresion') NOT NULL,
  `Detalle` varchar(1000) DEFAULT NULL,
  `Estado` enum('Pendiente','EnProceso','Resuelta','Rechazada') NOT NULL DEFAULT 'Pendiente',
  `Respuesta` varchar(1000) DEFAULT NULL,
  `ResueltaPor` int(11) DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ResueltoEn` datetime DEFAULT NULL,
  PRIMARY KEY (`IdSolicitud`),
  KEY `idx_ecommerce_privacidad_estado` (`Estado`,`CreadoEn`),
  KEY `fk_ecommerce_privacidad_cuenta` (`IdCuenta`),
  KEY `fk_ecommerce_privacidad_usuario` (`ResueltaPor`),
  CONSTRAINT `fk_ecommerce_privacidad_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ecommerce_privacidad_usuario` FOREIGN KEY (`ResueltaPor`) REFERENCES `usuario` (`IdUsuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_token` (
  `IdToken` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdCuenta` bigint(20) unsigned NOT NULL,
  `Tipo` enum('VerificarCorreo','RestablecerClave') NOT NULL,
  `TokenHash` char(64) NOT NULL,
  `ExpiraEn` datetime NOT NULL,
  `UsadoEn` datetime DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdToken`),
  UNIQUE KEY `uq_ecommerce_token_hash` (`TokenHash`),
  KEY `idx_ecommerce_token_cuenta` (`IdCuenta`,`Tipo`),
  CONSTRAINT `fk_ecommerce_token_cuenta` FOREIGN KEY (`IdCuenta`) REFERENCES `ecommerce_cuenta` (`IdCuenta`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa` (
  `RUT` varchar(12) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `RazonSocial` varchar(160) DEFAULT NULL,
  `Correo` varchar(100) DEFAULT NULL,
  `Telefono` varchar(20) DEFAULT NULL,
  `WhatsApp` varchar(20) DEFAULT NULL,
  `Instagram` varchar(100) DEFAULT NULL,
  `Facebook` varchar(255) DEFAULT NULL,
  `Logo` varchar(500) DEFAULT NULL,
  `Direccion` varchar(255) DEFAULT NULL,
  `Descripcion` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`RUT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `garantia` (
  `IdGarantia` int(11) NOT NULL AUTO_INCREMENT,
  `IdVehiculo` varchar(10) NOT NULL,
  `IdVenta` int(11) NOT NULL,
  `IdCliente` int(11) NOT NULL,
  `FechaInicio` date NOT NULL,
  `FechaFin` date NOT NULL,
  `Estado` enum('Vigente','Vencida','Anulada') NOT NULL DEFAULT 'Vigente',
  `Observaciones` varchar(500) DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdGarantia`),
  UNIQUE KEY `uq_garantia_vehiculo_venta` (`IdVehiculo`,`IdVenta`),
  KEY `idx_garantia_vehiculo` (`IdVehiculo`),
  KEY `idx_garantia_cliente` (`IdCliente`),
  KEY `idx_garantia_venta` (`IdVenta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gasto` (
  `IdGasto` int(11) NOT NULL AUTO_INCREMENT,
  `IdVenta` int(11) DEFAULT NULL,
  `Concepto` varchar(150) NOT NULL,
  `Descripcion` varchar(500) DEFAULT NULL,
  `Observaciones` text DEFAULT NULL,
  `Monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `FechaGasto` date NOT NULL,
  `Categoria` varchar(100) DEFAULT NULL,
  `MetodoPago` enum('Efectivo','Tarjeta','Transferencia','Otro') DEFAULT 'Efectivo',
  `Moneda` enum('UYU','USD') NOT NULL DEFAULT 'UYU',
  `TipoCambioAplicado` decimal(10,4) NOT NULL DEFAULT 1.0000 COMMENT 'TC de la importación más reciente al momento de crear el gasto',
  `Estado` enum('Activo','Anulado') NOT NULL DEFAULT 'Activo',
  `FechaAnulacion` datetime DEFAULT NULL,
  `Origen` varchar(50) NOT NULL DEFAULT 'Manual',
  PRIMARY KEY (`IdGasto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_accion_sugerida` (
  `IdAccion` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `TipoAccion` varchar(80) NOT NULL,
  `IdLead` bigint(20) unsigned DEFAULT NULL,
  `IdInbox` bigint(20) unsigned DEFAULT NULL,
  `IdCliente` int(11) DEFAULT NULL,
  `ClienteNombre` varchar(160) DEFAULT NULL,
  `ClienteTelefono` varchar(40) DEFAULT NULL,
  `IdVehiculo` varchar(10) DEFAULT NULL,
  `VehiculoTexto` varchar(160) DEFAULT NULL,
  `ResponsableUsuarioId` int(11) DEFAULT NULL,
  `ResponsableNombre` varchar(160) DEFAULT NULL,
  `Prioridad` enum('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
  `Estado` enum('pendiente','confirmada','rechazada','ejecutada','error') NOT NULL DEFAULT 'pendiente',
  `MensajeOrigen` text DEFAULT NULL,
  `Motivo` text DEFAULT NULL,
  `Payload` longtext DEFAULT NULL,
  `FechaSugerida` datetime DEFAULT NULL,
  `FechaConfirmacion` datetime DEFAULT NULL,
  `FechaEjecucion` datetime DEFAULT NULL,
  `ConfirmadaPor` int(11) DEFAULT NULL,
  `EjecutadaPor` int(11) DEFAULT NULL,
  `ResultadoEjecucion` text DEFAULT NULL,
  `ErrorMessage` text DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdAccion`),
  KEY `idx_ia_tipo_estado_fecha` (`TipoAccion`,`Estado`,`FechaAlta`),
  KEY `idx_ia_lead` (`IdLead`),
  KEY `idx_ia_inbox` (`IdInbox`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `importacion` (
  `IdImportacion` int(11) NOT NULL AUTO_INCREMENT,
  `Numero` int(11) NOT NULL,
  `TipoCambioUSD` decimal(10,2) NOT NULL DEFAULT 44.00,
  `Fecha` date DEFAULT NULL,
  `Descripcion` varchar(200) DEFAULT NULL,
  `Activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`IdImportacion`),
  UNIQUE KEY `uq_numero` (`Numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `internal_alert` (
  `IdAlert` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `Tipo` varchar(80) NOT NULL,
  `Severidad` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `Titulo` varchar(220) NOT NULL,
  `Cuerpo` text DEFAULT NULL,
  `Estado` enum('abierta','leida','cerrada') NOT NULL DEFAULT 'abierta',
  `SourceType` varchar(80) DEFAULT NULL,
  `SourceId` bigint(20) DEFAULT NULL,
  `ResponsableUsuarioId` int(11) DEFAULT NULL,
  `FechaEvento` datetime DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdAlert`),
  UNIQUE KEY `uq_alert_source` (`Tipo`,`SourceType`,`SourceId`),
  KEY `idx_alert_estado_fecha` (`Estado`,`FechaAlta`),
  KEY `idx_alert_responsable` (`ResponsableUsuarioId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `IdAttempt` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `Ip` varchar(45) NOT NULL,
  `UsuarioIntentado` varchar(190) NOT NULL,
  `UserAgent` varchar(255) DEFAULT NULL,
  `Resultado` varchar(30) NOT NULL,
  `IntentosRecientes` int(10) unsigned NOT NULL DEFAULT 0,
  `BloqueadoHasta` datetime DEFAULT NULL,
  `FechaHora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdAttempt`),
  KEY `idx_login_attempts_lookup` (`Ip`,`UsuarioIntentado`,`FechaHora`),
  KEY `idx_login_attempts_block` (`Ip`,`UsuarioIntentado`,`BloqueadoHasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `n8n_webhook_log` (
  `IdLog` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `EventKey` varchar(80) NOT NULL,
  `Status` varchar(32) NOT NULL,
  `SourceType` varchar(80) DEFAULT NULL,
  `SourceId` bigint(20) DEFAULT NULL,
  `WebhookUrl` text DEFAULT NULL,
  `HttpStatus` int(11) DEFAULT NULL,
  `ResponseBody` longtext DEFAULT NULL,
  `ErrorMessage` text DEFAULT NULL,
  `Payload` longtext DEFAULT NULL,
  `SentAt` datetime DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdLog`),
  KEY `idx_n8n_log_event_fecha` (`EventKey`,`FechaAlta`),
  KEY `idx_n8n_log_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `n8n_webhook_setting` (
  `IdSetting` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `EventKey` varchar(80) NOT NULL,
  `Label` varchar(160) NOT NULL,
  `WebhookUrl` text DEFAULT NULL,
  `Enabled` tinyint(1) NOT NULL DEFAULT 0,
  `TimeoutSeconds` int(10) unsigned NOT NULL DEFAULT 10,
  `Secret` varchar(255) DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdSetting`),
  UNIQUE KEY `EventKey` (`EventKey`),
  KEY `idx_n8n_setting_enabled` (`Enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion_whatsapp` (
  `IdNotificacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Tipo` enum('venta','service') NOT NULL,
  `IdReferencia` int(11) NOT NULL,
  `Telefono` varchar(30) NOT NULL,
  `Template` varchar(100) NOT NULL,
  `Estado` enum('enviado','error','omitido') NOT NULL DEFAULT 'omitido',
  `RespuestaMeta` text DEFAULT NULL,
  `FechaEnvio` datetime NOT NULL DEFAULT current_timestamp(),
  `WaMessageId` varchar(255) DEFAULT NULL,
  `EstadoEntrega` varchar(40) DEFAULT NULL,
  `RespuestaWebhook` text DEFAULT NULL,
  `FechaEstadoMeta` datetime DEFAULT NULL,
  PRIMARY KEY (`IdNotificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `panel_idempotency_key` (
  `IdKey` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `OperationKey` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OperationType` varchar(80) NOT NULL,
  `RequestHash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `Status` enum('processing','completed') NOT NULL DEFAULT 'processing',
  `IdUsuario` int(11) DEFAULT NULL,
  `ResultType` varchar(40) DEFAULT NULL,
  `ResultId` varchar(80) DEFAULT NULL,
  `RedirectUrl` varchar(255) DEFAULT NULL,
  `ExpiraEn` datetime NOT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `CompletedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`IdKey`),
  UNIQUE KEY `uq_panel_idempotency_operation_key` (`OperationKey`),
  KEY `idx_panel_idempotency_expira` (`ExpiraEn`),
  KEY `idx_panel_idempotency_type_result` (`OperationType`,`ResultType`,`ResultId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `postventa_historial_tecnico` (
  `IdHistorialTecnico` int(11) NOT NULL AUTO_INCREMENT,
  `IdVehiculo` varchar(10) NOT NULL,
  `IdVenta` int(11) DEFAULT NULL,
  `IdCliente` int(11) DEFAULT NULL,
  `IdService` int(11) DEFAULT NULL,
  `Diagnostico` varchar(700) NOT NULL,
  `SolucionAplicada` varchar(700) DEFAULT NULL,
  `Tecnico` varchar(120) DEFAULT NULL,
  `Estado` enum('Abierta','En reparación','En espera','Cerrada','Cancelada') NOT NULL DEFAULT 'Abierta',
  `FechaApertura` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaInicioReparacion` datetime DEFAULT NULL,
  `FechaCierre` datetime DEFAULT NULL,
  `TiempoTotalMinutos` int(11) DEFAULT NULL,
  `TiempoEsperaMinutos` int(11) DEFAULT NULL,
  `Observaciones` varchar(700) DEFAULT NULL,
  `IdUsuarioRegistra` int(11) DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdHistorialTecnico`),
  KEY `idx_postventa_historial_vehiculo` (`IdVehiculo`,`FechaApertura`),
  KEY `idx_postventa_historial_estado` (`Estado`),
  KEY `idx_postventa_historial_service` (`IdService`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `postventa_repuesto_usado` (
  `IdRepuestoUsado` int(11) NOT NULL AUTO_INCREMENT,
  `IdHistorialTecnico` int(11) NOT NULL,
  `IdRepuesto` int(11) NOT NULL,
  `IdProducto` int(11) NOT NULL,
  `Cantidad` int(11) NOT NULL,
  `CostoUnitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Moneda` enum('UYU','USD') NOT NULL DEFAULT 'UYU',
  `FechaUso` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdRepuestoUsado`),
  KEY `idx_postventa_repuesto_historial` (`IdHistorialTecnico`),
  KEY `idx_postventa_repuesto_repuesto` (`IdRepuesto`),
  CONSTRAINT `fk_postventa_repuesto_historial` FOREIGN KEY (`IdHistorialTecnico`) REFERENCES `postventa_historial_tecnico` (`IdHistorialTecnico`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_postventa_repuesto_repuesto` FOREIGN KEY (`IdRepuesto`) REFERENCES `repuesto` (`IdRepuesto`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto` (
  `IdProducto` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) NOT NULL,
  `Slug` varchar(160) DEFAULT NULL,
  `TipoProducto` enum('Moto','Repuesto') NOT NULL,
  `Descripcion` varchar(1000) DEFAULT NULL,
  `DescripcionWeb` text DEFAULT NULL,
  `Costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `GastoTotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `PrecioVenta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `PrecioDistribuidor` decimal(10,2) DEFAULT NULL,
  `Stock` int(11) NOT NULL DEFAULT 0,
  `Estado` enum('Disponible','Sin stock','Reservado','Vendido','Oculto') NOT NULL DEFAULT 'Disponible',
  `MostrarEnWeb` tinyint(1) NOT NULL DEFAULT 0,
  `DestacadoWeb` tinyint(1) NOT NULL DEFAULT 0,
  `OrdenWeb` int(11) NOT NULL DEFAULT 0,
  `TextoBotonWeb` varchar(80) DEFAULT NULL,
  `Empresa_RUT` varchar(12) NOT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `Moneda` enum('UYU','USD') NOT NULL DEFAULT 'USD',
  PRIMARY KEY (`IdProducto`),
  UNIQUE KEY `uq_producto_slug` (`Slug`),
  KEY `idx_producto_empresa` (`Empresa_RUT`),
  KEY `idx_producto_tipo` (`TipoProducto`),
  KEY `idx_producto_estado` (`Estado`),
  KEY `idx_producto_slug` (`Slug`),
  KEY `idx_producto_web` (`TipoProducto`,`MostrarEnWeb`,`DestacadoWeb`,`OrdenWeb`,`Estado`,`Stock`),
  CONSTRAINT `fk_producto_empresa` FOREIGN KEY (`Empresa_RUT`) REFERENCES `empresa` (`RUT`) ON UPDATE CASCADE,
  CONSTRAINT `chk_rel01_producto_stock_nonnegative` CHECK (`Stock` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER trg_producto_storefront_variant_au
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `remito` (
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
  KEY `IdDistribuidor` (`IdDistribuidor`),
  KEY `idx_remito_pedido` (`IdPedido`),
  KEY `idx_remito_venta` (`IdVenta`),
  KEY `idx_remito_estado_fecha` (`Estado`,`FechaEmision`),
  KEY `idx_remito_numero_factura` (`NumeroFactura`),
  CONSTRAINT `remito_ibfk_1` FOREIGN KEY (`IdDistribuidor`) REFERENCES `distribuidor` (`IdDistribuidor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repuesto` (
  `IdRepuesto` int(11) NOT NULL AUTO_INCREMENT,
  `IdProducto` int(11) NOT NULL,
  `NombreInterno` varchar(100) DEFAULT NULL,
  `NumeroImportacion` int(11) DEFAULT NULL,
  `TipoCambioImportacion` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`IdRepuesto`),
  UNIQUE KEY `uq_repuesto_idproducto` (`IdProducto`),
  CONSTRAINT `fk_repuesto_producto` FOREIGN KEY (`IdProducto`) REFERENCES `producto` (`IdProducto`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_historial` (
  `IdHistorial` int(11) NOT NULL AUTO_INCREMENT,
  `IdService` int(11) NOT NULL,
  `IdVehiculo` varchar(10) NOT NULL,
  `IdVenta` int(11) DEFAULT NULL,
  `TipoEvento` enum('CREADO','NOTA','REALIZADO','CANCELADO','VENCIDO','SISTEMA','NOTIFICACION_WA') NOT NULL DEFAULT 'NOTA',
  `Detalle` varchar(700) DEFAULT NULL,
  `Usuario` varchar(120) DEFAULT NULL,
  `FechaEvento` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdHistorial`),
  KEY `idx_historial_service` (`IdService`),
  KEY `idx_historial_vehiculo` (`IdVehiculo`),
  KEY `idx_historial_evento` (`TipoEvento`),
  KEY `idx_historial_fecha` (`FechaEvento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_vehiculo` (
  `IdService` int(11) NOT NULL AUTO_INCREMENT,
  `IdVehiculo` varchar(10) NOT NULL,
  `IdVenta` int(11) NOT NULL,
  `IdCliente` int(11) NOT NULL,
  `NumeroService` tinyint(4) NOT NULL,
  `FechaProgramada` date NOT NULL,
  `FechaRealizada` date DEFAULT NULL,
  `Estado` enum('Pendiente','Realizado','Vencido','Cancelado') NOT NULL DEFAULT 'Pendiente',
  `Observaciones` varchar(500) DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdService`),
  UNIQUE KEY `uq_service_vehiculo_venta_numero` (`IdVehiculo`,`IdVenta`,`NumeroService`),
  KEY `idx_service_vehiculo` (`IdVehiculo`),
  KEY `idx_service_cliente` (`IdCliente`),
  KEY `idx_service_estado` (`Estado`),
  KEY `idx_service_fecha` (`FechaProgramada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storefront_api_idempotency` (
  `IdempotencyKey` char(36) NOT NULL,
  `Operation` varchar(80) NOT NULL,
  `RequestHash` char(64) NOT NULL,
  `HttpStatus` smallint(5) unsigned DEFAULT NULL,
  `ResponseJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ResponseJson`)),
  `ExpiraEn` datetime NOT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdempotencyKey`),
  KEY `idx_storefront_api_idempotency_expira` (`ExpiraEn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storefront_api_nonce` (
  `KeyId` varchar(80) NOT NULL,
  `Nonce` varchar(128) NOT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ExpiraEn` datetime NOT NULL,
  PRIMARY KEY (`KeyId`,`Nonce`),
  KEY `idx_storefront_api_nonce_expira` (`ExpiraEn`),
  KEY `idx_storefront_api_nonce_rate` (`KeyId`,`CreadoEn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storefront_api_rate_window` (
  `KeyId` varchar(80) NOT NULL,
  `WindowStart` datetime NOT NULL,
  `RequestCount` int(10) unsigned NOT NULL DEFAULT 0,
  `ExpiraEn` datetime NOT NULL,
  PRIMARY KEY (`KeyId`,`WindowStart`),
  KEY `idx_storefront_api_rate_window_expira` (`ExpiraEn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `storefront_catalogo_motos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `storefront_catalogo_motos` AS SELECT
 1 AS `id_producto`,
  1 AS `nombre`,
  1 AS `modelo`,
  1 AS `slug`,
  1 AS `precio`,
  1 AS `moneda`,
  1 AS `stock`,
  1 AS `destacado`,
  1 AS `orden`,
  1 AS `capacidad_bateria_ah`,
  1 AS `color`,
  1 AS `descripcion`,
  1 AS `disponible` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storefront_privacy_request` (
  `RequestUuid` char(36) NOT NULL,
  `UserUuid` char(36) NOT NULL,
  `Tipo` enum('access','correction','suppression','objection') NOT NULL,
  `Estado` enum('submitted','in_review','resolved','rejected') NOT NULL DEFAULT 'submitted',
  `Nombre` varchar(220) NOT NULL,
  `Correo` varchar(190) NOT NULL,
  `Detalle` text DEFAULT NULL,
  `VenceEn` datetime NOT NULL,
  `Respuesta` text DEFAULT NULL,
  `ResueltaPor` int(11) DEFAULT NULL,
  `ResueltaEn` datetime DEFAULT NULL,
  `NotificacionEncolada` tinyint(1) NOT NULL DEFAULT 0,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`RequestUuid`),
  KEY `idx_storefront_privacy_status_due` (`Estado`,`VenceEn`),
  KEY `fk_storefront_privacy_user` (`ResueltaPor`),
  CONSTRAINT `fk_storefront_privacy_user` FOREIGN KEY (`ResueltaPor`) REFERENCES `usuario` (`IdUsuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storefront_reservation` (
  `ReservationId` char(36) NOT NULL,
  `OrderUuid` char(36) NOT NULL,
  `PaymentMethod` enum('cash','card') NOT NULL,
  `Estado` enum('active','released','expired','consumed') NOT NULL DEFAULT 'active',
  `Moneda` char(3) NOT NULL,
  `Subtotal` decimal(15,2) NOT NULL,
  `Descuento` decimal(15,2) NOT NULL DEFAULT 0.00,
  `Total` decimal(15,2) NOT NULL,
  `ExpiraEn` datetime NOT NULL,
  `LiberadaEn` datetime DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ReservationId`),
  UNIQUE KEY `uq_storefront_reservation_order` (`OrderUuid`),
  KEY `idx_storefront_reservation_expiry` (`Estado`,`ExpiraEn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `storefront_reservation_item` (
  `ReservationId` char(36) NOT NULL,
  `IdVehiculo` varchar(10) NOT NULL,
  `IdProducto` int(11) NOT NULL,
  `VariantId` char(64) NOT NULL,
  `Modelo` varchar(100) NOT NULL,
  `CapacidadBateriaAh` smallint(5) unsigned DEFAULT NULL,
  `Color` varchar(80) NOT NULL DEFAULT '',
  `PrecioBruto` decimal(15,2) NOT NULL,
  `TasaIVA` decimal(7,4) NOT NULL,
  `MostrarEnWebAnterior` tinyint(1) NOT NULL,
  `DestacadoWebAnterior` tinyint(1) NOT NULL,
  PRIMARY KEY (`ReservationId`,`IdVehiculo`),
  KEY `idx_storefront_reservation_item_variant` (`VariantId`),
  KEY `fk_storefront_reservation_item_product` (`IdProducto`),
  KEY `idx_storefront_reserved_vehicle` (`IdVehiculo`),
  CONSTRAINT `fk_storefront_reservation_item_product` FOREIGN KEY (`IdProducto`) REFERENCES `producto` (`IdProducto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_storefront_reservation_item_reservation` FOREIGN KEY (`ReservationId`) REFERENCES `storefront_reservation` (`ReservationId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_storefront_reservation_item_vehicle` FOREIGN KEY (`IdVehiculo`) REFERENCES `vehiculo` (`IdVehiculo`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_catalog` (
  `IdCatalog` int(11) NOT NULL AUTO_INCREMENT,
  `CatalogGroup` varchar(100) NOT NULL,
  `CatalogKey` varchar(100) NOT NULL,
  `CatalogValue` varchar(150) NOT NULL,
  `CatalogLabel` varchar(150) NOT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT 0,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `MetadataJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`MetadataJson`)),
  PRIMARY KEY (`IdCatalog`),
  UNIQUE KEY `uq_catalog_group_key` (`CatalogGroup`,`CatalogKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_module` (
  `IdModule` int(11) NOT NULL AUTO_INCREMENT,
  `ModuleCode` varchar(100) NOT NULL,
  `ModuleLabel` varchar(150) NOT NULL,
  `ModulePath` varchar(200) NOT NULL,
  `ModuleContext` varchar(50) NOT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT 0,
  `RolesJson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`RolesJson`)),
  `IsVisible` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`IdModule`),
  UNIQUE KEY `uq_module_code_context` (`ModuleCode`,`ModuleContext`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_delivery` (
  `IdDelivery` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `Tipo` varchar(80) NOT NULL,
  `IdReferencia` bigint(20) unsigned DEFAULT NULL,
  `ChatId` varchar(80) NOT NULL,
  `Estado` varchar(32) NOT NULL,
  `TelegramMessageId` bigint(20) DEFAULT NULL,
  `ErrorMessage` text DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdDelivery`),
  UNIQUE KEY `uq_telegram_tipo_ref_chat` (`Tipo`,`IdReferencia`,`ChatId`),
  KEY `idx_telegram_estado_fecha` (`Estado`,`FechaAlta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `IdUsuario` int(11) NOT NULL AUTO_INCREMENT,
  `NombreCompleto` varchar(120) NOT NULL,
  `Usuario` varchar(60) NOT NULL,
  `ClaveHash` varchar(255) NOT NULL,
  `Rol` enum('Superadmin','Administrador','Vendedor','Distribuidor') NOT NULL DEFAULT 'Vendedor',
  `IdDistribuidor` int(11) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaAlta` timestamp NULL DEFAULT current_timestamp(),
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_secret` varchar(190) DEFAULT NULL,
  `mfa_recovery_codes` text DEFAULT NULL,
  `ComisionPct` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Comisión % por venta para vendedores',
  `ComisionDistribuidorPct` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`IdUsuario`),
  UNIQUE KEY `Usuario` (`Usuario`),
  KEY `fk_usuario_distribuidor` (`IdDistribuidor`),
  CONSTRAINT `fk_usuario_distribuidor` FOREIGN KEY (`IdDistribuidor`) REFERENCES `distribuidor` (`IdDistribuidor`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehiculo` (
  `IdVehiculo` varchar(10) NOT NULL,
  `IdProducto` int(11) NOT NULL,
  `NumeroMotor` varchar(50) NOT NULL,
  `Modelo` varchar(80) NOT NULL,
  `CapacidadBateriaAh` smallint(5) unsigned DEFAULT NULL,
  `Color` varchar(30) DEFAULT NULL,
  `NumeroImportacion` int(11) DEFAULT NULL,
  `TipoCambioImportacion` decimal(10,2) DEFAULT NULL,
  `FechaIngreso` date DEFAULT NULL,
  `FechaVenta` date DEFAULT NULL,
  `ClienteReservaId` int(11) DEFAULT NULL,
  `FechaReserva` datetime DEFAULT NULL,
  `SeniaReserva` decimal(10,2) DEFAULT NULL,
  `StorefrontVariantId` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`IdVehiculo`),
  UNIQUE KEY `uq_vehiculo_idproducto` (`IdProducto`),
  UNIQUE KEY `uq_vehiculo_numeromotor` (`NumeroMotor`),
  KEY `fk_vehiculo_cliente_reserva` (`ClienteReservaId`),
  KEY `idx_vehiculo_producto` (`IdProducto`),
  KEY `idx_vehiculo_reserva` (`ClienteReservaId`),
  KEY `idx_vehiculo_modelo_bateria` (`Modelo`,`CapacidadBateriaAh`),
  KEY `idx_vehiculo_storefront_variant` (`StorefrontVariantId`,`IdVehiculo`),
  CONSTRAINT `fk_vehiculo_cliente_reserva` FOREIGN KEY (`ClienteReservaId`) REFERENCES `cliente` (`IdCliente`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_vehiculo_producto` FOREIGN KEY (`IdProducto`) REFERENCES `producto` (`IdProducto`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER trg_vehiculo_storefront_variant_bi
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER trg_vehiculo_storefront_variant_bu
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehiculo_imagen` (
  `IdImagen` int(11) NOT NULL AUTO_INCREMENT,
  `IdVehiculo` varchar(10) NOT NULL,
  `RutaImagen` varchar(500) NOT NULL,
  `EsPrincipal` tinyint(1) NOT NULL DEFAULT 0,
  `OrdenImagen` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`IdImagen`),
  KEY `idx_vehiculo_imagen` (`IdVehiculo`),
  CONSTRAINT `fk_vehiculo_imagen_vehiculo` FOREIGN KEY (`IdVehiculo`) REFERENCES `vehiculo` (`IdVehiculo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehiculo_seq` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta` (
  `IdVenta` int(11) NOT NULL AUTO_INCREMENT,
  `Cliente_IdCliente` int(11) NOT NULL,
  `FechaVenta` datetime NOT NULL DEFAULT current_timestamp(),
  `MetodoPago` enum('Efectivo','Tarjeta','Transferencia','Otro') NOT NULL DEFAULT 'Efectivo',
  `TipoTarjeta` enum('Crédito','Débito') DEFAULT NULL,
  `MarcaTarjeta` enum('Visa','Mastercard') DEFAULT NULL,
  `CuotasTarjeta` tinyint(3) unsigned DEFAULT NULL,
  `EstadoVenta` varchar(20) NOT NULL DEFAULT 'Confirmada',
  `TipoCliente` enum('Final','Distribuidor') NOT NULL DEFAULT 'Final',
  `Distribuidor_IdDistribuidor` int(11) DEFAULT NULL,
  `DistribuidorVendedorId` int(11) DEFAULT NULL,
  `UsuarioVendedorId` int(11) DEFAULT NULL,
  `Total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `MontoPagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `SaldoPendiente` decimal(12,2) NOT NULL DEFAULT 0.00,
  `GananciaEstimada` decimal(10,2) NOT NULL DEFAULT 0.00,
  `SubtotalBruto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `DescuentoAplicado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `RecargoAplicado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ComisionTarjeta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ComisionDistribuidor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ComisionVendedor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `MontoIVA` decimal(12,2) NOT NULL DEFAULT 0.00,
  `TotalSinIVA` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Observaciones` varchar(500) DEFAULT NULL,
  `Moneda` enum('UYU','USD') NOT NULL DEFAULT 'UYU',
  `TipoCambioAplicado` decimal(10,2) DEFAULT NULL,
  `NumeroFactura` varchar(50) DEFAULT NULL,
  `MotivoAnulacion` text DEFAULT NULL,
  `FechaAnulacion` datetime DEFAULT NULL,
  `AnuladaPorUsuarioId` int(11) DEFAULT NULL,
  `UsuarioAnulacion` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`IdVenta`),
  KEY `idx_venta_cliente` (`Cliente_IdCliente`),
  KEY `idx_venta_estado_fecha` (`EstadoVenta`,`FechaVenta`),
  KEY `idx_venta_distribuidor_vendedor` (`DistribuidorVendedorId`,`FechaVenta`),
  KEY `idx_venta_usuario_vendedor` (`UsuarioVendedorId`,`FechaVenta`),
  CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`Cliente_IdCliente`) REFERENCES `cliente` (`IdCliente`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta_detalle` (
  `IdVentaDetalle` int(11) NOT NULL AUTO_INCREMENT,
  `Venta_IdVenta` int(11) NOT NULL,
  `Producto_IdProducto` int(11) NOT NULL,
  `Cantidad` int(11) NOT NULL DEFAULT 1,
  `PrecioUnitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `CostoUnitario` decimal(12,2) DEFAULT NULL,
  `Subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `GananciaLinea` decimal(12,2) DEFAULT NULL,
  `Moneda` enum('UYU','USD') NOT NULL DEFAULT 'UYU',
  PRIMARY KEY (`IdVentaDetalle`),
  KEY `idx_detalle_venta` (`Venta_IdVenta`),
  KEY `idx_detalle_producto` (`Producto_IdProducto`),
  KEY `idx_venta_detalle_venta` (`Venta_IdVenta`),
  KEY `idx_venta_detalle_producto` (`Producto_IdProducto`),
  CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`Producto_IdProducto`) REFERENCES `producto` (`IdProducto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_venta` FOREIGN KEY (`Venta_IdVenta`) REFERENCES `venta` (`IdVenta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_rel01_venta_detalle_cantidad_positive` CHECK (`Cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `vw_motos_web`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vw_motos_web` AS SELECT
 1 AS `IdProducto`,
  1 AS `IdVehiculo`,
  1 AS `Modelo`,
  1 AS `Color`,
  1 AS `Nombre`,
  1 AS `Slug`,
  1 AS `Descripcion`,
  1 AS `DescripcionWeb`,
  1 AS `PrecioVenta`,
  1 AS `Moneda`,
  1 AS `MostrarEnWeb`,
  1 AS `DestacadoWeb`,
  1 AS `OrdenWeb`,
  1 AS `TextoBotonWeb`,
  1 AS `ImagenPrincipal` */;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_push_delivery` (
  `IdDelivery` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdEvent` bigint(20) unsigned NOT NULL,
  `IdSubscription` bigint(20) unsigned NOT NULL,
  `IdPedido` bigint(20) unsigned DEFAULT NULL,
  `Estado` varchar(32) NOT NULL,
  `Intentos` smallint(5) unsigned NOT NULL DEFAULT 1,
  `CodigoError` varchar(64) DEFAULT NULL,
  `FechaUltimoIntento` datetime DEFAULT NULL,
  `FechaEntrega` datetime DEFAULT NULL,
  `ErrorMessage` text DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IdDelivery`),
  UNIQUE KEY `uq_push_event_subscription` (`IdEvent`,`IdSubscription`),
  KEY `idx_push_delivery_estado` (`Estado`),
  KEY `idx_push_delivery_pedido` (`IdPedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_push_subscription` (
  `IdSubscription` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `IdUsuario` int(11) NOT NULL,
  `Endpoint` text NOT NULL,
  `EndpointHash` char(64) NOT NULL,
  `PublicKey` varchar(255) NOT NULL,
  `AuthToken` varchar(255) NOT NULL,
  `ContentEncoding` varchar(32) NOT NULL DEFAULT 'aes128gcm',
  `UserAgent` varchar(500) DEFAULT NULL,
  `Activa` tinyint(1) NOT NULL DEFAULT 1,
  `UltimoEnvio` datetime DEFAULT NULL,
  `UltimoError` text DEFAULT NULL,
  `FechaAlta` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaActualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`IdSubscription`),
  UNIQUE KEY `EndpointHash` (`EndpointHash`),
  KEY `idx_push_usuario_activa` (`IdUsuario`,`Activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_media_cache` (
  `SourceKey` char(64) NOT NULL,
  `Url` text NOT NULL,
  `LocalPath` varchar(500) DEFAULT NULL,
  `FileHash` char(64) DEFAULT NULL,
  `MimeType` varchar(80) NOT NULL,
  `MediaId` varchar(255) NOT NULL,
  `RespuestaMeta` text DEFAULT NULL,
  `CreadoEn` datetime NOT NULL DEFAULT current_timestamp(),
  `ActualizadoEn` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`SourceKey`),
  KEY `idx_whatsapp_media_media_id` (`MediaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_test_reset` (
  `Telefono` varchar(30) NOT NULL,
  `Estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `FechaSolicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaClaim` datetime DEFAULT NULL,
  `FechaConsumo` datetime DEFAULT NULL,
  PRIMARY KEY (`Telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `storefront_catalogo_motos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `storefront_catalogo_motos` AS select `p`.`IdProducto` AS `id_producto`,coalesce(`p`.`Nombre`,`v`.`Modelo`) AS `nombre`,`v`.`Modelo` AS `modelo`,`p`.`Slug` AS `slug`,`p`.`PrecioVenta` AS `precio`,`p`.`Moneda` AS `moneda`,`p`.`Stock` AS `stock`,coalesce(`p`.`DestacadoWeb`,0) AS `destacado`,coalesce(`p`.`OrdenWeb`,0) AS `orden`,`v`.`CapacidadBateriaAh` AS `capacidad_bateria_ah`,`v`.`Color` AS `color`,coalesce(`p`.`DescripcionWeb`,`p`.`Descripcion`) AS `descripcion`,`p`.`MostrarEnWeb` = 1 and `p`.`Estado` = 'Disponible' and `p`.`Stock` > 0 AS `disponible` from (`producto` `p` join `vehiculo` `v` on(`v`.`IdProducto` = `p`.`IdProducto`)) where `p`.`TipoProducto` = 'Moto' and `p`.`Slug` is not null and `p`.`Slug` <> '' and `p`.`DescripcionWeb` is not null and `p`.`DescripcionWeb` <> '' and `p`.`PrecioVenta` > 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_motos_web`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vw_motos_web` AS select `p`.`IdProducto` AS `IdProducto`,`v`.`IdVehiculo` AS `IdVehiculo`,`v`.`Modelo` AS `Modelo`,`v`.`Color` AS `Color`,`p`.`Nombre` AS `Nombre`,`p`.`Slug` AS `Slug`,`p`.`Descripcion` AS `Descripcion`,`p`.`DescripcionWeb` AS `DescripcionWeb`,`p`.`PrecioVenta` AS `PrecioVenta`,`p`.`Moneda` AS `Moneda`,`p`.`MostrarEnWeb` AS `MostrarEnWeb`,`p`.`DestacadoWeb` AS `DestacadoWeb`,`p`.`OrdenWeb` AS `OrdenWeb`,`p`.`TextoBotonWeb` AS `TextoBotonWeb`,(select `vi`.`RutaImagen` from `vehiculo_imagen` `vi` where `vi`.`IdVehiculo` = `v`.`IdVehiculo` order by `vi`.`EsPrincipal` desc,`vi`.`OrdenImagen`,`vi`.`IdImagen` limit 1) AS `ImagenPrincipal` from (`producto` `p` join `vehiculo` `v` on(`v`.`IdProducto` = `p`.`IdProducto`)) where `p`.`TipoProducto` = 'Moto' and `p`.`MostrarEnWeb` = 1 and `p`.`Estado` = 'Disponible' and `p`.`Stock` > 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

