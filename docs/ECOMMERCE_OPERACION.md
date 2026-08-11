# Operación del ecommerce

## Alcance local actual

- `/modelos` agrupa variantes reales por modelo y permite seleccionar color y batería; las combinaciones se vuelven a verificar en el servidor.
- El carrito de invitado usa hash de token, expiración y fusión segura al autenticarse.
- El checkout oculta campos que no corresponden al tipo de cliente y conserva dirección para facturación.

## Arquitectura comercial vigente

El dominio comercial activo es `Cliente -> Storefront -> API Panel -> Application/Domain -> DB`. `public-web` queda sólo para contenido, catálogo público, URLs históricas y compatibilidad de lectura; no puede crear pedidos, reservar stock, iniciar pagos, confirmar pagos ni crear ventas.

Las rutas legacy `public-web/carrito.php` y `public-web/checkout.php` redirigen por GET a Storefront y responden `410 Gone` ante métodos mutativos. El webhook legacy `public-web/webhook-mercadopago.php` responde `410 Gone`; la revisión productiva previa a B2C mostró cero pagos MercadoPago pendientes/no terminales y cero reservas legacy activas, por lo que no se mantiene conciliación comercial en esa entrada.

## Publicación desde el panel

El endpoint privado consulta `MostrarEnWeb=1`, tipo `Moto`, precio positivo y estados publicables (`Disponible` o `Sin stock`). Las variantes se agrupan en Laravel por identificador comercial cuando existe y, como compatibilidad, por el nombre normalizado. El storefront ya no limita las rutas a una lista fija de slugs: genera el slug desde el panel y devuelve 404 sólo si el modelo no existe.

Un modelo sin galería usa el placeholder institucional y queda registrado en el log del panel; no desaparece silenciosamente. El stock cero se conserva como agotado en el catálogo, mientras que el carrito sólo acepta variantes con disponibilidad positiva. `Oculto`, `MostrarEnWeb=0`, ventas confirmadas y reservas activas quedan fuera del catálogo.

Para diagnosticar el catálogo sin modificar datos:

```bash
docker compose -f docker-compose.storefront.yml exec storefront_php php artisan storefront:catalog-refresh
```

- Las compras requieren cuenta activa, correo verificado y sesión iniciada.
- El carrito guarda unidades físicas concretas y reserva todas dentro de una única transacción.
- El pedido web queda vinculado a la venta interna; la garantía y los services se activan al registrar la entrega.
- Getnet queda preparado como posible proveedor, pero pago online, reembolsos automáticos y envíos permanecen deshabilitados hasta aprobar contrato e integración.

## Tarea periódica

Docker ejecuta `ecommerce_worker` cada minuto. En local procesa vencimientos y conciliaciones, pero deja los correos en cola porque `LTECO_ECOMMERCE_MAIL_ENABLED=0`. Activar el envío únicamente en el entorno definitivo, después de validar SMTP y las URL públicas.

Validación sin enviar mensajes:

```bash
docker compose -f docker-compose.storefront.yml exec -T storefront_php php artisan storefront:mail-check
docker compose exec -T panel php lteco-panel/cron/ecommerce.php --mail-check
```

Una prueba real requiere indicar deliberadamente un destinatario:

```bash
docker compose -f docker-compose.storefront.yml exec -T storefront_php php artisan storefront:mail-check --send=destino@example.com
docker compose exec -T panel php lteco-panel/cron/ecommerce.php --send-test=destino@example.com
```

Primero se puede comprobar sin escribir datos ni enviar correos:

```bash
docker compose exec -T panel php lteco-panel/cron/ecommerce.php --dry-run
```

Si el despliegue no usa el worker de Docker, en producción debe programarse cada cinco minutos:

```cron
*/5 * * * * cd /opt/ltecobike && docker compose exec -T panel php lteco-panel/cron/ecommerce.php >> /var/log/ltecobike-ecommerce.log 2>&1
```

La tarea libera reservas vencidas, genera recordatorios de service, procesa la cola de correos y marca inconsistencias para conciliación. No debe activarse en un entorno que use datos o correo de producción para pruebas.

## Revisión diaria

1. Revisar pedidos pagados, en preparación y listos.
2. Resolver alertas de conciliación antes de entregar una unidad.
3. Confirmar físicamente la entrega desde el panel para activar postventa.
4. Revisar notificaciones fallidas y solicitudes de privacidad.

Estados visibles al cliente: reserva, pago confirmado, preparación, listo para retirar, entrega, cancelación, vencimiento y reembolso. Cada cambio operativo se sincroniza desde el panel y conserva su auditoría.

## Seguridad y datos

- El personal no puede ver contraseñas ni datos completos de tarjetas.
- Las métricas web son contadores diarios agregados y no almacenan IP, identidad ni comportamiento individual.
- Los administradores acceden solo a los datos necesarios para pedidos, entrega, garantía y soporte.
- Backups, restauración y salud general continúan en Mantenimiento del panel.

## Pendiente antes de producción

- Confirmar si Getnet será el proveedor; recién entonces implementar credenciales, checkout alojado, webhook firmado, conciliación y reembolsos.
- Los envíos están fuera del alcance actual: la entrega es únicamente mediante retiro coordinado en Belvedere.
- Revisión legal final de textos y plazos de conservación.
- Programación efectiva del cron en el servidor definitivo.


## Assets del storefront

Las páginas productivas cargan directamente `public/css/storefront.css` y los archivos de `public/js/`. El repositorio conserva `package.json` y `vite.config.js` de la instalación Laravel, pero el layout público no usa `@vite`; por lo tanto, los cambios actuales no requieren un build de Node para verse. Al desplegar es obligatorio reconstruir la imagen PHP porque el código y los assets se copian dentro del contenedor.

El componente de marca busca primero `public/images/brand/logo-ltecobike.svg`. Si el archivo vectorial oficial no está disponible usa `logo-lteco.webp` como fallback. No se debe vectorizar ni redibujar automáticamente el logo.


## Telegram interno

Las nuevas ventas web se pueden avisar por Telegram usando un bot propio. Configurar `LTECO_TELEGRAM_ENABLED=1`, `LTECO_TELEGRAM_BOT_TOKEN`, `LTECO_TELEGRAM_CHAT_IDS` y `LTECO_TELEGRAM_START_AT` en `.env`. `LTECO_TELEGRAM_START_AT` debe ser la fecha/hora de activación para no enviar pedidos históricos. Validar con:

```bash
docker compose exec -T panel php lteco-panel/cron/ecommerce.php --telegram-check
docker compose exec -T panel php lteco-panel/cron/ecommerce.php --telegram-test
```

El cron de ecommerce procesa esos avisos y registra entregas en `telegram_delivery` para evitar duplicados por pedido y chat. `LTECO_WEB_SALES_NOTIFY_WEB_PUSH` queda en `0` por defecto para que las nuevas ventas usen Telegram como canal interno principal.
