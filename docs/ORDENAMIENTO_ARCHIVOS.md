# Ordenamiento de archivos

La estructura actual también incluye `storefront/`, una aplicación Laravel
separada del panel y de `public-web/`. Sus vistas, rutas, comandos Artisan y
pruebas permanecen dentro de esa aplicación; no mover lógica del storefront a
`shared/` ni a `lteco-panel/` salvo que exista una decisión explícita de
integración.

## Regla base

- `lteco-panel/`: pantallas, acciones, assets y endpoints internos que requieren sesion o permisos del panel.
- `public-web/`: catalogo publico, contacto y verificacion publica de comprobantes.
- `shared/`: configuracion, conexion, helpers de dominio y logica usada por panel y web publica.
- `database/migrations/`: cambios versionados de base de datos.
- `docker/`: configuracion de Apache/servicios.
- `docs/`: documentacion, pendientes y criterios de arquitectura.
- `storage/`: datos operativos locales no versionados, como logs y backups.

## Artefactos locales no operativos

- Binarios, agentes locales, logs de herramientas externas y utilidades sin
  origen verificado no forman parte del runtime de Ltecobike. Deben permanecer
  sin permiso de ejecucion, fuera de Git y fuera de despliegues.
- `ventoagent` y `ventoagent.log` se consideran artefactos locales ajenos al
  sistema: no se ejecutan, no se versionan y no son requisito para panel,
  storefront, worker, backup ni migraciones.

## Confirmado

- El scanner QR interno pertenece al panel: `lteco-panel/vehiculos/scan.php`.
- El lector `html5-qrcode` queda solo en `lteco-panel/assets/vendor/html5-qrcode/`.
- `public-web/qr-cam.php` y `public-web/scanner-interno.php` fueron eliminados.
- La logica de token de comprobantes queda compartida en `shared/comprobante_verificacion.php`.

## Mantener por ahora

- `public-web/verificar-comprobante.php`: sigue siendo una ruta publica funcional para validar comprobantes con token.
- `lteco-panel/vehiculos/etiqueta.php` y `lteco-panel/vehiculos/etiqueta_doble.php`: se conservan como redirecciones legacy hacia `lteco-panel/vehiculos/etiqueta_multi.php`.

## Requiere confirmacion antes de mover

- Cambiar URLs publicas ya emitidas en QR, comprobantes o mensajes de WhatsApp.

## Criterio para futuras rutas

- Si lo usa un cliente sin login, no debe depender del panel.
- Si modifica datos internos o requiere permisos, debe estar en `lteco-panel/`.
- Si lo consumen panel y web publica, debe ir a `shared/`.
- Si una URL ya pudo haber sido impresa o enviada por WhatsApp, debe mantenerse o migrarse con redireccion explicita.
