# Migración de servidor

Esta guía sirve para mover LTecobike a otro servidor sin depender de memoria operativa. El repositorio contiene el código y la estructura, pero no contiene datos productivos, secretos ni uploads reales.

## Resumen

Para reconstruir el sistema en otro servidor necesitás:

1. Código del repositorio.
2. Dos archivos de entorno reales:
   - `.env`
   - `storefront/.env`
3. Dumps de base de datos:
   - base del panel, definida por `LTECO_DB_NAME`;
   - base propia del storefront, definida por `DB_DATABASE` en `storefront/.env`.
4. Uploads reales:
   - `lteco-panel/uploads/vehiculos/`
5. Directorio de backups externo:
   - `/opt/backups/ltecobike`
6. Configuración de proxy/DNS/HTTPS.

No alcanza con clonar el repositorio si no se migran datos, secretos y uploads.

## 1. Preparar servidor nuevo

Instalar:

- Git
- Docker
- Docker Compose v2
- MariaDB/MySQL, si la base corre en el host
- Nginx/Cloudflare Tunnel/reverse proxy, según el despliegue final

Crear estructura base:

```bash
sudo mkdir -p /opt
sudo mkdir -p /opt/backups/ltecobike
sudo chown -R "$USER":"$USER" /opt/backups/ltecobike

cd /opt
git clone <URL_DEL_REPO> ltecobike
cd /opt/ltecobike
```

## 2. Copiar archivos de entorno reales

Desde el servidor actual, copiar de forma segura:

```text
/opt/ltecobike/.env
/opt/ltecobike/storefront/.env
```

Al servidor nuevo:

```text
/opt/ltecobike/.env
/opt/ltecobike/storefront/.env
```

No subir estos archivos a Git.

### Variables críticas que deben conservarse

Si se migran datos reales, estas claves deben mantenerse iguales. Si cambian, pueden quedar datos ilegibles o integraciones rotas:

- `LTECO_COMPROBANTE_SECRET`
- `LTECO_MFA_ENCRYPTION_KEY`
- `APP_KEY` del storefront
- `CUSTOMER_BLIND_INDEX_KEY`
- `AUDIT_HASH_KEY`
- `LTECO_STOREFRONT_API_CURRENT_SECRET`
- `PANEL_API_SECRET`
- `STOREFRONT_INTERNAL_SECRET`
- credenciales SMTP/Resend
- credenciales WhatsApp Cloud API
- claves VAPID, si se usan notificaciones push

Actualizar solo las URLs/dominios si cambia el host público:

- `LTECO_PANEL_PUBLIC_URL`
- `LTECO_PUBLIC_URL`
- `LTECO_VERIFY_PUBLIC_URL`
- `LTECO_MEDIA_PUBLIC_URL`
- `LTECO_STOREFRONT_PUBLIC_URL`
- `APP_URL`
- `STOREFRONT_PRODUCTION_URL`
- `PANEL_API_BASE_URL`
- `TRUSTED_PROXIES`

## 3. Exportar bases desde servidor actual

Ejemplo con MariaDB/MySQL en host. Ajustar nombres y usuarios según `.env` real.

Panel:

```bash
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  -u <USUARIO_PANEL> -p \
  <DB_PANEL> > /opt/backups/ltecobike/panel_migracion_$(date +%Y%m%d_%H%M%S).sql
```

Storefront:

```bash
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  -u <USUARIO_STOREFRONT> -p \
  <DB_STOREFRONT> > /opt/backups/ltecobike/storefront_migracion_$(date +%Y%m%d_%H%M%S).sql
```

Comprimir:

```bash
gzip /opt/backups/ltecobike/*_migracion_*.sql
```

Copiar los `.sql.gz` al nuevo servidor, por ejemplo a:

```text
/opt/backups/ltecobike/
```

## 4. Copiar uploads

Copiar imágenes reales de vehículos:

```bash
rsync -aH --info=progress2 \
  /opt/ltecobike/lteco-panel/uploads/vehiculos/ \
  usuario@SERVIDOR_NUEVO:/opt/ltecobike/lteco-panel/uploads/vehiculos/
```

Validar en destino:

```bash
find /opt/ltecobike/lteco-panel/uploads/vehiculos -type f | wc -l
```

## 5. Crear bases y usuarios en servidor nuevo

Ejemplo:

```sql
CREATE DATABASE lteco_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE lteco_storefront CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'lteco_user'@'%' IDENTIFIED BY 'CAMBIAR';
CREATE USER 'storefront_app'@'%' IDENTIFIED BY 'CAMBIAR';
CREATE USER 'storefront_reader'@'%' IDENTIFIED BY 'CAMBIAR';

GRANT ALL PRIVILEGES ON lteco_db.* TO 'lteco_user'@'%';
GRANT ALL PRIVILEGES ON lteco_storefront.* TO 'storefront_app'@'%';
GRANT SELECT ON lteco_db.* TO 'storefront_reader'@'%';

FLUSH PRIVILEGES;
```

Los usuarios y contraseñas deben coincidir con `.env` y `storefront/.env`.

## 6. Importar dumps

```bash
gunzip -c /opt/backups/ltecobike/panel_migracion_YYYYMMDD_HHMMSS.sql.gz \
  | mysql -u <USUARIO_PANEL> -p <DB_PANEL>

gunzip -c /opt/backups/ltecobike/storefront_migracion_YYYYMMDD_HHMMSS.sql.gz \
  | mysql -u <USUARIO_STOREFRONT> -p <DB_STOREFRONT>
```

Si se parte de base vacía y no de dump completo, aplicar migraciones SQL en orden:

```bash
cd /opt/ltecobike
for f in database/migrations/*.sql; do
  mysql -h 127.0.0.1 -u <USUARIO_PANEL> -p <DB_PANEL> < "$f"
done
```

Para storefront, si no se importó dump:

```bash
docker compose -f docker-compose.storefront.yml run --rm storefront_php php artisan migrate --force
```

## 7. Levantar servicios

Panel, web legacy y worker:

```bash
cd /opt/ltecobike
docker compose up -d --build
```

Storefront:

```bash
docker compose -f docker-compose.storefront.yml up -d --build \
  storefront_php storefront_scheduler storefront_nginx
```

Limpiar cache de Laravel:

```bash
docker compose -f docker-compose.storefront.yml exec -T storefront_php php artisan optimize:clear
```

## 8. Configurar reverse proxy/DNS

Los contenedores exponen solo loopback:

- panel: `127.0.0.1:8081`
- web legacy: `127.0.0.1:8080`
- storefront: `127.0.0.1:8082`

El reverse proxy externo debe publicar HTTPS hacia esos puertos según corresponda.

Después de cambiar dominios, revisar:

- certificados TLS;
- registros DNS;
- Cloudflare Tunnel, si aplica;
- `APP_URL` y URLs públicas en ambos `.env`;
- webhook público de WhatsApp si cambia el dominio del panel.

## 9. Validaciones post-migración

### Contenedores

```bash
docker compose ps
docker compose -f docker-compose.storefront.yml ps
```

### Sintaxis/config básica

```bash
docker compose exec -T panel php -l /var/www/html/lteco-panel/index.php
docker compose -f docker-compose.storefront.yml exec -T storefront_php php -l routes/web.php
```

### Health HTTP local

```bash
curl -I http://127.0.0.1:8081/lteco-panel/
curl -I http://127.0.0.1:8082/
curl -I http://127.0.0.1:8082/modelos
curl -I http://127.0.0.1:8082/terminos
```

### Cron ecommerce

```bash
docker compose exec -T panel php /var/www/html/lteco-panel/cron/ecommerce.php --dry-run
```

### Catálogo e imágenes

Abrir storefront y verificar:

- modelos visibles;
- SL/Q8 con imágenes;
- variantes color/batería;
- stock correcto.

### Panel

Verificar:

- login;
- MFA de administradores;
- ventas;
- stock;
- pedidos web;
- mantenimiento/backups;
- WhatsApp config.

### Correo y WhatsApp

Enviar pruebas controladas:

- prueba SMTP desde mantenimiento;
- reserva web en efectivo;
- venta web con tarjeta simulada;
- aviso interno por email;
- aviso interno por WhatsApp.

## 10. Checklist antes de cortar al servidor nuevo

- [ ] Backup reciente validado.
- [ ] Dumps copiados al nuevo servidor.
- [ ] Uploads copiados.
- [ ] `.env` y `storefront/.env` copiados o recreados.
- [ ] Claves de cifrado preservadas.
- [ ] Bases importadas.
- [ ] Docker build OK.
- [ ] Panel responde.
- [ ] Storefront responde.
- [ ] Catálogo con imágenes.
- [ ] Cron ecommerce dry-run OK.
- [ ] SMTP validado.
- [ ] WhatsApp validado.
- [ ] DNS/HTTPS configurado.
- [ ] `STOREFRONT_INDEXABLE` revisado según staging/producción.
- [ ] Prueba de reserva/compra completada.

## 11. Qué no copiar ni versionar

No subir a Git:

- `.env`
- `storefront/.env`
- backups `.sql`, `.sql.gz`
- `deletion-backups/`
- logs;
- dumps de base;
- credenciales o tokens;
- uploads reales si contienen datos operativos no pensados para versionar.

Sí copiar al servidor nuevo, pero fuera de Git:

- `.env` reales;
- dumps;
- uploads reales;
- backups operativos.

