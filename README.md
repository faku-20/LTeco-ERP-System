# Ltecobike

Sistema de gestión comercial de Ltecobike. El repositorio contiene el panel interno, la web pública legacy, el storefront ecommerce Laravel, la capa de servicios PHP compartida, migraciones SQL, scripts operativos, documentación y tests.

## Superficies de la app

| Superficie | Ruta | Stack | URL local |
|------------|------|-------|-----------|
| Panel interno | `lteco-panel/` | PHP 8.2+, Apache, PDO | `http://127.0.0.1:8081/lteco-panel/` |
| Web pública legacy | `public-web/` | PHP 8.2+, Apache, PDO | `http://127.0.0.1:8080/public-web/` |
| Storefront ecommerce | `storefront/` | Laravel 13, PHP 8.4, Nginx, PHP-FPM | `http://127.0.0.1:8082/` |

El panel y la web legacy comparten `shared/`, `src/` y la base comercial MySQL/MariaDB. El storefront tiene base Laravel propia y se comunica con el panel mediante API privada firmada con HMAC.

## Stack

- PHP `>=8.2` en el proyecto raíz.
- PHP `8.4` en el storefront.
- Composer en raíz para autoload PSR-4 `Lteco\\` y `minishlink/web-push`.
- Laravel `13.x` y Livewire `4.x` en `storefront/`.
- MySQL/MariaDB como base comercial.
- Docker Compose para levantar panel, web pública, worker ecommerce y storefront.
- Frontend legacy con HTML, CSS propio y JS vanilla.
- Storefront con Blade, CSS/JS públicos y Vite disponible para desarrollo.

## Estructura

```text
.
├── composer.json                 # Dependencias/autoload del panel y web legacy
├── docker-compose.yml            # Panel, web pública y worker ecommerce
├── docker-compose.storefront.yml # Storefront Nginx/PHP-FPM/scheduler
├── database/
│   ├── baseline/                 # Schema base versionado
│   └── migrations/               # Migraciones SQL incrementales
├── docker/                       # Dockerfiles y configuración Apache/Nginx
├── docs/                         # Documentación técnica, operación y usuario
├── lteco-panel/                  # Panel interno PHP
├── public-web/                   # Web pública legacy
├── scripts/                      # Comandos de test, migración y operación
├── shared/                       # Config, DB, mailer y lógica compartida
├── src/                          # Servicios, dominio y repositorios PSR-4
├── storefront/                   # Ecommerce Laravel
└── tests/                        # Tests del panel, contratos e integración
```

## Módulos principales

El panel cubre operación comercial y administrativa:

- Dashboard, operación y búsqueda global.
- Vehículos, stock, QR, etiquetas, publicación web y reservas.
- Repuestos, cajas de repuestos e inventario.
- Clientes, ventas, comprobantes, anulaciones y exportaciones.
- Postventa, services, garantías, intervenciones y consumo de repuestos.
- Gastos, balance e importaciones.
- Distribuidores, stock asignado, pedidos, ventas y reportes.
- Usuarios, roles, MFA, auditoría y configuración.
- WhatsApp, n8n, IA comercial, Telegram, web push y alertas operativas.
- Ecommerce: catálogo, términos comerciales, reservas, pedidos, privacidad, visitas y notificaciones.

## Arquitectura

```text
Browser
  |
  +-- Apache/PHP -> lteco-panel/ + public-web/
  |        |
  |        +-- shared/        config, PDO, helpers compartidos
  |        +-- src/           servicios de aplicación, dominio, repositorios
  |        +-- MySQL/MariaDB  base comercial
  |
  +-- Nginx/PHP-FPM -> storefront/
           |
           +-- Laravel DB propia
           +-- API privada HMAC -> lteco-panel/api/storefront/v1/*
```

El código nuevo del panel tiende a seguir capas:

- `src/Application/*`: casos de uso y servicios.
- `src/Domain/*`: reglas puras de negocio.
- `src/Infrastructure/Repository/*`: consultas y persistencia PDO.
- `src/Presentation/Panel/*`: bootstrap y soporte de presentación.
- `lteco-panel/*`: páginas y endpoints PHP del panel.

## Requisitos locales

- Docker y Docker Compose v2.
- Git.
- MySQL/MariaDB accesible desde contenedores por `host.docker.internal`.
- Composer si se instalan dependencias fuera de Docker.
- Node/npm solo para tareas frontend del storefront.

## Setup rápido

1. Clonar el repositorio.

   ```bash
   git clone git@github.com:faku-20/ltecobike-system.git
   cd ltecobike-system
   ```

2. Crear la base comercial en MySQL/MariaDB.

   ```sql
   CREATE DATABASE lteco_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'lteco_user'@'%' IDENTIFIED BY 'tu-clave-segura';
   GRANT ALL PRIVILEGES ON lteco_db.* TO 'lteco_user'@'%';
   FLUSH PRIVILEGES;
   ```

3. Crear el `.env` del panel/web.

   ```bash
   cp .env.example .env
   ```

   Variables mínimas para local:

   ```env
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

4. Levantar panel y web pública.

   ```bash
   docker compose up -d --build
   ```

5. Aplicar o listar migraciones del panel.

   ```bash
   scripts/migrate.sh --list
   scripts/migrate.sh --dry-run
   scripts/migrate.sh
   ```

   En producción, el runner exige confirmación explícita:

   ```bash
   scripts/migrate.sh --allow-production
   ```

## Storefront Laravel

1. Crear configuración del storefront.

   ```bash
   cp storefront/.env.example storefront/.env
   ```

2. Configurar como mínimo:

   ```env
   APP_KEY=
   DB_DATABASE=lteco_storefront
   DB_USERNAME=storefront_app
   DB_PASSWORD=
   CATALOG_DB_DATABASE=lteco_db
   CATALOG_DB_USERNAME=storefront_reader
   CATALOG_DB_PASSWORD=
   PANEL_API_BASE_URL=http://panel/lteco-panel/api/storefront/v1
   PANEL_API_KEY_ID=storefront-current
   PANEL_API_SECRET=
   ```

3. Levantar el storefront.

   ```bash
   docker compose -f docker-compose.storefront.yml up -d --build
   ```

El storefront publica catálogo, modelos, contacto, agenda, calculadora de ahorro, carrito, registro, cuenta, checkout y pedidos. Las compras requieren cuenta verificada. El pago online está preparado pero deshabilitado por defecto; el flujo vigente reserva para retiro coordinado.

## Variables de entorno

Los contratos están en `.env.example` y `storefront/.env.example`.

Grupos importantes:

- Base de datos: `LTECO_DB_*`, `DB_*`, `CATALOG_DB_*`.
- URLs/base paths: `LTECO_PANEL_BASE_URL`, `LTECO_PUBLIC_BASE_URL`, `APP_URL`, `STOREFRONT_PRODUCTION_URL`.
- Seguridad: `LTECO_COMPROBANTE_SECRET`, `LTECO_MFA_ENCRYPTION_KEY`, `TRUSTED_PROXIES`, `LTECO_TRUSTED_PROXY_RANGES`.
- API panel/storefront: `LTECO_STOREFRONT_API_*`, `PANEL_API_*`, `STOREFRONT_INTERNAL_*`.
- Integraciones: WhatsApp/Meta, n8n, Telegram, web push, email, Turnstile.
- Ecommerce: `LTECO_PAYMENT_PROVIDER`, `STOREFRONT_PAYMENT_PROVIDER`, `STOREFRONT_ONLINE_PAYMENTS_ENABLED`.

No versionar `.env`, secretos reales, backups ni dumps de base de datos.

## Comandos frecuentes

```bash
# Panel + web legacy
docker compose up -d
docker compose logs -f panel
docker compose logs -f web
docker compose down

# Storefront
docker compose -f docker-compose.storefront.yml up -d --build
docker compose -f docker-compose.storefront.yml logs -f storefront_php

# Migraciones panel
scripts/migrate.sh --list
scripts/migrate.sh --dry-run
scripts/migrate.sh

# Salud operativa
scripts/operational_health.sh
scripts/operational_health.sh --alert
```

## Tests

Panel y contratos:

```bash
scripts/test-panel-fast.sh
scripts/test-panel-critical.sh
scripts/test-critical.sh
```

Storefront:

```bash
docker compose -f docker-compose.storefront.yml up -d --build
scripts/test-storefront.sh
scripts/test-storefront.sh --filter CheckoutFlowTest
```

El script `scripts/test-storefront.sh` ejecuta PHPUnit en Docker con SQLite en memoria y no toca la base comercial. Los tests críticos del panel usan variables `LTECO_TEST_DB_*`; revisar `scripts/lib/test-env.sh` y `.env.testing` antes de correrlos contra una base real.

## Seguridad y convenciones de desarrollo

- Toda acción mutante del panel debe usar `requirePost()` y `verifyCsrfOrFail()`.
- Toda salida de datos debe escapar con `h()`.
- Las operaciones sensibles deben registrar auditoría con `registrarAuditoria()`.
- Las consultas que excluyen ventas anuladas deben usar `COALESCE(EstadoVenta, 'Confirmada') <> 'Anulada'`.
- MFA TOTP es obligatorio para Superadmin y Administrador.
- La API privada del storefront usa HMAC, nonce y secretos rotables.
- No exponer endpoints internos, cron, backups, includes ni uploads ejecutables desde webroot.

## Migraciones y base de datos

El runner principal es `lteco-panel/scripts/panel_migrate.php`, envuelto por:

```bash
scripts/migrate.sh
```

Soporta:

- `--list`: lista migraciones pendientes.
- `--dry-run`: muestra qué aplicaría sin ejecutar SQL.
- `--baseline-existing`: registra baseline sobre una DB existente sin reejecutar schema histórico.
- `--allow-production`: requerido si `LTECO_ENV=production`.
- `--allow-destructive`: requerido para migraciones marcadas como destructivas.

El baseline actual vive en `database/baseline/2026_08_05_current_schema.sql`. Las migraciones incrementales viven en `database/migrations/`.

## Documentación

Punto de entrada documental:

- [docs/README.md](docs/README.md)

Documentos operativos principales:

- [docs/SETUP.md](docs/SETUP.md): setup local, Docker, migraciones y tareas comunes.
- [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md): arquitectura, roles, datos y seguridad.
- [docs/DOCUMENTACION_OFICIAL_SISTEMA.md](docs/DOCUMENTACION_OFICIAL_SISTEMA.md): referencia técnica completa.
- [docs/GUIA_USUARIO.md](docs/GUIA_USUARIO.md): guía por rol para usuarios del panel.
- [docs/MIGRACION_SERVIDOR.md](docs/MIGRACION_SERVIDOR.md): despliegue y migración de servidor.
- [docs/DB_LIFECYCLE_B5.md](docs/DB_LIFECYCLE_B5.md): ciclo de vida de base de datos.
- [docs/ECOMMERCE_OPERACION.md](docs/ECOMMERCE_OPERACION.md): operación diaria del ecommerce.
- [storefront/README.md](storefront/README.md): detalles del storefront Laravel.

## Despliegue

- Panel y web legacy se despliegan con `docker-compose.yml`.
- Storefront se despliega con `docker-compose.storefront.yml`.
- El worker `ecommerce_worker` ejecuta salud operativa y cron ecommerce cada 60 segundos.
- El storefront scheduler ejecuta `php artisan schedule:work`.
- En producción, correr migraciones con usuario migrator si está configurado y con `--allow-production`.
- Antes de publicar cambios, revisar salud operativa, permisos de storage/uploads, backups y secretos.

## Estado documental

La documentación vigente se mantiene en `docs/`. Los documentos marcados como históricos en [docs/README.md](docs/README.md) conservan contexto, pero no deben usarse como fuente operativa primaria sin contrastar con los documentos vigentes.
