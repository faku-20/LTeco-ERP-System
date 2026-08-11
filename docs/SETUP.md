# Guía de setup — Ltecobike

Stack: PHP legacy + servicios por capas, Laravel, MySQL/MariaDB, Apache, Nginx y Docker.
Entornos: `local`, `staging` y `production`.

---

## Requisitos previos

- Docker + Docker Compose v2
- Git
- MySQL/MariaDB corriendo en el host (el panel se conecta vía `host.docker.internal`)
- PHP 8.2+ para el panel/web y PHP 8.4 para la imagen del storefront; en el host solo si necesitás correr linting fuera del contenedor

---

## Setup local en 5 pasos

### 1. Clonar e instalar

```bash
git clone git@github.com:faku-20/ltecobike-system.git
cd ltecobike-system
```

### 2. Crear base de datos

En MySQL del host:

```sql
CREATE DATABASE lteco_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lteco_user'@'%' IDENTIFIED BY 'tu-clave-segura';
GRANT ALL PRIVILEGES ON lteco_db.* TO 'lteco_user'@'%';
FLUSH PRIVILEGES;
```

### 3. Configurar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` y cambiar como mínimo:

```
LTECO_ENV=local
LTECO_DEBUG=1
LTECO_FORCE_HTTPS=0
LTECO_DB_HOST=host.docker.internal
LTECO_DB_NAME=lteco_db
LTECO_DB_USER=lteco_user
LTECO_DB_PASS=tu-clave-segura
LTECO_COMPROBANTE_SECRET=un-secreto-largo-y-aleatorio
LTECO_MFA_ENCRYPTION_KEY=otro-secreto-largo-y-aleatorio
```

No copiar secretos reales a documentación, tickets ni chats. Usar `.env.example` como contrato y completar `.env` local/producción fuera del repositorio.

### 4. Aplicar migraciones

Las migraciones del panel están en `database/migrations/`. No aplicar todas con
un loop alfabético: las migraciones ecommerce tienen dependencias y algunas no
son idempotentes. Usar backup, revisar el estado de la base y aplicar el orden
documentado en `MIGRACION_SERVIDOR.md`.

```bash
mysql -h 127.0.0.1 -u lteco_user -p lteco_db < database/migrations/2026_05_21_lteco_panel_core_fix.sql
# Continuar con las migraciones en el orden lógico indicado para el entorno.
```

O individualmente:

```bash
mysql -h 127.0.0.1 -u lteco_user -p lteco_db < database/migrations/2026_05_21_lteco_panel_core_fix.sql
```

### 5. Levantar contenedores

```bash
docker compose up -d
```

Verificar:
- Panel: http://127.0.0.1:8081/lteco-panel/
- Web pública: http://127.0.0.1:8080/public-web/

El storefront se levanta aparte:

```bash
docker compose -f docker-compose.storefront.yml up -d --build
```

- Storefront: http://127.0.0.1:8082/

---

## Comandos de uso frecuente

```bash
# Levantar
docker compose up -d

# Ver logs en tiempo real
docker compose logs -f panel
docker compose logs -f web

# Reconstruir imagen (después de cambiar Dockerfile o apache conf)
docker compose up -d --build

# Validar sintaxis PHP de un archivo
docker exec ltecobike_panel php -l /var/www/html/lteco-panel/ventas/index.php

# Acceder al contenedor
docker exec -it ltecobike_panel bash

# Detener
docker compose down
```

### Reset de datos comerciales de prueba

El reset de datos de prueba no reinicia Docker ni el servidor. Es un script PHP CLI que limpia datos comerciales para volver a testear flujos desde cero. Corre en dry-run por defecto y no debe ejecutarse en modo real sin revisar primero los conteos que informa.

```bash
# Dry-run: muestra tablas, conteos y alcance. No modifica la base.
docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php

# Ejecución real: destructiva, exige confirmación explícita.
docker exec ltecobike_panel php /var/www/html/lteco-panel/scripts/cleanup_test_data.php --execute --confirm=RESET-LTECO-TEST-DATA
```

Antes de borrar, el script crea un backup automático en `/opt/backups/ltecobike/`. Mantiene usuarios, vehículos, productos, repuestos, configuración, empresa e importaciones. Limpia ventas, clientes, garantías, services/postventa, gastos y comisiones de venta, pedidos/remitos de distribuidor, stock asignado de distribuidor y auditoría comercial.

Después de ejecutarlo, validar como mínimo: `venta=0`, `venta_detalle=0`, `cliente=0`, services/garantías de venta en cero, motos disponibles con `Stock=1`, repuestos con stock reintegrado y usuarios/configuración sin cambios.

---

## Estructura de directorios

```
ltecobike/
├── .env                      # Variables locales (no versionado)
├── .env.example              # Contrato de variables
├── docker-compose.yml
├── docker/
│   ├── Dockerfile.panel
│   ├── Dockerfile.web
│   ├── apache-panel.conf
│   └── apache-web.conf
├── shared/                   # Código PHP compartido entre panel y web
│   ├── app_config.php        # Config central, roles, estados, seguridad
│   ├── db.php                # Conexión PDO
│   ├── vehiculo_logic.php    # Lógica de vehículos compartida
│   └── comprobante_verificacion.php
├── lteco-panel/              # Panel interno
│   ├── includes/
│   │   ├── auth.php          # Sesión, roles, guards
│   │   ├── helpers.php       # Utilidades: h(), CSRF, redirect, formateo
│   │   ├── auditoria.php     # registrarAuditoria()
│   │   └── whatsapp.php      # WhatsApp Cloud API
│   ├── ventas/
│   ├── clientes/
│   ├── vehiculos/
│   └── ...
├── public-web/               # Sitio público legacy
│   └── ...
├── storefront/               # Laravel ecommerce
│   └── ...
├── database/
│   └── migrations/           # SQL incrementales; revisar idempotencia por archivo
└── storage/
    ├── logs/                 # php-error.log
    └── backups/
```

---

## Cómo agregar una migración

Crear un archivo en `database/migrations/` con fecha ISO como prefijo:

```bash
touch database/migrations/2026_06_01_nombre_descriptivo.sql
```

Escribir SQL defensivo y declarar si requiere ejecución única. No asumir que
`ALTER TABLE ... ADD COLUMN` es idempotente en MariaDB/MySQL:

```sql
ALTER TABLE cliente ADD COLUMN IF NOT EXISTS PaisOrigen varchar(60) DEFAULT NULL;
CREATE TABLE IF NOT EXISTS nueva_tabla (
  Id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Aplicar:

```bash
mysql -h 127.0.0.1 -u lteco_user -p lteco_db < database/migrations/2026_06_01_nombre_descriptivo.sql
```

No hay runner automático. Las migraciones se aplican manualmente y en orden.

---

## Cómo agregar una nueva página al panel

### Patrón mínimo

Cada página es un archivo PHP autónomo. El patrón estándar al inicio del archivo:

```php
<?php
$pageTitle = "Mi módulo | Ltecobike";

require_once __DIR__ . "/../includes/db.php";    // crea $pdo
require_once __DIR__ . "/../includes/auth.php";  // inicia sesión
require_once __DIR__ . "/../includes/helpers.php";

requiereLogin();                // cualquier usuario autenticado
// O uno más específico:
// requiereModulo("ventas");   // según permiso de módulo
// requiereAdmin();            // Superadmin o Administrador
// requiereSuperadmin();       // solo Superadmin

require_once __DIR__ . "/../includes/header.php";
?>

<!-- HTML de la página -->

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
```

### Para acciones POST (crear, editar, eliminar)

```php
<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/helpers.php";

requiereAdmin();
requirePost();          // rechaza GET
verifyCsrfOrFail();     // valida token CSRF

// ... lógica ...

registrarAuditoria($pdo, 'ACCION_NOMBRE', 'Módulo', 'detalle');
redirectWithFlash(panelBaseUrl('modulo/index.php'), 'success', 'Guardado correctamente.');
```

### Salida segura de datos

Siempre usar `h()` para mostrar datos del usuario o la base de datos:

```php
echo h($row['Nombre']);           // escapa HTML
echo h($row['Descripcion'], '—'); // con fallback
```

---

## Validar cambios PHP

Antes de hacer commit, verificar que no haya errores de sintaxis:

```bash
docker exec ltecobike_panel php -l /var/www/html/lteco-panel/ruta/al/archivo.php
```

---

## Logs

| Log | Dónde |
|-----|-------|
| Errores PHP globales | `storage/logs/php-error.log` |
| Errores del panel | `lteco-panel/logs/panel-error.log` |
| Auditoría de operaciones | Tabla `auditoria` en la base |
| Envíos WhatsApp | Tabla `notificacion_whatsapp` |

Ver logs en vivo:

```bash
docker compose logs -f panel
tail -f storage/logs/php-error.log
```

---

## Variables de entorno — referencia completa

| Variable | Default | Descripción |
|----------|---------|-------------|
| `LTECO_ENV` | `local` | Entorno. `production` activa comportamientos de producción |
| `LTECO_DEBUG` | `1` en local | Muestra errores PHP en pantalla |
| `LTECO_FORCE_HTTPS` | `0` | Redirige HTTP → HTTPS. Solo activar en producción con HTTPS real |
| `LTECO_PANEL_BASE_URL` | `/lteco-panel` | Prefijo URL del panel |
| `LTECO_PUBLIC_BASE_URL` | `/public-web` | Prefijo URL de la web pública |
| `LTECO_PANEL_PUBLIC_URL` | (vacío) | URL absoluta del panel en producción |
| `LTECO_PUBLIC_URL` | (vacío) | URL absoluta de la web pública en producción |
| `LTECO_DB_HOST` | `host.docker.internal` | Host MySQL |
| `LTECO_DB_NAME` | — | Base de datos |
| `LTECO_DB_USER` | — | Usuario MySQL |
| `LTECO_DB_PASS` | — | Contraseña MySQL |
| `LTECO_COMPROBANTE_SECRET` | — | Secreto para HMAC de comprobantes. Cambiar antes de producción |
| `LTECO_VERIFY_RATE_LIMIT` | `60` | Límite de verificaciones públicas de comprobante por ventana |
| `LTECO_VERIFY_RATE_WINDOW_SECONDS` | `3600` | Ventana de rate limit para verificación pública |
| `LTECO_MFA_ENCRYPTION_KEY` | — | Clave AES-256-GCM para cifrar secretos TOTP. Cambiar antes de producción |
| `LTECO_SESSION_IDLE_SECONDS` | `7200` | Segundos de inactividad antes de cerrar sesión |
| `LTECO_SESSION_REGENERATE_SECONDS` | `1800` | Frecuencia de regeneración del ID de sesión |
| `LTECO_TURNSTILE_SITE_KEY` | (vacío) | Cloudflare Turnstile — clave pública. Dejar vacío para desactivar |
| `LTECO_TURNSTILE_SECRET_KEY` | (vacío) | Cloudflare Turnstile — clave privada |
| `LTECO_TRUSTED_PROXY_RANGES` | (vacío) | Proxies confiables para tomar IP real desde headers del proxy |
| `LTECO_DEFAULT_EXCHANGE_RATE` | `42.00` | Tipo de cambio USD/UYU por defecto (fallback si no hay importación activa) |
| `LTECO_DEFAULT_IVA_RATE` | `22.00` | Tasa IVA por defecto |
| `LTECO_DEFAULT_WHATSAPP` | — | WhatsApp de contacto por defecto |
| `LTECO_DEFAULT_EMPRESA_RUT` | — | RUT de empresa por defecto |
| `LTECO_BACKUP_DIR` | `/opt/backups/ltecobike` | Directorio de backups en el host |
| `LTECO_DB_BACKUP_HOST` | `host.docker.internal` | Host usado por scripts de backup de base |
| `LTECO_MAIL_HOST` | `smtp.gmail.com` | Host SMTP para notificaciones email |
| `LTECO_MAIL_PORT` | `587` | Puerto SMTP. En 587 se usa STARTTLS validado |
| `LTECO_MAIL_USER` | — | Usuario SMTP |
| `LTECO_MAIL_PASS` | — | Contraseña o app password SMTP |
| `LTECO_MAIL_FROM` | — | Remitente email |
| `LTECO_MAIL_NAME` | `Ltecobike` | Nombre visible del remitente |
| `LTECO_MAIL_TO` | — | Destinatario de pruebas/notificaciones internas |
| `LTECO_WEB_SALES_NOTIFY_EMAILS` | `LTECO_MAIL_TO` | Emails internos que reciben aviso de nueva compra web; separar varios con coma |
| `LTECO_WEB_SALES_NOTIFY_WHATSAPP` | — | Teléfonos internos que reciben WhatsApp por nueva compra web; separar varios con coma |
| `LTECO_TELEGRAM_ENABLED` | `0` | Habilita avisos internos por Telegram para nuevas compras web |
| `LTECO_TELEGRAM_BOT_TOKEN` | — | Token del bot de Telegram; no versionar |
| `LTECO_TELEGRAM_CHAT_IDS` | — | Chat IDs internos que reciben avisos; separar varios con coma |
| `LTECO_TELEGRAM_START_AT` | — | Fecha/hora desde la que se procesan avisos Telegram; evita enviar pedidos históricos |
| `LTECO_WEB_SALES_NOTIFY_WEB_PUSH` | `0` | Reactiva Web Push para nuevas compras web si se necesita convivir con Telegram |
| `LTECO_MAIL_EHLO_HOST` | `ltecobike.shop` | Host EHLO enviado al servidor SMTP |
| `LTECO_WEB_PUSH_ENABLED` | `0` | Habilita notificaciones Web Push para Android |
| `LTECO_WEB_PUSH_VAPID_SUBJECT` | — | URL HTTPS o mailto que identifica al emisor Web Push |
| `LTECO_WEB_PUSH_VAPID_PUBLIC_KEY` | — | Clave pública VAPID enviada a Chrome |
| `LTECO_WEB_PUSH_VAPID_PRIVATE_KEY` | — | Clave privada VAPID; nunca versionar ni exponer |

---

## Producción

Diferencias con local:

1. `LTECO_ENV=production` y `LTECO_DEBUG=0`
2. `LTECO_FORCE_HTTPS=1` — solo si hay certificado SSL válido
3. `LTECO_COMPROBANTE_SECRET` y `LTECO_MFA_ENCRYPTION_KEY` con valores aleatorios únicos
4. `LTECO_BACKUP_DIR` apuntando a directorio fuera del webroot con backups automáticos
5. MFA activo en todas las cuentas Superadmin y Administrador
6. Base de datos con usuario de permisos mínimos (solo el schema de lteco, sin GRANT)

Turnstile puede bloquear login automatizado en local/headless si las claves o el dominio no coinciden. Para QA automatizada local, dejar Turnstile desactivado o usar sesiones temporales controladas; en producción debe quedar configurado con el dominio real.

`test_mail.php` es solo para Superadmin. El cron de WhatsApp debe ejecutarse por CLI/cron y no por HTTP público.

Antes de cada cambio importante en producción: hacer backup desde el panel (`Configuración > Mantenimiento`) o ejecutar `scripts/backup_db.sh`.

Para resetear datos comerciales de prueba, usar `lteco-panel/scripts/cleanup_test_data.php` con dry-run previo. No confundir este reset de datos con `docker compose restart`, `docker compose down` o reconstrucción de contenedores.
