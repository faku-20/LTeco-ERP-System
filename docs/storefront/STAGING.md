# Staging del storefront

## Acceso

- URL: `https://storefront.ltecobike.shop`
- Cloudflare Tunnel: `ltecobike`
- Origen local: `http://127.0.0.1:8082`
- El DNS y el ingress ya existen; no se modificó el dominio principal.

## Protección del entorno

- `APP_ENV=staging`
- `APP_DEBUG=false`
- `STOREFRONT_INDEXABLE=false`
- Header `X-Robots-Tag: noindex, nofollow, noarchive`
- `robots.txt` responde `Disallow: /`
- Pago online y envíos desactivados.

## Paso a producción

1. Cerrar la revisión funcional y legal.
2. Definir dominio canónico definitivo.
3. Configurar correo real, colas, backups y monitoreo.
4. Definir proveedor de pago y validar toda la integración en sandbox.
5. Cambiar `STOREFRONT_INDEXABLE=true`, cargar verificación de Search Console y volver a probar `robots.txt`, `sitemap.xml`, canonicales y JSON-LD.
6. No habilitar envíos mientras la operación sea solo retiro coordinado en Belvedere.
