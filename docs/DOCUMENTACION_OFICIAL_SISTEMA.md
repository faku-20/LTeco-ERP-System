# Documentación oficial del sistema Ltecobike

Versión documental: 2026-08-05
Sistema relevado: repositorio `/opt/ltecobike`  
Entorno local: `ltecobike_panel`, `ltecobike_web` y el stack `storefront_*` en Docker

> Para una instalación nueva, seguir `docs/SETUP.md` y
> `docs/MIGRACION_SERVIDOR.md`.
> Para evaluar vigencia, madurez y calidad documental, seguir
> `docs/CALIDAD_DOCUMENTACION_ISO.md`.

## 1. Propósito

Ltecobike es un sistema web para administrar la operación comercial de una empresa de movilidad eléctrica. Incluye un panel interno para ventas, stock, clientes, postventa, distribuidores, finanzas, usuarios, auditoría y configuración; además incluye una web pública de catálogo, contacto y verificación de comprobantes.

El sistema está orientado a:

- Controlar vehículos, repuestos y stock.
- Registrar ventas internas y ventas de distribuidores.
- Generar comprobantes y validar su autenticidad.
- Administrar clientes, garantías y servicios programados.
- Gestionar postventa técnica con historial e insumos usados.
- Llevar gastos, balance e importaciones.
- Publicar productos seleccionados en la web pública.
- Gestionar catálogo dinámico, cuentas, reservas y pedidos desde el storefront.
- Registrar auditoría y controles de seguridad del panel.

## 1.1 Gobernanza documental y calidad

La documentación vigente se mantiene con `docs/README.md` como índice primario.
Los documentos de cierre, validación o planes históricos conservan evidencia,
pero no reemplazan a esta referencia ni a `docs/ARQUITECTURA.md`.

El estándar interno de calidad documental usa ISO/IEC 9126 como antecedente e
ISO/IEC 25010:2023, ISO/IEC 25001:2014 e ISO/IEC 25012:2008 como marco SQuaRE
vigente. En la práctica, cada cambio relevante debe dejar evidencia documental
de:

- comportamiento funcional afectado;
- impacto en seguridad, privacidad, datos o roles;
- scripts, migraciones, endpoints o variables nuevas;
- pruebas o validaciones ejecutadas;
- riesgos aceptados o deuda residual.

El nivel documental actual es 3/5. La meta para el próximo cierre es 4/5:
documentación gestionada por evidencia, con módulos críticos enlazados a tests,
runbooks y criterios de release.

## 2. Componentes

### 2.1 Panel interno

Ruta de código: `lteco-panel/`  
URL local Docker: `http://127.0.0.1:8081/lteco-panel/`

Módulos principales:

- Login y MFA: `login.php`, `mfa_verificar.php`, `usuarios/mfa.php`.
- Inicio/lobby por rol: `inicio.php`.
- Dashboard: `dashboard.php`, `DashboardService`, `DashboardRepository` y `Domain/Dashboard/DashboardCalculo`.
- Buscador global: `busqueda/index.php`.
- Vehículos: `vehiculos/`.
- Repuestos: `repuestos/`.
- Clientes: `clientes/`.
- Ventas y comprobantes: `ventas/`.
- Postventa: `postventa/`.
- Gastos: `gastos/`.
- Balance: `balance/`.
- Importaciones: `importaciones/`.
- Distribuidores: `distribuidores/`.
- Usuarios: `usuarios/`.
- Configuración y mantenimiento: `configuracion/`.
- Auditoría: `auditoria/`.

### 2.2 Web pública

Ruta de código: `public-web/`  
URL local Docker: `http://127.0.0.1:8080/public-web/`

Pantallas:

- Home pública: `index.php`.
- Catálogo: `catalogo.php`.
- Detalle de modelo: `detalle.php`.
- Contacto: `contacto.php`.
- Verificación de comprobante: `verificar-comprobante.php`.

El scanner interno vive en `lteco-panel/vehiculos/scan.php`.

### 2.3 Código compartido

Ruta: `shared/`

- `app_config.php`: configuración central, roles, estados, monedas, seguridad HTTP, sesiones y valores por defecto.
- `comprobante_verificacion.php`: generación de número, token HMAC y URL pública de verificación de comprobantes.
- `db.php`: conexión PDO a MySQL/MariaDB usando variables de entorno.
- `vehiculo_logic.php`: lógica compartida para vehículos.

### 2.4 Storefront ecommerce

Ruta de código: `storefront/`
URL local Docker: `http://127.0.0.1:8082/`

El storefront usa Laravel, PHP-FPM y Nginx. Se conecta al panel mediante una
API privada HMAC bajo `/api/storefront/v1`. El pago online, los reembolsos
automáticos y los envíos están deshabilitados; la modalidad activa es retiro
coordinado en Belvedere.

### 2.5 Infraestructura

Archivos principales:

- `docker-compose.yml`: define servicios `web` y `panel`.
- `docker-compose.storefront.yml`: define `storefront_php`, `storefront_nginx` y `storefront_scheduler`.
- `Dockerfile.web`: imagen para web pública.
- `Dockerfile.panel`: imagen para panel.
- `docker/apache-web.conf`: configuración Apache de web pública.
- `docker/apache-panel.conf`: configuración Apache de panel.
- `docker/nginx-storefront.conf`: configuración Nginx del storefront.
- `.env.example`: contrato de variables de entorno.
- `backup_db.sh`: script de respaldo.
- `database/migrations/`: migraciones SQL incrementales.

## 3. Arquitectura de despliegue

El despliegue Docker contiene dos servicios Apache/PHP separados:

- `web`: expone `127.0.0.1:8080:80`, monta `public-web`, `shared` y las imágenes de vehículos.
- `panel`: expone `127.0.0.1:8081:80`, monta `lteco-panel`, `shared` y el directorio de backups.

Ambos servicios usan el mismo archivo `.env` y se conectan a base de datos mediante:

- `LTECO_DB_HOST`
- `LTECO_DB_NAME`
- `LTECO_DB_USER`
- `LTECO_DB_PASS`

En Docker, el host por defecto esperado para la base es `host.docker.internal`.

## 4. Variables de entorno

El archivo `.env` no debe versionarse ni compartirse. El contrato público está en `.env.example`.

Variables funcionales:

- `LTECO_ENV`: entorno (`production`, `local`, etc.).
- `LTECO_DEBUG`: activa o desactiva salida de errores.
- `LTECO_FORCE_HTTPS`: fuerza HTTPS en producción.
- `LTECO_PANEL_BASE_URL`: base interna del panel, por defecto `/lteco-panel`.
- `LTECO_PUBLIC_BASE_URL`: base pública legacy, por defecto `/public-web`.
- `LTECO_PANEL_PUBLIC_URL`: URL absoluta pública del panel si aplica.
- `LTECO_PUBLIC_URL`: URL absoluta pública de la web si aplica.
- `LTECO_APP_NAME`: nombre visible de la aplicación.

Variables comerciales:

- `LTECO_DEFAULT_EXCHANGE_RATE`: tipo de cambio USD por defecto.
- `LTECO_DEFAULT_IVA_RATE`: IVA por defecto.
- `LTECO_DEFAULT_WHATSAPP`: WhatsApp por defecto.
- `LTECO_DEFAULT_EMPRESA_RUT`: RUT por defecto.

Variables de sesión y seguridad:

- `LTECO_SESSION_IDLE_SECONDS`: tiempo máximo de inactividad.
- `LTECO_SESSION_REGENERATE_SECONDS`: frecuencia de regeneración de ID de sesión.
- `LTECO_COMPROBANTE_SECRET`: secreto para validar comprobantes.
- `LTECO_TURNSTILE_SITE_KEY`: clave pública Cloudflare Turnstile.
- `LTECO_TURNSTILE_SECRET_KEY`: clave privada Cloudflare Turnstile.
- `LTECO_TRUSTED_PROXY_RANGES`: proxies confiables para resolver IP real.
- `LTECO_MFA_ENCRYPTION_KEY`: clave de cifrado para MFA.

Variables de backup:

- `LTECO_BACKUP_DIR`: directorio de backups.
- `LTECO_DB_BACKUP_HOST`: host de base para respaldo.

## 5. Roles y permisos

Roles definidos:

- `Superadmin`
- `Administrador`
- `Vendedor`
- `Distribuidor`

### 5.1 Superadmin

Puede acceder a la operación completa y a administración avanzada. Tiene permisos exclusivos sobre:

- Auditoría del sistema.
- Mantenimiento y backups desde panel.
- Publicación web avanzada de vehículos: mostrar en web, destacado y orden.
- Edición completa de usuarios y roles.
- Eliminación de usuarios, excepto restricciones de seguridad sobre la propia cuenta y otros superadmins según flujo.

### 5.2 Administrador

Puede administrar la operación del negocio:

- Dashboard.
- Búsqueda.
- Vehículos.
- Repuestos.
- Clientes.
- Ventas.
- Nueva venta.
- Postventa.
- Gastos.
- Balance.
- Importaciones.
- Distribuidores.
- Usuarios, con alcance limitado.
- Configuración.

En usuarios, el administrador puede crear vendedores y distribuidores, cambiar claves y activar/desactivar vendedores según reglas de `includes/auth.php`.

### 5.3 Vendedor

Puede operar ventas y consultas básicas:

- Nueva venta.
- Ventas propias.
- Clientes propios.
- Buscador global.
- Servicios/postventa.
- Vehículos y repuestos necesarios para operar ventas.

No accede a administración financiera, usuarios, importaciones ni configuración.

El alcance de Vendedor es por ownership de registro:

- Venta propia: `venta.UsuarioVendedorId` coincide con el usuario actual.
- Cliente propio: existe una venta propia asociada a `cliente.IdCliente`.
- Postventa propia: service/garantía/vehículo asociado a una venta propia.
- Búsqueda: no muestra PII de clientes ajenos; si hay coincidencia ajena, devuelve un mensaje genérico.
- Nueva venta: el selector de cliente existente solo incluye clientes propios.
- Guardado de venta: un `cliente_id` ajeno enviado por POST manual se bloquea.
- Alta de cliente: teléfono/correo duplicado devuelve mensaje genérico para Vendedor.
- Exportaciones de ventas/clientes: no disponibles para Vendedor.

### 5.4 Distribuidor

Accede a un panel específico:

- Dashboard distribuidor.
- Nueva venta desde stock asignado.
- Ventas propias.
- Pedidos.
- Solicitar stock.
- Estado de cuenta.
- Catálogo mayorista de repuestos.

Las funciones usan `IdDistribuidor` asociado al usuario para limitar datos propios.

## 6. Seguridad

### 6.1 Sesiones

El sistema inicia sesión PHP con:

- Cookies `HttpOnly`.
- `SameSite=Lax`.
- `Secure` si la petición es HTTPS o si `LTECO_FORCE_HTTPS=1`.
- Regeneración periódica de ID.
- Expiración por inactividad.

### 6.2 Encabezados HTTP

`shared/app_config.php` configura:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- `Content-Security-Policy`
- `Strict-Transport-Security` cuando corresponde.

### 6.3 Login

El login:

- Usa hash de contraseña con `password_verify`.
- Aplica CSRF.
- Registra intentos en `login_attempts`.
- Bloquea temporalmente tras intentos fallidos.
- Puede validar Cloudflare Turnstile si está configurado.
- Registra auditoría para login exitoso, fallido, bloqueado y MFA requerido.

### 6.4 MFA

MFA está disponible para roles administrativos y es obligatorio para Superadmin y Administrador. Por decisión aceptada, Vendedor no requiere MFA por ahora; el control compensatorio es ownership por registro y 403 en acceso directo a IDs ajenos.

Campos involucrados:

- `usuario.mfa_enabled`
- `usuario.mfa_secret`
- `usuario.mfa_recovery_codes`

Pantallas:

- Verificación: `lteco-panel/mfa_verificar.php`.
- Administración por usuario: `lteco-panel/usuarios/mfa.php`.

### 6.5 CSRF

Los formularios sensibles usan `csrfInput()` y validación con `verifyCsrfOrFail()`.

### 6.6 Auditoría

La tabla `auditoria` registra:

- Usuario.
- Rol.
- Acción.
- Módulo.
- Detalle.
- JSON extra.
- IP.
- User agent.
- Fecha y hora.

El acceso a auditoría está restringido por rol.

## 7. Modelo de datos

Estado real relevado en base local:

- `usuario`: 5 registros.
- `producto`: 11 registros.
- `vehiculo`: 8 registros.
- `vehiculo_imagen`: 7 registros.
- `cliente`: 3 registros.
- `venta`: 36 registros.
- `venta_detalle`: 38 registros.
- `service_vehiculo`: 48 registros.
- `garantia`: 13 registros.
- `repuesto`: 3 registros.
- `gasto`: 2 registros.
- `importacion`: 2 registros.
- `distribuidor`: 1 registro.
- `distribuidor_stock`: 3 registros.
- `distribuidor_pedido`: 1 registro.
- `distribuidor_comision`: 0 registros.
- `auditoria`: 367 registros.
- `login_attempts`: 31 registros.

### 7.1 Tablas principales

`usuario`

- Identidad y acceso al panel.
- Campos clave: `IdUsuario`, `NombreCompleto`, `Usuario`, `ClaveHash`, `Rol`, `IdDistribuidor`, `Activo`, MFA.

`cliente`

- Datos comerciales y fiscales.
- Campos clave: `NombreApellido`, `TipoFiscal`, `Telefono`, `Correo`, `Cedula`, `Direccion`, `RUT`.

`producto`

- Entidad base para motos y repuestos.
- Campos clave: `Nombre`, `Slug`, `TipoProducto`, `Descripcion`, `Costo`, `GastoTotal`, `PrecioVenta`, `PrecioDistribuidor`, `Stock`, `Estado`, `MostrarEnWeb`, `DestacadoWeb`, `OrdenWeb`, `Moneda`.

`vehiculo`

- Datos específicos de motos.
- Relación 1 a 1 con `producto`.
- Campos clave: `IdVehiculo`, `IdProducto`, `NumeroMotor`, `Modelo`, `Color`, importación, reserva y venta.

`vehiculo_imagen`

- Imágenes asociadas a vehículos.
- Permite principalidad y orden.

`repuesto`

- Datos específicos de repuestos.
- Relación 1 a 1 con `producto`.

`venta`

- Cabecera comercial.
- Campos clave: cliente, fecha, método de pago, tarjeta, estado, tipo de cliente, distribuidor, vendedor, totales, IVA, descuentos, recargos, moneda, factura y anulación.

`venta_detalle`

- Líneas de venta.
- Relaciona venta con producto, cantidad, precio, costo, subtotal y ganancia.

`garantia`

- Garantía por vehículo, venta y cliente.
- Estados: `Vigente`, `Vencida`, `Anulada`.

`service_vehiculo`

- Services programados por vehículo.
- Estados: `Pendiente`, `Realizado`, `Vencido`, `Cancelado`.

`postventa_historial_tecnico`

- Registro técnico extendido de intervenciones.
- Estados: `Abierta`, `En reparación`, `En espera`, `Cerrada`, `Cancelada`.

`postventa_repuesto_usado`

- Repuestos consumidos en una intervención técnica.

`gasto`

- Egresos del negocio por concepto, categoría, método de pago y moneda.

`importacion`

- Número de importación y tipo de cambio asociado.

`distribuidor`

- Datos del distribuidor, comisión y estado.

`distribuidor_stock`

- Stock asignado a distribuidores para vehículos o repuestos.

`distribuidor_pedido`

- Solicitudes de stock.
- Estados: `Pendiente`, `Aprobado`, `Rechazado`.

`distribuidor_comision`

- Comisión generada por ventas de distribuidor.
- Estados: `Pendiente`, `Aprobada`, `Pagada`, `Anulada`.

`configuracion`

- Parámetros globales: empresa, contacto, tipo de cambio, comprobante, moneda, descuentos, recargos, comisión e IVA.

`empresa`

- Datos públicos/fiscales de la empresa.

`login_attempts`

- Registro de intentos de acceso y bloqueos.

`auditoria`

- Trazabilidad operativa.

## 8. Flujos funcionales

### 8.1 Acceso al panel

1. Usuario abre `/lteco-panel/login.php`.
2. Ingresa usuario y contraseña.
3. El sistema valida CSRF, bloqueo, Turnstile si aplica y credenciales.
4. Si el usuario administrativo tiene MFA activo, se redirige a verificación MFA.
5. Si el acceso es correcto:
   - Superadmin va al dashboard.
   - Administrador, vendedor y distribuidor van al inicio/lobby correspondiente.

### 8.2 Alta de vehículo

1. Administrador entra a `Vehículos > Nuevo vehículo`.
2. Carga identificación, motor, modelo, color, importación, costos, precios, notas y publicación.
3. El sistema crea un `producto` de tipo `Moto`.
4. Crea el registro específico en `vehiculo`.
5. Puede guardar imágenes asociadas en `vehiculo_imagen`.
6. Según configuración, puede quedar visible u oculto en web.

### 8.3 Publicación web

1. Superadmin define `MostrarEnWeb`.
2. Puede marcar `DestacadoWeb`.
3. Puede ajustar `OrdenWeb`.
4. La web pública consulta productos de tipo moto visibles y con estado publicable.
5. Las fotos se sirven desde `lteco-panel/uploads/vehiculos`.

### 8.4 Reserva de vehículo

1. Administrador selecciona un vehículo.
2. Ingresa cliente, fecha y seña.
3. El vehículo queda vinculado a `ClienteReservaId`, `FechaReserva` y `SeniaReserva`.
4. El estado operativo refleja la reserva.

### 8.5 Venta interna

1. Operador entra en `Nueva venta`.
2. Carga o selecciona cliente.
3. Agrega productos.
4. Define moneda, método de pago, tarjeta si corresponde, descuentos, recargos, pago y saldo.
5. El sistema crea `venta` y `venta_detalle`.
6. Actualiza stock/estado según producto.
7. Para motos, genera garantía y services programados cuando corresponde.
8. La venta queda disponible para detalle, comprobante y exportación.

### 8.6 Anulación de venta

1. Usuario administrador abre detalle de venta.
2. Indica motivo de anulación.
3. El sistema registra fecha, usuario y motivo.
4. Ajusta estado a `Anulada`.
5. No republica automáticamente vehículos en web; esa decisión queda para administración.

### 8.7 Postventa

1. Usuario entra en `Postventa`.
2. Revisa motos en seguimiento, garantías y services.
3. Puede abrir detalle por vehículo.
4. Registra diagnóstico, solución, técnico, estado, tiempos y observaciones.
5. Puede registrar repuestos usados.
6. Puede marcar services como realizados o cancelados.

### 8.8 Distribuidores

1. Administración crea distribuidor y usuario asociado.
2. Administración asigna stock.
3. Distribuidor ingresa a su panel.
4. Puede vender desde stock asignado.
5. Puede solicitar stock.
6. Administración revisa pedidos y los aprueba o rechaza.
7. El estado de cuenta muestra comisiones generadas y su estado.

### 8.9 Gastos y balance

1. Administración registra gastos por categoría, fecha, método, moneda y monto.
2. Balance combina ventas, gastos y métricas financieras.
3. Exportaciones permiten salida a CSV/Excel según módulo.

### 8.10 Verificación de comprobante

1. El comprobante incluye mecanismo de validación con secreto.
2. La web pública ofrece `verificar-comprobante.php`.
3. El sistema valida token HMAC, aplica headers `noindex`/`no-store` y limita solicitudes por IP.
4. El sistema indica si el comprobante es válido o no.

## 9. Estados y catálogos internos

Estados de motos:

- `Disponible`
- `Reservado`
- `Vendido`
- `Oculto`
- `Sin stock`

Estados de repuestos:

- `Disponible`
- `Sin stock`
- `Oculto`

Estados de venta:

- `Pendiente`
- `Confirmada`
- `Entregada`
- `Anulada`

Métodos de pago de venta:

- `Efectivo`
- `Transferencia`
- `Tarjeta`
- `Otro`

Tarjetas:

- Tipos: `Crédito`, `Débito`.
- Marcas: `Visa`, `Mastercard`.
- Cuotas: Visa 6; Mastercard 6 o 18.

Categorías de gasto:

- `Repuestos`
- `Mantenimiento`
- `Logística`
- `Publicidad`
- `Servicios`
- `Sueldos`
- `Transporte`
- `Otros`

Tipos de cliente:

- `Final`
- `Distribuidor`

Tipos fiscales:

- `Consumidor final`
- `Empresa/RUT`

## 10. Archivos públicos y carga de imágenes

Directorio de imágenes de vehículos:

- `lteco-panel/uploads/vehiculos/`

Protecciones:

- `lteco-panel/uploads/.htaccess`
- `lteco-panel/uploads/vehiculos/.htaccess`

La web pública monta el mismo directorio para mostrar fotos reales de catálogo.

## 11. Backups y mantenimiento

Backups desde panel:

- Ruta de módulo: `lteco-panel/configuracion/mantenimiento/`.
- Acceso: Superadmin.
- Funciones: listar backups, generar backup, descargar y restaurar.

Backups por script:

- `backup_db.sh`
- `lteco-panel/scripts/cleanup_test_data.php` para reset controlado de datos comerciales/de prueba.

Directorio configurable:

- `LTECO_BACKUP_DIR`

Recomendaciones:

- Guardar backups fuera del webroot.
- Verificar permisos de escritura del directorio.
- Probar restauración en entorno de staging antes de producción.
- Mantener copias externas.

### 11.1 Reset de datos comerciales de prueba

`lteco-panel/scripts/cleanup_test_data.php` es un script PHP CLI para resetear datos comerciales/de prueba del panel. No reinicia Docker, Apache ni los contenedores; solo opera sobre la base de datos MariaDB usando la configuración del panel.

Debe ejecutarse desde el contenedor `ltecobike_panel`:

```bash
# Dry-run obligatorio: no modifica la base.
docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php

# Ejecución real: destructiva y con confirmación explícita.
docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php --execute --confirm=RESET-LTECO-TEST-DATA
```

Comportamiento de seguridad:

- Corre en dry-run por defecto.
- En modo real exige `--execute --confirm=RESET-LTECO-TEST-DATA`.
- Antes de borrar crea un backup completo automático en `/opt/backups/ltecobike/`.
- Usa transacción para la limpieza principal.
- Muestra conteos antes, alcance del dry-run y conteos después cuando se ejecuta en modo real.

Datos que conserva:

- Usuarios.
- Vehículos.
- Productos.
- Repuestos.
- Configuración general.
- Empresa.
- Importaciones.

Datos que limpia:

- Ventas.
- Detalle de ventas.
- Clientes.
- Garantías asociadas a ventas.
- Services y postventa asociada a ventas.
- Gastos vinculados a ventas.
- Comisiones generadas por ventas.
- Notificaciones WhatsApp de ventas/services alcanzados.
- Pedidos/remitos de distribuidor.
- Stock asignado a distribuidores.
- Auditoría comercial de pruebas.

Ajustes de stock y estados:

- Reintegra stock de repuestos vendidos.
- Devuelve motos vendidas por ventas a `Disponible`, `Stock=1`, sin publicación web automática.
- Limpia `FechaVenta`, reserva, fecha de reserva y seña de la ficha del vehículo.
- Corrige casos legacy de motos marcadas como `Vendido` sin venta activa asociada.
- No toca repuestos fuera del stock vendido por ventas objetivo.

Riesgos y advertencias:

- Es destructivo. No usar sin revisar primero el dry-run.
- Borra clientes comerciales del entorno objetivo.
- El reset de datos no es lo mismo que reiniciar contenedores: `docker compose restart` no limpia la base y este script no reinicia servicios.
- Verificar que el entorno sea el correcto antes de ejecutar. No correr en producción real salvo decisión explícita y backup revisado.
- El backup queda en el host montado en `/opt/backups/ltecobike/`; comprobar espacio y permisos antes de usarlo.

Validaciones recomendadas después del reset real:

- `venta`, `venta_detalle`, `cliente`, `garantia` y `service_vehiculo` en cero si el objetivo era limpiar todo el flujo comercial.
- Motos disponibles con `producto.Estado='Disponible'` y `producto.Stock=1`.
- Repuestos conservados con stock reintegrado.
- Usuarios, configuración, empresa, importaciones, vehículos y repuestos presentes.
- No quedan motos legacy con `producto.Estado='Vendido'` sin venta activa.
- Revisar el dashboard y el listado de vehículos desde el panel.

## 12. Web pública

La web pública consume configuración y productos publicados.

Funciones:

- Mostrar modelos destacados.
- Mostrar catálogo completo.
- Mostrar detalle de modelo con fotos.
- Mostrar canales de contacto.
- Verificar comprobantes.
- Permitir scanner interno auxiliar.

El contenido comercial depende de:

- `producto.MostrarEnWeb`
- `producto.DestacadoWeb`
- `producto.OrdenWeb`
- `producto.Estado`
- `vehiculo_imagen`
- `empresa`
- `configuracion`

## 13. Operación diaria recomendada

1. Revisar dashboard al iniciar el día.
2. Confirmar stock de vehículos y repuestos.
3. Registrar ventas en el momento de la operación.
4. Revisar servicios pendientes o vencidos.
5. Actualizar gastos diariamente.
6. Revisar pedidos de distribuidores.
7. Controlar auditoría ante eventos sensibles.
8. Ejecutar backup antes de cambios importantes.

## 14. Riesgos y controles

Riesgos operativos:

- Ventas cargadas con moneda o tipo de cambio incorrecto.
- Vehículos vendidos que permanecen publicados.
- Repuestos usados en postventa sin stock actualizado.
- Usuarios administrativos sin MFA.
- Backups no probados.
- Tablas anchas de ventas/dashboard pueden recortarse horizontalmente en algunos viewports.
- QA local/headless puede quedar bloqueada por Turnstile si se intenta validar login real.

Controles recomendados:

- MFA obligatorio para Superadmin y Administrador.
- Ownership por registro para Vendedor en ventas, clientes, búsqueda y postventa.
- Revisión semanal de auditoría.
- Backup automático diario.
- Control de publicaciones web después de cada venta.
- Conciliación mensual de gastos, ventas y balance.

## 15. URLs internas principales

Panel:

- Login: `/lteco-panel/login.php`
- Inicio: `/lteco-panel/inicio.php`
- Dashboard: `/lteco-panel/dashboard.php`
- Vehículos: `/lteco-panel/vehiculos/index.php`
- Nueva venta: `/lteco-panel/ventas/crear.php`
- Ventas: `/lteco-panel/ventas/index.php`
- Clientes: `/lteco-panel/clientes/index.php`
- Postventa: `/lteco-panel/postventa/index.php`
- Repuestos: `/lteco-panel/repuestos/index.php`
- Gastos: `/lteco-panel/gastos/index.php`
- Balance: `/lteco-panel/balance/index.php`
- Distribuidores: `/lteco-panel/distribuidores/index.php`
- Usuarios: `/lteco-panel/usuarios/index.php`
- Configuración: `/lteco-panel/configuracion/index.php`
- Auditoría: `/lteco-panel/auditoria/index.php`

Web pública:

- Home: `/public-web/index.php`
- Catálogo: `/public-web/catalogo.php`
- Contacto: `/public-web/contacto.php`
- Verificación: `https://ltecobike.uy/verificar-comprobante.php`

Storefront:

- Catálogo: `/`
- Modelos: `/modelos`
- Carrito: `/carrito`
- Cuenta: `/cuenta`
- Compra: `/comprar`
- Agenda: `/agenda`

## 16. Estado de capturas y evidencias

Se generó un material visual con fotos reales ya cargadas en el sistema:

![Fotos reales de vehículos del catálogo](assets/fotos-reales-catalogo-ltecobike.png)

Nota técnica: en este entorno no hay navegador headless instalado (`Chromium`, `Firefox` o `Playwright`), por lo que no se pudieron tomar capturas automáticas navegadas desde la interfaz. Los servicios Docker sí están levantados y las URLs locales responden al panel/web.
