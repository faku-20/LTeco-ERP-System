# LTecobike Storefront

## Operación ecommerce

El catálogo se alimenta del panel y revalida variante, precio y stock en servidor. El carrito admite visitantes con token efímero: sólo se persiste su hash en cookie HttpOnly/SameSite y se fusiona transaccionalmente al registrarse o iniciar sesión.

El checkout solicita dirección de facturación y actualmente reserva para retiro coordinado en Belvedere con pago en efectivo. Getnet, transferencias y envíos permanecen deshabilitados hasta su aprobación.

La publicación se refresca desde el panel con `storefront:catalog-refresh`; no requiere editar arrays de modelos. `MostrarEnWeb`, estado publicable (`Disponible` o `Sin stock`), precio y tipo de producto se validan en el endpoint privado. Las rutas `/modelos/{slug}` son dinámicas.

Tienda Laravel conectada al panel LTecobike mediante una API privada firmada. El catálogo público usa stock real; las compras requieren cuenta y correo verificado; cada pedido se registra en el panel y conserva el flujo de venta, garantía y postventa.

## Desarrollo local

```bash
docker compose -f docker-compose.storefront.yml up -d --build
docker compose -f docker-compose.storefront.test.yml run --rm storefront_test
```

La web queda en `http://127.0.0.1:8082`. Las pruebas usan SQLite en memoria y no acceden a la base comercial.

## API privada

- `GET /api/storefront/v1/catalog`
- `GET /api/storefront/v1/commercial-terms`
- reservas, pedidos, estado, privacidad y agenda bajo la misma autenticación HMAC.

Configurá `PANEL_API_BASE_URL`, `PANEL_API_KEY_ID` y `PANEL_API_SECRET`. En el panel deben coincidir con `LTECO_STOREFRONT_API_CURRENT_KEY_ID` y `LTECO_STOREFRONT_API_CURRENT_SECRET`. En producción la API debe usar HTTPS y un cache compartido para impedir replays.

## Staging y producción

- Staging: `storefront.ltecobike.shop` con `STOREFRONT_INDEXABLE=false`.
- Producción prevista: `ltecobike.shop`; activar indexación solo después de validar dominio, canonicales y sitemap.
- Sin envíos: retiro en Belvedere siempre con coordinación previa.
- Pago online desactivado. La configuración Getnet es un punto de integración, no una pasarela activa.


## Assets públicos

El layout productivo sirve directamente:

- `public/css/storefront.css`
- `public/js/storefront.js`
- `public/js/checkout.js`
- `public/js/account.js`
- `public/js/savings-calculator.js`

Aunque existen `package.json` y `vite.config.js`, las vistas productivas no usan `@vite`. La vista Laravel de ejemplo `welcome.blade.php` no forma parte del storefront público. Para publicar cambios de CSS, JavaScript, Blade o PHP hay que reconstruir los contenedores del storefront.

El logo vectorial oficial puede colocarse en `public/images/brand/logo-ltecobike.svg`; mientras no exista, el componente usa el WebP oficial disponible como fallback.
