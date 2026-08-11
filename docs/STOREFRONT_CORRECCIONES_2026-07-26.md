# Correcciones integrales del storefront — 26/07/2026

## Alcance

- Catálogo dinámico desde el panel, sin allowlist fija de slugs.
- Publicación de modelos sin imagen o descripción mediante fallback.
- Modelos publicados sin stock visibles como agotados.
- Conteo de stock por unidad física y agrupación por variante.
- Selector robusto de color y batería con precio y disponibilidad dinámicos.
- Carrito invitado con token aleatorio, hash SHA-256, cookie HttpOnly/SameSite y protección IDOR.
- Fusión transaccional del carrito invitado al registrarse o iniciar sesión.
- Registro con redirección al inicio y aviso de verificación.
- Checkout consumidor/empresa, CI y RUT uruguayos, datos incompatibles en `null`.
- Calculadora contra nafta u ómnibus urbano.
- Rediseño de carrito, Mi cuenta y contacto.
- Iconos SVG locales reutilizables y fallback seguro del logo.
- Rate limits por acción y `no-store` para carrito y páginas privadas.

## Variables nuevas o documentadas

```dotenv
TRUSTED_PROXIES=172.16.0.0/12
STOREFRONT_CONTACT_EMAIL=
STOREFRONT_CONTACT_HOURS=
STOREFRONT_CONTACT_MAP_URL=
STOREFRONT_DEFAULT_TICKET_PRICE=0
STOREFRONT_CART_COOKIE=ltecobike_guest_cart
STOREFRONT_CART_LIFETIME_MINUTES=43200
STOREFRONT_CART_MAX_QUANTITY=10
STOREFRONT_CART_MAX_UNITS_PER_ORDER=10
STOREFRONT_CART_SECURE_COOKIE=true
```

No usar `TRUSTED_PROXIES=*` en producción. El precio inicial del boleto queda en `0` hasta aprobar una tarifa y fecha de referencia. El visitante puede ingresarlo manualmente.

## Despliegue

Conservar el `.env` real del servidor; el paquete corregido no incluye secretos.

```bash
cd /opt/ltecobike

docker compose -f docker-compose.storefront.yml up -d --build \
  storefront_php storefront_scheduler storefront_nginx

docker compose -f docker-compose.storefront.yml exec \
  storefront_php php artisan migrate --force

docker compose -f docker-compose.storefront.yml exec \
  storefront_php php artisan optimize:clear

docker compose -f docker-compose.storefront.yml exec \
  storefront_php php artisan storefront:catalog-refresh
```

Después ejecutar la suite aislada:

```bash
docker compose -f docker-compose.storefront.test.yml run --rm --build storefront_test
```

## Assets

El storefront productivo sirve CSS y JavaScript directamente desde `storefront/public`; no usa Vite en el layout público. La reconstrucción Docker copia esos assets a la imagen.

## Logo SVG

No había un SVG oficial en el material recibido. Para habilitarlo, colocar el archivo oficial sin modificar en:

```text
storefront/public/images/brand/logo-ltecobike.svg
```

El sitio usa el WebP existente hasta entonces.
