# Integracion IA/WhatsApp en V2

Fecha: 2026-07-09

## Alcance

- V2 queda como base productiva.
- Se portaron funciones utiles de IA/WhatsApp desde V3 a V2.
- Se porto n8n como parte de Automatizaciones.
- No se portaron mantenimiento ni seguridad.
- No se modificaron ventas existentes ni se recalcularon datos comerciales.

## Backup

- Durante la implementación se generaron backups previos a cada cambio sensible.
- Esos respaldos intermedios se retiraron en la limpieza del 2026-07-11, después del cierre y la validación del backup final de producción.
- El respaldo vigente está documentado en `docs/CIERRE_V2_PRODUCCION.md`.

## Modulos agregados

- `Automatizaciones`: hub con cards internas.
- `Comercial`: hub con búsqueda, WhatsApp, agenda y alertas.
- `Stock`: hub con vehículos, repuestos e importaciones.
- `Ventas`: hub con nueva venta, ventas, postventa y clientes.
- `Administración`: hub con balance, gastos, distribuidores, usuarios/configuración y auditoría para superadmin.
- `WhatsApp`: bandeja de conversaciones guardadas, clasificacion IA y respuesta desde panel.
- `Asistente IA`: consulta manual con contexto real del panel.
- `IA acciones`: acciones sugeridas desde conversaciones guardadas.
- `IA acciones` permite exportar a CSV solo los teléfonos visibles según Estado/Tipo, normalizados, sin encabezados ni duplicados.
- `Agenda`: visitas showroom confirmadas por conversación, con estado y responsable.
- `Alertas`: avisos internos de visitas agendadas para administradores y vendedores.
- `Base comercial IA`: tono, reglas comerciales, modelos y prohibidos editables desde panel.
- `n8n`: configuracion de webhooks salientes, logs y API interna protegida por token.

## Navegacion sin duplicados

- `Comercial`: Buscar, WhatsApp, Agenda y Alertas.
- `Stock`: Vehiculos, Repuestos e Importaciones.
- `Ventas`: Nueva venta, Ventas, Clientes y Postventa.
- `Administracion`: Balance, Gastos, Distribuidores, Usuarios, Configuracion y Auditoria segun rol.
- `Automatizaciones`: Asistente IA, IA acciones, Base comercial IA y n8n.
- WhatsApp ya no aparece en Automatizaciones; Asistente IA ya no aparece en Comercial; Clientes pertenece solo a Ventas; Alertas ya no aparece en Automatizaciones.
- El inicio de Administrador se redujo a las cinco secciones principales, evitando repetir todos los modulos internos como cards sueltas.
- Se corrigio el bloqueo contra doble envio para formularios con varios botones: Alertas conserva `estado=leida|cerrada` e IA acciones conserva `accion=confirmar|ejecutar|rechazar` antes de deshabilitar los controles.
- Los hubs y las cinco secciones del inicio de Administrador usan cards neutrales consistentes; se retiro el fondo, borde y estado tactil verde de las cards de navegacion.
- Se corrigio el shell mobile: los contenidos ya no heredan `grid-column: 2` al pasar a una sola columna, eliminando la franja lateral vacia que desplazaba especialmente Comercial y Stock.

## Tablas nuevas

- `ai_instruction_entry`
- `commercial_lead`
- `commercial_inbox_message`
- `ia_accion_sugerida`
- `ai_usage_log`
- `n8n_webhook_setting`
- `n8n_webhook_log`
- `automation_event`
- `crm_visita`
- `internal_alert`

Todas son aditivas y no reemplazan tablas existentes de ventas, clientes, vehiculos ni gastos.

## Variables

- Se copiaron variables `LTECO_AI_*` desde V3 a `.env` de V2 sin imprimir secretos.
- `LTECO_AI_CLASSIFY_ON_WEBHOOK=1`: el webhook intenta clasificar mensajes entrantes guardados.
- `LTECO_AI_AUTO_REPLY_ENABLED=1`: el webhook responde solamente el primer mensaje entrante del telefono. La secuencia es saludo, fotos/fichas y cierre; el cierre espera la confirmacion de entrega de la ultima imagen para no adelantarse. Desde el segundo mensaje no envia respuestas automaticas y la conversacion queda a cargo del equipo humano.
- `LTECO_META_APP_SECRET`: valida `X-Hub-Signature-256` antes de procesar payloads de Meta. El secreto se migro sin imprimirlo ni versionarlo.
- Se copio `LTECO_N8N_WEBHOOK_TOKEN` desde V3 a V2 sin imprimir el secreto.

## Flujo WhatsApp

- Meta entrega directamente en `https://panel.ltecobike.shop/lteco-panel/whatsapp_webhook.php`.
- El webhook sigue registrando estados de mensajes salientes.
- Ahora tambien guarda mensajes entrantes reales en `commercial_inbox_message`.
- Los mensajes que un humano envia fuera del panel solo forman parte del contexto si Meta los entrega al webhook; si no llegan, la IA no puede reconstruir la propuesta previa de fecha u hora.
- Si la IA esta configurada, clasifica el mensaje y guarda resumen/sugerencia.
- Si el primer mensaje consulta por modelos, envia el texto, las tres imagenes de modelos y el cierre configurados en V3.
- Si detecta `Q8 350W`, `Q8 500W` o `LY 500W`, envia la ficha acotada del modelo sin inventar precios, stock o promociones.
- Si un contacto que ya recibio el saludo inicial elige luego uno de esos modelos, envia la ficha y el cierre de showroom una sola vez por telefono/modelo.
- Si el contacto usa Responder sobre una ficha o imagen y escribe `me interesa esta`, se toma `context.id` del payload de Meta, se resuelve el modelo del mensaje citado y se guarda en `ReplyToModelo` para clasificacion, UI y auto-respuesta.
- La bienvenida automatica solo se intenta sobre el primer inbound guardado y se omite ante continuaciones claras (`ok`, `si`, `dale`, mensajes citados o multimedia sin texto), evitando intervenir en una conversacion humana ya iniciada.
- Cada envio guarda inmediatamente el `WaMessageId` devuelto por Meta; no depende de esperar el callback de entrega para resolver una respuesta citada.
- Para otros primeros mensajes, acusa recibo y deriva a un miembro del equipo.
- Las imagenes se sirven desde rutas publicas de `ltecobike.uy`; la respuesta ya no depende de `v3.ltecobike.shop`.
- Todo POST sin firma Meta valida se rechaza con HTTP 403 antes de decodificar o guardar datos.

## Agenda IA

- El webhook analiza el historial reciente de la conversación después de guardar cada mensaje entrante.
- Agenda automáticamente cuando existe intención de showroom y fecha clara; la hora puede quedar pendiente.
- Fecha y hora pueden confirmarse en mensajes separados; también reconoce una confirmación posterior a la invitación automática al showroom.
- Si falta la fecha no crea una visita; genera seguimiento para completar los datos.
- Si hay aceptacion de visita y fecha pero falta hora, crea la visita real con `HoraConfirmada=0`, muestra `Hora pendiente` en Agenda y genera una alerta/accion para completarla sin inventar el dato.
- La hora se puede confirmar despues desde Agenda; al hacerlo se actualiza la visita, se cierra la alerta pendiente y se genera la notificacion de visita confirmada.
- `crm_visita.HoraConfirmada` es una columna aditiva; las visitas anteriores conservan valor `1` y no se modifican sus fechas.
- Frases como `Ok, si el otro sabado` se interpretan como continuacion de agenda; `otro sabado` refiere al sabado de la semana siguiente.
- Evita duplicados para el mismo teléfono dentro de una ventana de 45 minutos.
- Al confirmar crea `crm_visita`, actualiza el lead a `visita_agendada`, registra una acción IA ejecutada, crea `internal_alert` y guarda el evento `visita_agendada` para n8n.
- Al completar una visita pendiente se cierra su alerta y la accion IA pendiente asociada.
- El vendedor ve visitas propias o sin responsable; Administrador y Superadmin ven todas.
- Las cards `Agenda` y `Alertas` quedan en los hubs correspondientes sin agregar opciones permanentes al sidebar del vendedor.
- El Distribuidor no ve cards, enlaces ni scripts de `Agenda` o `Alertas`; el acceso directo conserva HTTP 403 como protección del servidor.
- Administrador y Superadmin consultan alertas abiertas cada 15 segundos desde un endpoint autenticado.
- Una visita nueva muestra una campana global con contador y un modal con acceso directo a Agenda; cada navegador recuerda el ultimo `IdAlert` mostrado para no repetir el popup en cada recarga.
- Si el navegador ya tiene permiso de notificaciones, tambien genera una notificacion del sistema. Vendedor y Distribuidor no cargan este sondeo global.
- Desde la bandeja se puede responder por Cloud API y queda guardado mensaje saliente.

## Flujo n8n

- Pantalla: `Automatizaciones -> n8n`.
- Workflows migrados a V2 el 10/07/2026:
  - `LTeco ERP System - Poll automation events`: activo cada minuto como respaldo de entrega Web Push; solo procesa eventos de visitas.
  - `LTeco ERP System - Daily operational digest`: activo; consulta el resumen operativo diario en modo lectura.
  - `LTeco ERP System - Meta WhatsApp inbound`: activo; reenvía payloads Meta al endpoint interno V2.
  - `LTeco ERP System - Android visit push`: activo; recibe el webhook inmediato y solicita la entrega push al panel.
- Usan `https://panel.ltecobike.shop/lteco-panel/api/n8n/*.php` y el header `X-Lteco-N8n-Token`.
- Se generó un backup previo a la migración n8n; fue retirado durante la limpieza posterior al cierre.
- Webhooks salientes configurables por evento:
  - `visita_agendada`
  - `visita_proxima`
  - `reserva_por_vencer`
  - `moto_lista_publicar`
  - `service_proximo`
  - `resumen_diario`
  - `inbox_mensaje_entrante`
- Endpoints internos protegidos por `X-Lteco-N8n-Token`:
  - `GET /lteco-panel/api/n8n/health.php`
  - `GET /lteco-panel/api/n8n/digest.php`
  - `POST /lteco-panel/api/n8n/inbox.php`
  - `POST /lteco-panel/api/n8n/meta_whatsapp.php`
  - `GET /lteco-panel/api/n8n/events.php`
  - `POST /lteco-panel/api/n8n/event_ack.php`
  - `POST /lteco-panel/api/n8n/classification.php`
  - `POST /lteco-panel/api/n8n/push_event.php`

## Notificaciones Android

- Administrador y Superadmin pueden activar Web Push desde el botón de campana con `+` del panel.
- Chrome registra cada teléfono mediante VAPID y la suscripción queda vinculada al usuario autenticado.
- El service worker muestra la alerta aunque el panel esté cerrado; al tocarla abre Agenda.
- `visita_agendada` y `visita_hora_confirmada` se envían primero por webhook inmediato a n8n.
- El polling activo cada minuto funciona como respaldo si el webhook inmediato falla.
- El evento solo se marca procesado después de una entrega push correcta; sin teléfonos suscritos permanece pendiente.
- Suscripciones vencidas se desactivan automáticamente y una restricción única evita duplicar una entrega por evento/dispositivo.
- El panel fuerza HTTPS. En pruebas internas con curl desde el contenedor se debe enviar `X-Forwarded-Proto: https` o usar la URL publica HTTPS.

## Reglas IA

- No inventar descuentos, promociones, precios, stock ni beneficios.
- No confirmar ventas, reservas ni disponibilidad.
- Para visitas, hablar de showroom; no prueba de manejo.
- Para modelos conocidos, usar ficha base y derivar precio/colores/disponibilidad al asesor.

## Validaciones

- `php -l` en archivos PHP modificados/nuevos.
- `php -l` en `src/Presentation/Panel/Support/n8n.php`, `lteco-panel/n8n/*.php` y `lteco-panel/api/n8n/*.php`.
- Prueba directa de IA desde contenedor:
  - Resultado: `AI_OK`.
- Smoke HTTP n8n con token:
  - `GET /lteco-panel/api/n8n/health.php`: `ok=true`, `ai_enabled=true`, `n8n_token_configured=true`.
  - `GET /lteco-panel/api/n8n/digest.php`: `ok=true`.
- Smoke render con sesion simulada superadmin:
  - `comercial/index.php`
  - `stock/index.php`
  - `operacion/index.php`
  - `administracion/index.php`
  - `automatizaciones/index.php`
- `docker exec ltecobike_panel php /var/www/html/tests/run.php`
  - Resultado actual: `OK - 938 aserciones pasaron`.
- Caso real reproducido: `hola, quiero mas info sobre los modelos4` genera el texto de catalogo de V3, tres imagenes y el mensaje de cierre.
- Smoke webhook publico:
  - POST sin `X-Hub-Signature-256`: HTTP 403.
  - POST vacio con firma valida: HTTP 200 y `ok=true`.
  - Verificacion GET de Meta: HTTP 200 y challenge correcto.
- Smoke de contexto citado sobre un mensaje real guardado: el ID de la imagen Q8 500W resuelve `ReplyToModelo=Q8 500W` sin exponer el identificador externo.
- Smoke Agenda sobre conversación real sin fecha/hora: `scheduled=no`, 0 visitas y 0 alertas; no agenda prematuramente.
- Smoke autenticado de `agenda/index.php` y `notificaciones/index.php`: HTTP 200.
- Smoke de alerta global: Admin y Superadmin reciben HTTP 200 con la visita abierta y cargan `visit-alerts.js`; Vendedor recibe HTTP 403 y no carga el script.
- Smoke de hubs con Administrador: inicio 5 secciones; Comercial 4 cards; Automatizaciones 4; Ventas 4; Stock 3; Administracion 6.
- Simulacion DOM del submit con multiples acciones: conserva `accion=ejecutar` antes de bloquear los botones.
- Conversacion `Ok, si el otro sabado` recuperada despues del backup: una visita real para el 18/07/2026, `HoraConfirmada=0`, alerta abierta y accion `completar_visita`; no se creo duplicado.
- Smoke autenticado de Agenda: la visita sin hora muestra `Hora pendiente` y el formulario `Confirmar hora`.
- Prueba transaccional de confirmacion de hora: actualiza visita, alerta, accion y lead; el rollback deja intacta la visita real.
- Smoke render con sesion simulada admin:
  - `automatizaciones/index.php`
  - `ia/index.php`
  - `ia/base.php`
  - `ia/acciones.php`
  - `whatsapp/index.php`

## Cierre QA 2026-07-10

- Se verificaron en navegador los hubs Comercial, Stock, Ventas, Administración y Automatizaciones en escritorio y 375 px, tanto en modo claro como oscuro.
- Se verificaron WhatsApp, Asistente IA, Acciones IA, Agenda, Alertas y n8n sin errores de consola ni desborde horizontal global.
- Se comprobó el envío correcto de `accion=ejecutar` en formularios con varios botones sin ejecutar cambios sobre la base.
- Se probaron permisos con sesiones temporales para Superadmin, Administrador, Vendedor y Distribuidor. Auditoría queda reservada a Superadmin y los módulos comerciales/técnicos mantienen sus restricciones.
- Los endpoints n8n `health`, `digest` y `events` respondieron HTTP 200 con token válido; un token inválido respondió HTTP 401.
- El webhook Meta rechazó un POST sin firma con HTTP 403.
- La rama incorporó `main` conservando la validación única de cédula y la jerarquía de cambio de contraseña dentro de la arquitectura POO.
- Validación final: `975` aserciones, `php -l` limpio en los archivos incorporados, JavaScript válido y `git diff --check` limpio.
- Smoke real WhatsApp: mensaje sobre Q8 350W recibido, clasificado y respondido con texto, imagen y cierre; Meta confirmó entrega/lectura.
- Smoke real Agenda: nueva fecha sin hora quedó con `HoraConfirmada=0`, alerta abierta y acción para completar la hora sin heredar horarios anteriores.
- QA Web Push: control visible sin superposición a 375 px, permisos Admin `200`/Vendedor `403`, webhook inmediato y polling n8n activos.
- QA mobile final: Agenda, Alertas, IA acciones y WhatsApp convertidas en fichas operables a 320-375 px, sin desborde global; dark mode y escritorio verificados.
- QA de permisos: la navegación de Distribuidor no contiene Agenda ni Alertas, y ambas rutas mantienen el bloqueo backend para accesos directos.
- Navegación global: todas las pantallas operativas y el Inicio muestran `Atrás`; vuelve al historial interno del panel y usa Inicio como destino seguro si no existe una pantalla anterior válida.

## Pendientes y riesgos operativos

- Los webhooks `visita_agendada` y `visita_hora_confirmada` están activos y sus últimos envíos finalizaron en HTTP 200. Los restantes continúan desactivados deliberadamente hasta definir destino y reglas concretas.
- Web Push fue registrado y comprobado en un Android real, incluida la recepción fuera del panel.
- No reconstruir historial viejo desde WhatsApp: solo se puede analizar lo que Meta haya entregado y el panel haya guardado.
