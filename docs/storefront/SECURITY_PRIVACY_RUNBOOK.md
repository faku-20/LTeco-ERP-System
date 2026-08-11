# Seguridad, privacidad y operación del storefront

## Antes de producción

1. Ejecutar `php artisan storefront:doctor`; todos los controles deben quedar en `OK`.
2. Usar HTTPS, `APP_DEBUG=false`, cookies seguras y sesiones cifradas.
3. Configurar claves independientes de al menos 32 caracteres para `APP_KEY`, `CUSTOMER_BLIND_INDEX_KEY`, `AUDIT_HASH_KEY` y cada dirección de la API HMAC.
4. Validar SMTP y recién entonces habilitar cuentas/correos.
5. Mantener pagos y envíos desactivados hasta completar sus integraciones y pruebas propias.
6. Revisar contractualmente a los proveedores que traten datos y documentar dónde se almacenan.
7. Completar la inscripción de las bases de datos y la revisión legal de la política ante la URCDP cuando corresponda.

## Salud y monitoreo

- `GET /health/live`: confirma que el proceso HTTP responde.
- `GET /health/ready`: comprueba las bases propia y de catálogo; devuelve solamente `ok` o `unavailable`.
- `php artisan storefront:heartbeat`: confirma la ejecución del scheduler.
- Los errores incluyen un `X-Correlation-Id` sin exponer trazas ni credenciales.

## Solicitudes de privacidad

La cuenta permite descargar una copia en JSON después de validar nuevamente la contraseña y presentar solicitudes de acceso, corrección, oposición o supresión. El vencimiento operativo se calcula a cinco días hábiles. La supresión nunca borra automáticamente pedidos o comprobantes: requiere revisión de identidad, obligaciones fiscales, garantía, seguridad y defensa de derechos.

Referencias oficiales:

- https://www.gub.uy/unidad-reguladora-control-datos-personales/personas
- https://www.gub.uy/unidad-reguladora-control-datos-personales/politicas-y-gestion/obligaciones
- https://www.gub.uy/unidad-reguladora-control-datos-personales/comunicacion/publicaciones/guia-proteccion-datos-personales-para-empresas-especial-micro-pequenas-1

## Retención

`php artisan storefront:privacy-maintenance` es siempre una simulación. Para eliminar registros técnicos vencidos se requieren simultáneamente `PRIVACY_MAINTENANCE_ENABLED=true` y `--execute`. No habilitar hasta aprobar una política formal de conservación.

## Incidentes

1. Preservar logs y correlaciones sin copiar contraseñas, tokens ni datos completos de pago.
2. Rotar las credenciales afectadas y revocar sesiones si corresponde.
3. Determinar alcance, datos involucrados, titulares y ventana temporal.
4. Documentar contención, recuperación y comunicaciones.
5. Evaluar con el responsable legal la comunicación a titulares y URCDP.
