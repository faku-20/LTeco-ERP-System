# Guía de usuario — Panel LTeco ERP System

Panel interno de gestión comercial para movilidad eléctrica.  
URL del panel: `http://127.0.0.1:8081/lteco-panel/` (local) o la URL de producción configurada.

La tienda pública nueva está en `http://127.0.0.1:8082/` en local. Esta guía
describe principalmente el panel interno; para catálogo, reservas, cuenta y
pedidos web consultar `storefront/README.md` y `docs/ECOMMERCE_OPERACION.md`.

---

## Acceso

### Ingresar al panel

1. Abrir la URL del panel en el navegador.
2. Escribir usuario y contraseña.
3. Presionar **Ingresar**.
4. Si el sistema pide código MFA, ingresar el código de 6 dígitos de la app autenticadora (Google Authenticator, Authy, etc.) o uno de los códigos de recuperación.

MFA es obligatorio para Administrador y Superadmin. Vendedor no requiere MFA por decisión operativa actual, pero solo puede ver registros propios.

**Si no podés ingresar:**
- Verificar que el usuario y la contraseña sean correctos.
- Si hubo varios intentos fallidos, el sistema bloquea temporalmente. Esperar unos minutos.
- Si olvidaste la contraseña, pedirle a un Administrador o Superadmin que la cambie desde el módulo Usuarios.

### Cerrar sesión

Presionar **Salir** en el encabezado o el sidebar. La sesión también se cierra sola después de 2 horas de inactividad.

---

## Pantalla de inicio

Después del login, el sistema muestra la pantalla de inicio con accesos según tu rol:

| Rol | Pantalla post-login | Módulos disponibles |
|-----|--------------------|--------------------|
| Superadmin | Dashboard directo | Todo |
| Administrador | Lobby con accesos rápidos | Todo excepto Auditoría y Mantenimiento |
| Vendedor | Lobby con accesos rápidos | Nueva venta, Ventas propias, Clientes propios, Búsqueda limitada, Postventa propia, Vehículos/Repuestos para operar |
| Distribuidor | Lobby del distribuidor | Panel propio, Nueva venta (stock asignado), Pedidos |

---

## Dashboard

> Disponible para: Superadmin, Administrador

Muestra el estado general del negocio:
- **Ventas activas** — ventas confirmadas (excluye anuladas)
- **Motos disponibles** — stock actual de vehículos
- **Repuestos** — cantidad total y alertas de stock bajo
- **Últimas ventas** — las más recientes con link al detalle
- **Clientes más activos** — por cantidad de compras

Revisarlo al inicio de cada día de trabajo.

---

## Búsqueda global

> Disponible para: todos los roles (excepto Distribuidor)

Permite buscar por nombre, motor, factura, ID de venta o teléfono en todos los módulos a la vez.

Para Vendedor, la búsqueda está limitada:
- muestra ventas propias;
- muestra clientes propios;
- mantiene vehículos y repuestos disponibles para operar;
- no revela datos de clientes ajenos. Si coincide un cliente no propio, muestra un aviso genérico para solicitar ayuda a administración.

**Uso:**
1. Ir a **Buscar** desde el inicio o el sidebar.
2. Escribir el término en el campo de búsqueda.
3. Presionar **Buscar** o Enter.
4. Los resultados aparecen agrupados por tipo (clientes, ventas, vehículos).

---

## Vehículos

> Disponible para: Superadmin, Administrador

### Ver el inventario

1. Ir a **Vehículos**.
2. El listado muestra: ID, modelo, color, número de motor, estado, precio y si está publicado en web.
3. Usar los filtros por estado (Disponible, Reservado, Vendido, Oculto) o buscar por texto.

### Agregar un vehículo nuevo

1. Presionar **+ Nuevo vehículo**.
2. Completar:
   - **Modelo** — nombre del modelo
   - **Color** — color de la unidad
   - **Número de motor** — identificador único físico
   - **Importación** — número de importación al que pertenece
   - **Costo en USD** y **precio de venta**
3. Cargar imágenes si están disponibles.
4. Presionar **Guardar**.

El vehículo queda en estado **Disponible** por defecto.

### Editar un vehículo

1. En el listado, presionar **Editar** en la fila del vehículo.
2. Modificar los campos necesarios.
3. Guardar.

Desde la pantalla de edición también se puede ver:
- QR interno del vehículo
- Garantía vigente
- Services programados
- Imágenes actuales
- Estado de publicación web

### Publicar en la web pública

> Solo Superadmin puede controlar la publicación web.

1. Abrir el vehículo.
2. Activar **Mostrar en web**.
3. Opcionalmente marcar **Destacado** y ajustar el **Orden**.

Después de vender una moto, verificar que no quede publicada por error.

### Reservar un vehículo

1. Abrir el vehículo.
2. Presionar la opción de reserva.
3. Asociar un cliente existente o crear uno nuevo.
4. Registrar la seña si corresponde.
5. Confirmar.

El vehículo queda en estado **Reservado** y solo puede venderse al cliente de la reserva.

### Estados posibles de un vehículo

| Estado | Descripción |
|--------|-------------|
| Disponible | En stock, listo para vender |
| Reservado | Tiene seña, esperando concretar la venta |
| Vendido | Venta confirmada |
| Oculto | En stock pero no visible en operaciones normales |
| Sin stock | No disponible |

---

## Repuestos

> Disponible para: Superadmin, Administrador

### Ver catálogo

1. Ir a **Repuestos**.
2. El listado muestra todos los repuestos con stock, precios y estado.

### Agregar un repuesto

1. Presionar **+ Nuevo repuesto**.
2. Completar nombre, descripción, stock, costo y precio de venta.
3. Definir precio para distribuidores si aplica.
4. Guardar.

### Controlar stock

El stock se actualiza automáticamente cuando se registra un repuesto usado en postventa. Para ajustes manuales, editar el repuesto directamente.

---

## Clientes

> Disponible para: Superadmin, Administrador, Vendedor con cartera propia

Admin/Superadmin ven todos los clientes. Vendedor solo ve clientes vinculados a ventas propias.

### Buscar un cliente

1. Ir a **Clientes**.
2. Usar el campo de búsqueda: nombre, teléfono, correo o cédula.
3. Presionar **Aplicar**.

### Crear un cliente nuevo

1. Presionar **+ Nuevo cliente**.
2. Completar:
   - **Nombre y apellido**
   - **Teléfono** y/o **correo**
   - **Tipo fiscal**: Consumidor final o Empresa/RUT
   - **Cédula** o **RUT** según corresponda
3. Guardar.

También se puede crear un cliente directamente desde el formulario de nueva venta. Si un Vendedor intenta crear un cliente con teléfono o correo ya registrado, el sistema muestra un mensaje genérico y no revela si el dato corresponde a un cliente propio o ajeno.

### Ver historial de un cliente

Abrir la ficha del cliente para ver:
- Datos de contacto
- Total de compras y monto gastado
- Saldo pendiente
- Ventas realizadas

---

## Ventas

> Nueva venta disponible para: Superadmin, Administrador, Vendedor  
> Listado y detalle disponible para: Superadmin, Administrador y Vendedor con ventas propias

### Registrar una venta nueva

1. Ir a **Nueva venta** (desde el inicio o el sidebar).
2. **Cliente:** seleccionar un cliente existente o completar los datos para crearlo al vuelo. Para Vendedor, la lista de clientes existentes muestra solo clientes propios y no expone datos de la cartera completa.
3. **Productos:** agregar motos o repuestos usando el buscador de productos.
4. **Pago:** elegir método:
   - **Efectivo** — ingresar monto recibido y saldo pendiente si aplica
   - **Transferencia** — registrar referencia si corresponde
   - **Tarjeta** — elegir tipo (Crédito/Débito), marca (Visa/Mastercard) y cuotas
   - **Otro**
5. Revisar el resumen: descuento, recargo de tarjeta, IVA, total y ganancia.
6. Presionar **Confirmar venta**.

Al vender una moto, el sistema genera automáticamente:
- Garantía de 1 año (o según configuración)
- Services programados (1er, 2do, 3er service)

### Ver el historial de ventas

1. Ir a **Ventas**.
2. Filtrar por fecha, cliente, estado, método de pago u otros campos.
3. Presionar **Filtrar**.
4. Abrir cualquier venta para ver el detalle completo.

Vendedor solo puede listar y abrir ventas propias. Si intenta abrir una venta ajena por URL directa, el sistema muestra acceso denegado.

### Anular una venta

> Solo Administrador y Superadmin

1. Abrir el detalle de la venta.
2. Presionar la opción de anulación.
3. Escribir el motivo.
4. Confirmar.

La anulación queda registrada con usuario, fecha y motivo. El vehículo **no** vuelve automáticamente a Disponible — verificar y cambiar el estado manualmente si corresponde.

### Comprobante

Desde el detalle de venta, presionar **Ver comprobante** para abrir la versión imprimible. El comprobante incluye un código de verificación que el cliente puede validar en la web pública.

---

## Postventa

> Disponible para: Superadmin, Administrador, Vendedor

Seguimiento de motos vendidas, garantías y services programados.

Admin/Superadmin ven toda la postventa. Vendedor solo ve y opera postventa asociada a ventas propias.

### Ver motos en seguimiento

1. Ir a **Postventa**.
2. El panel muestra:
   - **Services vencidos** — a atender con urgencia
   - **Próximos 7 días** — services que vencen pronto
   - **Garantías vigentes** — motos con garantía activa
   - **Vehículos en seguimiento** — total bajo seguimiento

### Abrir el detalle de un vehículo

1. En el listado, presionar **Ver** en la fila del vehículo.
2. Ver: datos de la moto, cliente, número de venta, estado de garantía y services.

### Registrar una intervención técnica

1. En el detalle del vehículo, completar la sección de intervención:
   - **Diagnóstico** — descripción del problema
   - **Solución aplicada**
   - **Técnico responsable**
   - **Estado** — Abierta / En reparación / En espera / Cerrada / Cancelada
   - **Tiempos** (opcional)
2. Si se usaron repuestos, agregarlos en la sección correspondiente.
3. Guardar.

### Marcar un service como realizado

1. En el listado de services del vehículo, presionar la acción de completar.
2. Confirmar.

El sistema actualiza el próximo service automáticamente.

---

## Gastos

> Disponible para: Superadmin, Administrador

Registro de egresos del negocio.

### Registrar un gasto

1. Ir a **Gastos**.
2. Presionar **+ Nuevo gasto**.
3. Completar:
   - **Concepto** — descripción del gasto
   - **Categoría** — Repuestos, Mantenimiento, Logística, Publicidad, Servicios, Sueldos, Transporte, Comisiones, Otros
   - **Método de pago** — Efectivo, Tarjeta, Transferencia, Otro
   - **Moneda** y **monto**
   - **Fecha**
4. Guardar.

### Exportar gastos

Usar **Exportar CSV** desde el listado para bajar un archivo compatible con Excel o análisis externo.

---

## Balance

> Disponible para: Superadmin, Administrador

Vista financiera del negocio.

Muestra:
- **Ingresos facturados** — ventas confirmadas en UYU
- **IVA estimado** — 22% sobre el bruto
- **Ingresos netos** — facturado menos IVA
- **Gastos reales** — total de gastos registrados
- **Evolución de los últimos 6 meses** — gráfico comparativo
- **Gastos por categoría** — gráfico y tabla desglosada
- **Últimos movimientos financieros** — ventas y gastos recientes

Usar los filtros de fecha para analizar períodos específicos.

Revisar el balance semanalmente y hacer cierre de mes al final de cada período.

---

## Importaciones

> Disponible para: Superadmin, Administrador

Registra cada contenedor o lote de vehículos importados y el tipo de cambio USD usado para costeo.

### Crear una importación

1. Ir a **Importaciones**.
2. Presionar **+ Nueva importación**.
3. Completar: número de importación, descripción, tipo de cambio USD y fecha.
4. Guardar.

Al crear un vehículo o repuesto, asociarlo a la importación correspondiente para que el costo quede registrado con el tipo de cambio correcto.

El sistema usa el tipo de cambio de la importación activa más reciente para todos los cálculos. Si hay un cambio de tipo de cambio, crear una nueva importación antes de cargar los nuevos vehículos.

---

## Distribuidores

> Administración disponible para: Superadmin, Administrador  
> Panel propio para: rol Distribuidor

### Gestionar distribuidores

1. Ir a **Distribuidores**.
2. Ver lista de distribuidores activos con comisión y stock asignado.
3. Presionar **Editar** para modificar datos o comisión.

### Asignar stock a un distribuidor

1. En la fila del distribuidor, presionar **Asignar stock**.
2. Buscar el vehículo o repuesto.
3. Definir cantidad, precio de venta y precio mínimo.
4. Guardar.

El distribuidor ve este stock en su panel para venderlo.

### Aprobar pedidos de stock

1. Ir a **Pedidos** (botón en la parte superior de Distribuidores).
2. Ver solicitudes pendientes con detalle de qué pide cada distribuidor.
3. Presionar **Aprobar** o **Rechazar**.
4. Si se aprueba, verificar que el stock quede correctamente asignado.

### Ver ventas y estado de cuenta de un distribuidor

- **Ventas**: historial de ventas realizadas por distribuidores
- **Estado de cuenta**: comisiones generadas, pagadas y pendientes

---

## Usuarios

> Superadmin: gestión completa  
> Administrador: puede crear Vendedores y Distribuidores, cambiar claves, activar/desactivar

### Crear un usuario

1. Ir a **Usuarios**.
2. Presionar **+ Nuevo usuario**.
3. Completar nombre, usuario, contraseña y rol.
4. Si es Distribuidor, seleccionar el distribuidor asociado.
5. Guardar.

**Reglas por rol:**
- Superadmin puede crear cualquier rol
- Administrador puede crear Vendedor y Distribuidor únicamente
- Vendedor no gestiona usuarios

### Cambiar contraseña de un usuario

1. En el listado, presionar la acción de cambio de clave.
2. Ingresar la nueva contraseña (mínimo 8 caracteres, mayúscula, minúscula y número para roles administrativos).
3. Confirmar.

### Activar o desactivar un usuario

1. En el listado, usar el toggle de estado.
2. Confirmar.

No desactivar tu propia cuenta mientras está en uso.

### Configurar MFA (autenticación de dos factores)

> Obligatorio para Administrador y Superadmin

1. En el listado de usuarios, presionar la acción MFA del usuario correspondiente.
2. Activar MFA.
3. Escanear el código QR con Google Authenticator, Authy u otra app compatible.
4. Ingresar el código para confirmar que la configuración funcionó.
5. Guardar y anotar los códigos de recuperación en un lugar seguro.

Vendedor no requiere MFA por ahora. No compensar esa decisión compartiendo usuarios: cada vendedor debe usar su propia cuenta para que el ownership por registro funcione correctamente.

---

## Configuración

> Disponible para: Superadmin, Administrador

Permite ajustar parámetros del sistema:
- Nombre de la empresa, correo, teléfono, WhatsApp y redes sociales
- Tipo de cambio USD (fallback cuando no hay importación reciente)
- Texto de pie de comprobante
- Moneda principal, IVA, descuento contado, recargo por tarjeta
- Comisión de distribuidor

Cambiar estos valores afecta el comportamiento de todo el sistema. Hacerlo con cuidado.

---

## Mantenimiento y backups

> Solo Superadmin

Desde **Configuración > Mantenimiento**:
- **Generar backup** — crea una copia de la base de datos
- **Descargar backup** — descarga el archivo al navegador
- **Restaurar backup** — reemplaza la base con un backup anterior

**Antes de restaurar:**
1. Hacer un backup del estado actual primero.
2. Confirmar que el archivo es del entorno correcto (no restaurar un backup de producción en otro sistema sin revisar).
3. Evitar restaurar en horario operativo.

### Reset de datos de prueba

El reset de datos comerciales de prueba no se hace desde el panel y no equivale a reiniciar Docker. Es una tarea de sysadmin/desarrollador mediante el script CLI `lteco-panel/scripts/cleanup_test_data.php`.

Usar siempre primero el dry-run:

```bash
docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php
```

La ejecución real es destructiva y exige:

```bash
docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php --execute --confirm=RESET-LTECO-TEST-DATA
```

El script crea backup automático en `/opt/backups/ltecobike/`, conserva usuarios/vehículos/productos/repuestos/configuración y limpia ventas, clientes, garantías, services/postventa, gastos/comisiones comerciales, pedidos/remitos de distribuidor, stock asignado y auditoría comercial. Después del reset, revisar que las motos estén disponibles con stock y que los usuarios sigan activos.

---

## Auditoría

> Solo Superadmin

Registro completo de operaciones sensibles del sistema.

1. Ir a **Auditoría**.
2. Filtrar por acción, módulo, usuario o rango de fechas.
3. Revisar los registros.

Cada entrada muestra: quién hizo qué, en qué módulo, desde qué IP y en qué momento.

**Revisar regularmente para detectar:**
- Logins fallidos repetidos (posible ataque de fuerza bruta)
- Anulaciones de venta inesperadas
- Cambios de usuarios o contraseñas
- Operaciones fuera del horario habitual

---

## Web pública

La web pública (`/public-web/`) muestra el catálogo de motos disponibles y permite verificar comprobantes.

### Publicar una moto en la web

1. Abrir el vehículo en el panel.
2. Activar **Mostrar en web** (solo Superadmin).
3. Cargar fotos reales del vehículo.
4. Opcionalmente marcar como **Destacado** y definir **Orden** de aparición.

### Verificar un comprobante

Los clientes pueden verificar la autenticidad de su comprobante entrando a la URL de verificación pública e ingresando el número de comprobante. El sistema valida que el comprobante fue generado por el sistema y no fue alterado.

---

## Buenas prácticas operativas

1. **Cerrar sesión** al terminar de trabajar, especialmente en equipos compartidos.
2. **No compartir usuario y contraseña** con otros. Cada operador debe tener su propio usuario.
3. **MFA activo** en todas las cuentas Administrador y Superadmin sin excepción.
4. **Cargar ventas en el momento** — no dejar para después para evitar errores de tipo de cambio o stock.
5. **Revisar la publicación web** después de cada venta o reserva de vehículo.
6. **Registrar gastos con fecha correcta** para que el balance mensual sea preciso.
7. **Revisar services pendientes** todos los días desde el módulo Postventa.
8. **Backup antes de cambios grandes** — restaurar la base sin backup es imposible.
9. **Guardar los códigos de recuperación MFA** en un lugar seguro (no en el mismo dispositivo que la app).

---

## Problemas frecuentes

### No puedo ingresar al panel
- Verificar usuario y contraseña.
- Si hubo múltiples intentos fallidos, esperar unos minutos (el sistema bloquea temporalmente).
- Pedir cambio de clave a un Administrador o Superadmin.

### No aparece un módulo en el menú
- El rol del usuario puede no tener acceso. Consultar con Superadmin.
- Si el módulo debería aparecer, pedir que revisen los permisos del usuario en el módulo Usuarios.

### Una moto no aparece en la web pública
- Verificar que tenga **Mostrar en web** activo.
- Verificar el estado del vehículo (no debe ser Vendido, Oculto ni Sin stock).
- Verificar que tenga al menos una imagen.
- Verificar el campo **Orden** si hay muchas motos y se perdió de vista.

### El total de una venta no coincide con lo esperado
- Verificar la **moneda** seleccionada (USD vs UYU).
- Verificar el **tipo de cambio** activo en el momento de la venta.
- Revisar descuento, recargo de tarjeta, IVA y cantidad de productos.

### Un distribuidor no ve su stock asignado
- Verificar que el usuario tenga el `IdDistribuidor` correcto (en el módulo Usuarios).
- Verificar que el stock esté asignado al distribuidor correcto (en el módulo Distribuidores).
- Verificar que el ítem no esté en estado Oculto o Sin stock.

### Se perdió el código MFA
- Usar uno de los **códigos de recuperación** guardados al activar MFA.
- Si tampoco están disponibles, un Superadmin puede desactivar MFA del usuario desde el módulo Usuarios y volver a configurarlo.
