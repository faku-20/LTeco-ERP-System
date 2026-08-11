# Arquitectura — LTeco ERP System

Documento de referencia técnica para desarrolladores.

Estado de referencia: 2026-08-05. El panel y la web legacy son PHP/Apache; el
storefront ecommerce es una aplicación Laravel/PHP-FPM separada. Los documentos
que describen exclusivamente la migración POO son históricos y no representan
una rama activa.

La gobernanza documental y la matriz ISO/SQuaRE están en
`docs/CALIDAD_DOCUMENTACION_ISO.md`.

---

## Visión general

LTeco ERP System está compuesto por dos aplicaciones PHP legacy y un storefront Laravel:

- `lteco-panel/`: panel interno PHP/Apache.
- `public-web/`: web pública legacy PHP/Apache.
- `storefront/`: catálogo y ecommerce Laravel/PHP-FPM, conectado al panel por una API privada HMAC.

El panel y la web legacy comparten `shared/`, `src/` y la base comercial. El
storefront usa su propia base Laravel y consulta/actualiza el panel mediante
endpoints privados firmados.

```
                        ┌─────────────────────────────┐
                        │        Base de datos         │
                        │     MySQL / MariaDB          │
                        └────────────┬────────────────┘
                                     │ PDO
                    ┌────────────────┴───────────────────┐
                    │           shared/ + src/             │
                    │  config · PDO · dominio · servicios  │
                    └────────────┬────────────────────────┘
                                 │
              ┌──────────────────┴──────────────────────┐
              │                                         │
   ┌──────────▼──────────┐               ┌─────────────▼──────────┐
   │    lteco-panel/      │               │     public-web/         │
   │  Apache/PHP :8081    │               │  Apache/PHP :8080       │
   └──────────┬──────────┘               └────────────────────────┘
              │ API privada HMAC
              ▼
   ┌──────────────────────────────┐
   │ storefront/ Laravel :8082    │
   │ Nginx + PHP-FPM + scheduler   │
   └──────────────────────────────┘
```

Los servicios `panel` y `web` montan el mismo `shared/`, `src/` y directorio de
imágenes de vehículos. El storefront se despliega con
`docker-compose.storefront.yml` y no comparte directamente la base comercial.

---

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Lenguaje | PHP 8.2+ para panel/web; PHP 8.4 en storefront |
| Base de datos | MySQL / MariaDB (PDO) |
| Servidor web | Apache 2.4 |
| Contenedores | Docker + Docker Compose |
| Frontend | HTML + CSS custom + JS vanilla; Blade/Livewire en storefront |
| Gráficos | Chart.js (vendored) |
| QR | qrcodejs + html5-qrcode (vendored) |
| Autenticación 2FA | TOTP (algoritmo estándar, secretos cifrados AES-256-GCM) |
| Verificación anti-bot | Cloudflare Turnstile (opcional) |
| Notificaciones | WhatsApp Cloud API (Meta, sin BSP) |

El panel/web usan assets propios y vendor versionado. El repositorio raíz tiene
Composer para el autoload PSR-4 y Web Push; `storefront/` tiene Composer y
Laravel. El layout productivo del storefront no usa Vite: sus assets públicos
se sirven directamente desde `storefront/public/`.

---

## Flujo de una request

```
Browser → Apache → PHP
                    │
                    ├── require shared/app_config.php
                    │     ├── configureRuntime()   ← headers seguridad, error handling
                    │     └── redirectToHttpsIfNeeded()
                    │
                    ├── require includes/db.php     ← crea $pdo
                    ├── require includes/auth.php   ← inicia sesión PHP
                    ├── require includes/helpers.php
                    │
                    ├── requiereLogin() / requiereAdmin() / ...   ← guard
                    │
                    ├── servicio de aplicación en `src/`
                    │     └── repositorio PDO en `src/Infrastructure/`
                    │
                    ├── require includes/header.php   ← HTML <head> + sidebar
                    ├── [HTML del módulo]
                    └── require includes/footer.php
```

`shared/app_config.php` se auto-incluye desde `lteco-panel/includes/db.php` y `shared/db.php`. No llamarlo manualmente.

---

## Archivos clave

### `shared/app_config.php`
Config central. Define:
- Constantes: roles (`ROL_SUPERADMIN`, etc.), monedas, rutas base
- Catálogos: estados de motos, repuestos, ventas, métodos de pago, tarjetas, cuotas, categorías de gasto
- Funciones de entorno: `configEnv()`, `appEnv()`, `appIsProduction()`, `appDebug()`
- Funciones de URL: `panelBaseUrl()`, `publicBaseUrl()`, `panelAbsoluteUrl()`
- Seguridad: `configureSecurityHeaders()`, `configureSessionCookieSecurity()`
- Se incluye automáticamente en cada request

### `lteco-panel/includes/auth.php`
Sesión y control de acceso:
- `requiereLogin()` — redirige al login si no hay sesión
- `requiereModulo($modulo)` — verifica permiso de módulo según rol
- `requiereAdmin()` — Superadmin o Administrador
- `requiereSuperadmin()` — solo Superadmin
- `requiereNoDistribuidor()` — bloquea rol Distribuidor
- `requierePuedeVerRegistro($tipo, $id)` — valida ownership de venta, cliente o postventa
- `vendedorPuedeVerVenta()` — ownership por `venta.UsuarioVendedorId`
- `vendedorPuedeVerCliente()` — cliente propio si tiene venta propia asociada
- `vendedorPuedeVerPostventa()` / `vendedorPuedeOperarPostventaService()` — postventa propia por venta asociada
- `usuarioActual()` — devuelve el array de sesión del usuario
- `rolActual()` — string del rol normalizado
- Gestión de expiración por inactividad y regeneración de ID de sesión

### `lteco-panel/includes/helpers.php`
Utilidades de uso transversal:
- `h($valor)` — escapa HTML para salida segura (wraps `htmlspecialchars`)
- `redirectWithFlash($url, $tipo, $msg)` — redirige con mensaje flash
- `requirePost()` — abort si la request no es POST
- `verifyCsrfOrFail()` — valida token CSRF del formulario
- `csrfInput()` — genera el `<input>` CSRF para formularios
- `obtenerTipoCambioUSD($pdo)` — tipo de cambio activo desde la última importación
- `convertirAUyu($montoUSD, $tc)` — conversión USD → UYU
- `formatearMonto($monto)` — formato numérico con separadores
- `normalizarNumeroUsuario($valor)` — normaliza `1.200,50` o `1200.50` a float
- `subirImagenVehiculo($file, $prefijo)` — sube imagen con nombre seguro

### `lteco-panel/includes/auditoria.php`
- `registrarAuditoria($pdo, $accion, $modulo, $detalle, $extra = [])` — graba en tabla `auditoria`. Llamar en toda operación sensible.

### `lteco-panel/includes/whatsapp.php`
- `enviarWhatsAppTemplate($telefono, $template, $params)` — envía plantilla vía Meta Cloud API
- `enviarWhatsAppTemplateConPdo($pdo, ...)` — igual, lee config desde tabla `configuracion`
- Retornan `false` silenciosamente si WhatsApp está deshabilitado o sin configurar

### `shared/comprobante_verificacion.php`
- Generación de número de comprobante único
- `generarTokenComprobante($idVenta)` — HMAC-SHA256 con `LTECO_COMPROBANTE_SECRET`
- `verificarTokenComprobante($idVenta, $token)` — valida autenticidad para la web pública

---

## Sistema de roles

Cuatro roles definidos como constantes en `app_config.php`:

| Constante | Valor | Acceso post-login |
|-----------|-------|-------------------|
| `ROL_SUPERADMIN` | `'Superadmin'` | Dashboard directo |
| `ROL_ADMINISTRADOR` | `'Administrador'` | Lobby (`inicio.php`) |
| `ROL_VENDEDOR` | `'Vendedor'` | Lobby (`inicio.php`) |
| `ROL_DISTRIBUIDOR` | `'Distribuidor'` | Lobby distribuidor |

**Módulos por rol:**

| Módulo | Superadmin | Administrador | Vendedor | Distribuidor |
|--------|:-----------:|:-------------:|:--------:|:------------:|
| Dashboard | ✅ | ✅ | — | — |
| Vehículos | ✅ | ✅ | ✅ lectura/operación comercial | — |
| Repuestos | ✅ | ✅ | ✅ lectura/stock para venta | solo catálogo |
| Clientes | ✅ | ✅ | propios por venta | — |
| Ventas | ✅ | ✅ | propias + nueva venta | desde su stock |
| Postventa | ✅ | ✅ | propia por venta | — |
| Gastos | ✅ | ✅ | — | — |
| Balance | ✅ | ✅ | — | — |
| Importaciones | ✅ | ✅ | — | — |
| Distribuidores | ✅ | ✅ | — | solo el propio |
| Usuarios | ✅ | ✅ limitado | — | — |
| Configuración | ✅ | ✅ | — | — |
| Auditoría | ✅ | — | — | — |
| Mantenimiento/Backup | ✅ | — | — | — |
| Búsqueda | ✅ | ✅ | ✅ | — |

MFA (TOTP) es obligatorio para Superadmin y Administrador. Los secretos TOTP se cifran con AES-256-GCM usando `LTECO_MFA_ENCRYPTION_KEY`.

Por decisión aceptada, `ROL_VENDEDOR` no requiere MFA por ahora. Su alcance se limita con ownership por registro:

- Ventas: solo `venta.UsuarioVendedorId = IdUsuario` del vendedor actual.
- Clientes: solo clientes con al menos una venta propia.
- Búsqueda global: solo muestra ventas/clientes propios; si existe un cliente ajeno coincidente, muestra un mensaje genérico sin PII.
- Postventa: solo servicios y garantías asociados a ventas propias.
- Nueva venta: el selector de cliente existente no renderiza clientes ajenos ni PII ajena.
- Guardado de venta: rechaza `cliente_id` ajeno enviado manualmente por POST.
- Alta de cliente: para Vendedor, duplicados por teléfono/correo devuelven el mismo mensaje genérico.
- Exportaciones de ventas/clientes: reservadas a Admin/Superadmin.

---

## Modelo de datos — tablas principales

```
producto ─── vehiculo ─── vehiculo_imagen
    │
    └── repuesto

cliente ─── venta ─── venta_detalle ──► producto
                │
                ├── garantia ──► vehiculo
                └── service_vehiculo ──► vehiculo

venta ─── postventa_historial_tecnico ─── postventa_repuesto_usado
                                                    │
                                                    └──► producto (repuesto)

distribuidor ─── distribuidor_stock ──► producto
             ─── distribuidor_pedido
             ─── distribuidor_comision
             ─── usuario (IdDistribuidor FK)

importacion ──► vehiculo (importación origen)
            ──► repuesto (importación origen)

gasto
configuracion
empresa
auditoria
login_attempts
notificacion_whatsapp
```

**Invariante crítico:** ventas con `EstadoVenta = 'Anulada'` deben filtrarse de toda lógica de negocio. Usar siempre `COALESCE(EstadoVenta, 'Confirmada') <> 'Anulada'` en queries que excluyan anuladas.

---

## Seguridad — convenciones obligatorias

### Toda acción mutante requiere:

```php
requirePost();        // rechaza GET, HEAD, etc.
verifyCsrfOrFail();   // valida token del formulario
```

### Toda salida de datos requiere:

```php
echo h($row['Campo']);  // nunca echo $row['Campo'] directamente
```

### Toda operación sensible requiere:

```php
registrarAuditoria($pdo, 'ACCION', 'Modulo', 'detalle');
```

### Headers de seguridad

Aplicados automáticamente por `configureSecurityHeaders()` en cada request:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Content-Security-Policy` con nonce para scripts inline permitidos
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` cuando la request es HTTPS o `LTECO_FORCE_HTTPS=1`
- `Permissions-Policy`

### Sesiones

- Cookies `HttpOnly` + `SameSite=Lax` + `Secure` en HTTPS
- Expiración por inactividad (`LTECO_SESSION_IDLE_SECONDS`, default 2h)
- Regeneración periódica de ID (`LTECO_SESSION_REGENERATE_SECONDS`, default 30min)
- Modo estricto (`session.use_strict_mode=1`)

### Login

- `password_verify()` para validar contraseñas (bcrypt)
- Bloqueo temporal tras intentos fallidos (tabla `login_attempts`)
- CSRF en el formulario
- Soporte Cloudflare Turnstile cuando `LTECO_TURNSTILE_SITE_KEY` y `LTECO_TURNSTILE_SECRET_KEY` están configuradas
- Bloqueo temporal por intentos fallidos

En QA local/headless, Turnstile puede bloquear el login automatizado. Para smoke tests autenticados locales se usan sesiones temporales de prueba, sin cambiar datos de negocio.

### SMTP y utilidades sensibles

- SMTP usa STARTTLS validado cuando se envía por puerto 587.
- `LTECO_MAIL_EHLO_HOST` permite configurar el EHLO SMTP.
- `test_mail.php` está restringido a Superadmin.
- Cron de WhatsApp no debe ser invocado por HTTP público.
- Secretos de comprobantes y MFA deben ser largos, únicos por entorno y no versionados.

---

## Tipo de cambio USD

El tipo de cambio activo **siempre** viene de la importación activa más reciente:

```php
$tc = obtenerTipoCambioUSD($pdo);
```

No hardcodear tasas. Si no hay importación activa, cae al valor de `LTECO_DEFAULT_EXCHANGE_RATE`.

---

## Convención de redirect post-POST

Nunca usar `header('Location: ...')` directamente. Usar siempre:

```php
redirectWithFlash(panelBaseUrl('modulo/index.php'), 'success', 'Operación exitosa.');
// o
redirectWithFlash(panelBaseUrl('modulo/index.php'), 'error', 'Ocurrió un error.');
// o 'warning' / 'info'
```

---

## Web pública legacy

`public-web/` usa su propio `includes/db.php` y `includes/helpers.php`. No depende de los includes del panel. Comparte `shared/` para acceder a la misma base de datos y lógica de vehículos/comprobantes.

Los productos aparecen en la web legacy si `MostrarEnWeb = 1` y el estado es
publicable según la lógica legacy. Esta superficie se mantiene por compatibilidad.

## Storefront ecommerce

El storefront vive en `storefront/` y se levanta con `docker-compose.storefront.yml`:

- Nginx público en `127.0.0.1:8082`.
- PHP-FPM en `storefront_php`.
- Scheduler en `storefront_scheduler`.
- API privada bajo `/api/storefront/v1`, autenticada con HMAC.
- Catálogo dinámico por modelo/variante, carrito, cuenta, reservas, pedidos,
  privacidad y agenda.

El catálogo valida nuevamente precio, stock, estado publicable y variante en el
servidor. El checkout actual reserva para retiro coordinado en Belvedere; pagos
online, reembolsos y envíos permanecen deshabilitados.

---

## Logs y observabilidad

| Dónde | Qué registra |
|-------|--------------|
| `storage/logs/php-error.log` | Errores PHP del sistema |
| `lteco-panel/logs/panel-error.log` | Errores del panel (vía `logPanelError()`) |
| Tabla `auditoria` | Operaciones sensibles con usuario, IP, módulo y detalle |
| Tabla `notificacion_whatsapp` | Cada envío WhatsApp con estado y respuesta |
| Tabla `login_attempts` | Intentos de login y bloqueos |

---

## Decisiones de diseño

**¿Por qué conviven PHP legacy, capas POO y Laravel?**
El panel y la web legacy se mantienen por compatibilidad de URLs y operación.
El código nuevo del panel ubica reglas y persistencia en `src/`, mientras que el
ecommerce usa Laravel para cuentas, sesiones, colas, reservas y pruebas aisladas.

**¿Por qué `shared/` en lugar de duplicar código?**  
Los dos contenedores (panel y web) necesitan la misma conexión a base de datos y la misma lógica de vehículos. En lugar de duplicar, montan el mismo directorio. `app_config.php` usa `dirname(__DIR__)` para encontrar el `.env` sin importar desde qué contenedor se llame.

**¿Por qué hay migraciones SQL y migraciones Laravel?**
El panel conserva migraciones SQL manuales, aplicadas con revisión y backup. No
todas son idempotentes: varias usan `ALTER TABLE ... ADD COLUMN` sin
`IF NOT EXISTS`. El storefront usa sus propias migraciones Laravel. No aplicar
el conjunto SQL con un loop ciego; revisar el orden indicado en
`HANDOFF.md`/`MIGRACION_SERVIDOR.md`.

**¿Por qué WhatsApp Cloud API directo (sin BSP)?**  
Elimina un intermediario y su costo mensual. La contra es que requiere cuenta Meta Business verificada y gestión directa de plantillas aprobadas.
