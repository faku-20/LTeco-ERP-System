# Validación del parche del storefront — 26/07/2026

## Controles ejecutados en esta copia

- Sintaxis PHP: **321 archivos**, sin errores.
- Sintaxis JavaScript: `storefront.js`, `account.js`, `checkout.js` y `savings-calculator.js`, sin errores.
- Suite de caracterización independiente: **1.133 aserciones correctas**.
- CSS: cantidad de llaves de apertura y cierre consistente (**1.352 / 1.352**).
- Revisión estática de SQL dinámico en Laravel: no se encontraron usos de `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw` ni `statement` en `storefront/app` o `storefront/routes`.
- Los usos de Blade sin escape se limitan a rutas SVG de una allowlist interna y JSON-LD generado con flags `JSON_HEX_*`.

## Limitación del entorno de revisión

No se pudo ejecutar la suite Feature completa de Laravel en este entorno porque el archivo recibido excluía `storefront/vendor` y el PHP local no tiene las extensiones `dom`, `mbstring` y `xmlwriter`. La suite de caracterización se ejecutó con un polyfill temporal de funciones multibyte; ese archivo temporal no forma parte del proyecto.

La validación definitiva debe ejecutarse en los contenedores del servidor:

```bash
cd /opt/ltecobike
docker compose -f docker-compose.storefront.test.yml run --rm --build storefront_test
```

## Datos y secretos

El paquete entregado excluye:

- `.env`
- `.git`
- `vendor`
- `node_modules`
- logs y cachés de ejecución

Conservar el `.env` existente del servidor y revisar especialmente `TRUSTED_PROXIES`: no usar `*` en producción.
