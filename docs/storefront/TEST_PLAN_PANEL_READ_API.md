# Test Plan — Panel Storefront Read API

## Affected Pages/Routes

- `GET /api/storefront/v1/catalog`
- `GET /api/storefront/v1/commercial-terms`

## Key Interactions to Verify

- Laravel firma una solicitud y el panel la acepta con el mismo secreto.
- El catálogo agrupa unidades por modelo, batería, color, moneda y precio.
- Descripciones y galería reflejan el panel y respetan principal/orden.
- Las URLs de medios usan exclusivamente el origen configurado.
- Las condiciones comerciales coinciden con configuración, Visa y Mastercard del panel.
- `ETag` responde `304` cuando el contenido no cambió y cambia al editar contenido comercial.

## Edge Cases

- Firma ausente, alterada o calculada para otra query.
- Timestamp vencido o demasiado futuro.
- Nonce repetido y fallo del almacenamiento de nonces.
- Key ID desconocida y credencial sin alcance.
- Correlation ID ausente o inválido.
- Unidad no publicada, reservada, vendida, sin stock, sin precio, slug, descripción o imagen.
- Dos unidades iguales se agrupan; precios o monedas diferentes no se mezclan.
- Variaciones de mayúsculas/espacios en modelo y color producen una identidad determinista.
- Imagen principal ausente, galería reordenada, ruta inválida o fuera del prefijo permitido.
- Configuración comercial ausente, negativa o con IVA inválido.
- Base de datos temporalmente caída devuelve error seguro y reintentable.

## Critical Paths

```text
HMAC válido → catálogo → dos consultas → agrupación → media → dinero → 200 + ETag
HMAC válido → términos → configuración + tarjetas → versión → 200 + ETag
HMAC inválido/replay/scope → rechazo antes de ejecutar repositorios
If-None-Match vigente → 304 sin cuerpo
```

- Ejecutar suite raíz `php tests/run.php`.
- Ejecutar suite Laravel dentro del contenedor de test.
- Ejecutar solicitudes HTTP reales contra los endpoints con fixtures MySQL.
- Confirmar cero consultas N+1 y latencia p95 menor a 500 ms con catálogo representativo.

