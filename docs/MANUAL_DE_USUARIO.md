# Manual de usuario LTeco ERP System

Versión: 2026-05-28  
Aplicación: Panel interno y web pública LTeco ERP System

## 1. Acceso

Ingresar al panel desde:

- Local: `http://127.0.0.1:8081/lteco-panel/login.php`
- Producción: usar la URL definida por la empresa.

Pasos:

1. Escribir usuario.
2. Escribir contraseña.
3. Presionar `Ingresar`.
4. Si el sistema solicita MFA, ingresar el código de la app autenticadora o un código de recuperación.

MFA es obligatorio para Administrador y Superadmin. Vendedor no requiere MFA por ahora, pero su cuenta solo puede ver registros propios.

Si aparecen errores de credenciales, esperar unos minutos antes de reintentar muchas veces. El sistema bloquea temporalmente accesos fallidos repetidos.

## 2. Pantalla de inicio

Después del login, el sistema muestra accesos según el rol:

- Superadmin: entra directo al dashboard.
- Administrador: ve accesos a venta, búsqueda, servicios, dashboard, vehículos, clientes, ventas, repuestos, gastos, balance, usuarios e importaciones.
- Vendedor: ve nueva venta, ventas propias, clientes propios, búsqueda limitada y postventa propia.
- Distribuidor: ve panel distribuidor, nueva venta y solicitud de stock.

Usar el menú lateral para moverse entre módulos. El botón `Salir` cierra la sesión.

## 3. Fotos reales del sistema

El sistema ya tiene imágenes reales cargadas para vehículos. Estas fotos son las mismas que se usan en el catálogo y en fichas publicables:

![Fotos reales de vehículos LTeco ERP System](assets/fotos-reales-catalogo-ltecobike.png)

Las imágenes originales están en:

- `lteco-panel/uploads/vehiculos/`

## 4. Dashboard

Disponible para Superadmin y Administrador.

Uso:

1. Entrar en `Dashboard`.
2. Revisar métricas generales del negocio.
3. Controlar últimas ventas.
4. Revisar clientes más activos.
5. Usar accesos rápidos para continuar operación.

Recomendación: revisar esta pantalla al comenzar el día.

## 5. Buscador global

Disponible para usuarios logueados.

Permite buscar:

- Clientes.
- Vehículos.
- Ventas y comprobantes.
- Repuestos.

Para Vendedor, la búsqueda no muestra datos de clientes ajenos. Si existe un cliente registrado que coincide con el término, el sistema muestra un mensaje genérico para solicitar ayuda a administración.

Uso:

1. Entrar en `Buscar`.
2. Escribir parte del nombre, documento, motor, ID, modelo o dato relacionado.
3. Revisar resultados agrupados.
4. Abrir el registro necesario.

## 6. Vehículos

Disponible para administración.

### 6.1 Ver listado

1. Entrar en `Vehículos`.
2. Usar filtros por estado, búsqueda u otros disponibles.
3. Revisar datos operativos: ID, modelo, color, motor, estado, precio y publicación.

### 6.2 Crear vehículo

1. Entrar en `Vehículos`.
2. Presionar `Nuevo vehículo`.
3. Completar identificación:
   - Modelo.
   - Color.
   - Número de motor.
   - Importación.
   - Fecha de ingreso.
4. Completar costos y precios.
5. Cargar notas internas si corresponde.
6. Si el usuario tiene permiso, configurar publicación web.
7. Guardar.

### 6.3 Editar vehículo

1. Entrar en el listado.
2. Abrir `Editar`.
3. Modificar datos.
4. Guardar cambios.

La pantalla de edición también permite revisar:

- QR interno.
- Garantía.
- Services programados.
- Imágenes actuales.
- Estado de publicación.

### 6.4 Publicar en web

Solo Superadmin puede gestionar publicación web avanzada.

Opciones:

- Mostrar u ocultar en web.
- Marcar destacado.
- Cambiar orden.
- Editar imágenes.

Después de vender una moto, revisar que no quede publicada por error.

### 6.5 Reservar vehículo

1. Abrir vehículo.
2. Seleccionar acción de reserva.
3. Asociar cliente.
4. Registrar seña si corresponde.
5. Confirmar.

## 7. Repuestos

### 7.1 Administración

Permite:

- Crear repuesto.
- Editar repuesto.
- Eliminar si corresponde.
- Controlar stock.
- Definir precio venta y precio distribuidor.
- Asociar importación.

### 7.2 Distribuidor

El distribuidor ve catálogo mayorista y puede preparar pedidos según disponibilidad.

## 8. Clientes

### 8.1 Listado

1. Entrar en `Clientes`.
2. Buscar por nombre, teléfono, correo, documento u otro dato.
3. Abrir detalle para ver historial.

### 8.2 Crear cliente

1. Presionar `Nuevo cliente`.
2. Completar nombre y datos de contacto.
3. Elegir tipo fiscal:
   - Consumidor final.
   - Empresa/RUT.
4. Completar cédula, RUT o dirección si aplica.
5. Guardar.

### 8.3 Editar cliente

1. Abrir cliente.
2. Presionar editar.
3. Actualizar datos.
4. Guardar.

Los productos comprados se administran desde ventas, no desde la ficha del cliente.

## 9. Ventas

### 9.1 Crear venta

1. Entrar en `Nueva venta`.
2. Completar datos del cliente o seleccionar uno existente.
3. Agregar productos.
4. Revisar cantidades y precios.
5. Configurar pago:
   - Efectivo.
   - Transferencia.
   - Tarjeta.
   - Otro.
6. Si es tarjeta, elegir tipo, marca y cuotas.
7. Revisar descuento, recargo, comisión, IVA y total.
8. Completar monto pagado y saldo pendiente si aplica.
9. Confirmar venta.

Al vender una moto, el sistema puede generar garantía y services programados.

### 9.2 Ver ventas

1. Entrar en `Ventas`.
2. Filtrar por fechas, cliente, estado u otros campos.
3. Abrir detalle para revisar comprobante, productos y datos financieros.

### 9.3 Anular venta

Acción disponible para administración.

1. Abrir detalle de venta.
2. Presionar opción de anulación.
3. Escribir motivo.
4. Confirmar.

La anulación queda registrada con usuario, fecha y motivo.

## 10. Comprobantes

Desde el detalle de venta se puede abrir el comprobante.

Uso recomendado:

1. Revisar que cliente, productos, total y pago estén correctos.
2. Entregar o enviar el comprobante al cliente.
3. Usar la verificación pública si se necesita validar autenticidad.

URL pública de verificación:

- `https://ltecobike.uy/verificar-comprobante.php`

## 11. Postventa

### 11.1 Listado

1. Entrar en `Postventa`.
2. Revisar motos en seguimiento.
3. Priorizar services vencidos o pendientes.

### 11.2 Detalle técnico

En detalle de postventa se ve:

- Datos del vehículo.
- Intervención técnica.
- Historial técnico extendido.
- Services.
- Observaciones.

### 11.3 Registrar intervención

1. Abrir detalle del vehículo.
2. Completar diagnóstico.
3. Completar solución aplicada si corresponde.
4. Definir técnico.
5. Elegir estado.
6. Registrar tiempos.
7. Agregar repuestos usados si aplica.
8. Guardar.

### 11.4 Services

Los services pueden estar:

- Pendientes.
- Realizados.
- Vencidos.
- Cancelados.

Registrar service realizado apenas se completa el trabajo.

## 12. Gastos

Disponible para administración.

### 12.1 Crear gasto

1. Entrar en `Gastos`.
2. Presionar `Nuevo gasto`.
3. Completar concepto.
4. Agregar descripción u observaciones.
5. Indicar monto, fecha, categoría, método de pago y moneda.
6. Guardar.

### 12.2 Exportar gastos

Usar `Exportar` desde el listado cuando se necesite análisis externo o respaldo administrativo.

## 13. Balance

Disponible para administración.

Permite revisar:

- Evolución de últimos meses.
- Gastos por categoría.
- Desglose por categoría.
- Gastos por método de pago.
- Últimos movimientos financieros.

Recomendación: revisar balance semanalmente y cerrar conciliaciones a fin de mes.

## 14. Importaciones

Disponible para administración.

Uso:

1. Entrar en `Importaciones`.
2. Crear nueva importación con número, fecha, tipo de cambio y descripción.
3. Asociar vehículos o repuestos al número de importación desde sus formularios.

Mantener actualizado el tipo de cambio usado para costeo.

## 15. Distribuidores

### 15.1 Administración de distribuidores

1. Entrar en `Distribuidores`.
2. Crear o editar distribuidor.
3. Definir contacto, teléfono, correo, comisión y estado.
4. Asignar usuario de rol distribuidor si corresponde.

### 15.2 Asignar stock

1. Abrir distribuidor.
2. Entrar en asignación de stock.
3. Elegir vehículo o repuesto.
4. Definir cantidad, precio de venta y precio mínimo.
5. Guardar.

### 15.3 Pedidos de stock

El distribuidor puede solicitar stock.

Administración:

1. Entrar en pedidos.
2. Revisar solicitudes pendientes.
3. Aprobar o rechazar.
4. Si se aprueba, controlar que el stock quede correctamente asignado.

### 15.4 Ventas de distribuidor

El distribuidor registra ventas desde su stock. La administración puede revisar ventas por distribuidor y estado de cuenta.

## 16. Usuarios

Disponible para Superadmin y Administrador con permisos distintos.

### 16.1 Crear usuario

1. Entrar en `Usuarios`.
2. Presionar `Nuevo usuario`.
3. Completar nombre, usuario, contraseña y rol.
4. Si es distribuidor, asociar distribuidor.
5. Guardar.

Reglas:

- Superadmin puede crear vendedor, administrador, superadmin y distribuidor.
- Administrador puede crear vendedor y distribuidor.
- Vendedor no gestiona usuarios.

### 16.2 Cambiar clave

1. En listado de usuarios, presionar acción de clave.
2. Escribir nueva contraseña.
3. Confirmar.

Contraseñas de Administrador y Superadmin deben tener mínimo 8 caracteres, mayúscula, minúscula y número.

### 16.3 Activar o desactivar

1. En listado de usuarios, usar acción de estado.
2. Confirmar.

No desactivar la propia cuenta administrativa en uso.

### 16.4 MFA

Para usuarios administrativos:

1. Abrir acción MFA.
2. Activar MFA.
3. Escanear o cargar el secreto en una app autenticadora.
4. Guardar códigos de recuperación.

Vendedor no requiere MFA por decisión operativa actual. No compartir usuarios: el sistema usa la cuenta del vendedor para limitar ventas, clientes y postventa propios.

## 17. Configuración

Disponible para administración.

Permite definir:

- Nombre de empresa.
- Correo.
- Teléfono.
- WhatsApp.
- Redes.
- Logo.
- Tipo de cambio USD.
- Texto de comprobante.
- Moneda principal.
- Descuento contado.
- Recargo tarjeta.
- Comisión distribuidor.
- Tasa IVA.

Cambiar estos valores afecta cálculos y visualización del sistema.

## 18. Mantenimiento

Disponible para Superadmin.

Funciones:

- Generar backup.
- Descargar backup.
- Restaurar backup.

Antes de restaurar:

1. Confirmar que el backup corresponde al entorno correcto.
2. Hacer un backup nuevo del estado actual.
3. Evitar restaurar durante horario operativo.

Reset de datos comerciales de prueba:

- No es una función normal del panel y no reinicia Docker ni el servidor.
- Lo ejecuta un sysadmin/desarrollador por CLI con `lteco-panel/scripts/cleanup_test_data.php`.
- Primero se corre dry-run: `docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php`.
- La ejecución real es destructiva y exige `--execute --confirm=RESET-LTECO-TEST-DATA`.
- Antes de borrar crea backup automático en `/opt/backups/ltecobike/`.
- Conserva usuarios, vehículos, productos, repuestos, configuración, empresa e importaciones.
- Limpia ventas, clientes, garantías, services/postventa, gastos/comisiones, pedidos/remitos de distribuidor, stock asignado y auditoría comercial.

## 19. Auditoría

Disponible para Superadmin.

Permite filtrar y revisar:

- Acciones.
- Módulos.
- Usuarios.
- Fechas.
- IP.
- Datos adicionales.

Usar auditoría para revisar:

- Logins fallidos.
- Cambios de usuario.
- Anulaciones.
- Operaciones sensibles.
- Problemas de seguridad.

## 20. Web pública

La web pública muestra modelos y datos configurados desde el panel.

Uso:

1. Publicar vehículo desde panel.
2. Cargar fotos reales.
3. Revisar catálogo público.
4. Revisar detalle del modelo.
5. Confirmar canales de contacto.

URLs locales de la web legacy:

- Home: `http://127.0.0.1:8080/public-web/index.php`
- Catálogo: `http://127.0.0.1:8080/public-web/catalogo.php`
- Contacto: `http://127.0.0.1:8080/public-web/contacto.php`

El ecommerce actual se sirve desde el storefront Laravel en
`http://127.0.0.1:8082/`. Sus rutas principales son `/modelos`, `/carrito`,
`/cuenta`, `/comprar` y `/agenda`. La operación vigente es retiro coordinado en
Belvedere; pagos online, reembolsos y envíos permanecen deshabilitados.

## 21. Buenas prácticas

- Cerrar sesión al terminar.
- No compartir usuarios.
- Activar MFA en cuentas administrativas.
- Cargar ventas en el momento.
- Revisar publicación web después de vender o reservar vehículos.
- Registrar gastos con fecha correcta.
- Mantener fotos claras y reales para catálogo.
- Revisar services pendientes todos los días.
- Hacer backup antes de cambios grandes.
- No guardar contraseñas en documentos ni chats.

## 22. Problemas frecuentes

No puedo ingresar:

- Revisar usuario y contraseña.
- Esperar si hubo muchos intentos fallidos.
- Solicitar cambio de clave a administración.

No veo un módulo:

- El rol puede no tener permisos.
- Consultar con Superadmin o Administrador.

Una moto no aparece en la web:

- Revisar `Mostrar en web`.
- Revisar estado del producto.
- Revisar imágenes.
- Revisar orden/destacado.

El total de venta no coincide:

- Revisar moneda.
- Revisar tipo de cambio.
- Revisar descuentos, recargos, IVA y comisiones.
- Revisar cantidades.

Un distribuidor no ve stock:

- Revisar que el usuario tenga `IdDistribuidor`.
- Revisar stock asignado.
- Revisar estado del item.

## 23. Capturas recomendadas para completar el manual visual

En este entorno no hay navegador headless disponible para generar capturas automáticas de pantallas. Para completar una versión PDF con capturas navegadas, tomar imágenes de estas pantallas:

- Login.
- Inicio/lobby.
- Dashboard.
- Vehículos.
- Editar vehículo con imágenes.
- Nueva venta.
- Detalle de venta/comprobante.
- Postventa.
- Distribuidores.
- Configuración.
- Web pública catálogo.

Las fotos reales de productos ya están incluidas arriba y salen de archivos cargados por el propio sistema.
