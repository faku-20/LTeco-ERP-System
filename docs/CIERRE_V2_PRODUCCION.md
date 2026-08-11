# Cierre V2 para producción

> Documento de cierre histórico del 2026-07-11. Describe la validación V2 del
> panel y la web legacy; no sustituye la documentación operativa actual del
> storefront ecommerce ni el procedimiento de `docs/MIGRACION_SERVIDOR.md`.

Fecha de validación: 2026-07-11  
Rama: `main`  
Alcance: `/opt/ltecobike`

## Backup

- Archivo: `/opt/backups/ltecobike/lteco_db_2026-07-11_18-53-38.sql.gz`
- Tamaño: 47048 bytes.
- SHA-256: `505f0d303d4659b2a71f861fbbe7325aba311d6a72a9686b69c7d070c7317d76`.
- `gzip -t`: aprobado.
- Encabezado SQL: MariaDB dump de `lteco_db_poo`, legible.

## Validaciones automáticas

- `docker exec -i ltecobike_panel php /var/www/html/tests/run.php`: 986 aserciones aprobadas.
- `VentaAnulacionTest.php`: 11 aserciones aprobadas.
- `VentaAnulacionWiringTest.php`: 10 comprobaciones aprobadas.
- `php -l`: 368 archivos de `src/`, `lteco-panel/` y `tests/` sin errores.
- `git diff --check`: aprobado.

Las pruebas de anulación validaron dominio, transacción, rollback, auditoría y delegación al servicio. No se anuló ni modificó ninguna venta real.

## Permisos por rol

Se ejecutaron solicitudes autenticadas con sesiones temporales, sin persistir usuarios ni modificar la base.

| Módulo | Superadmin | Administrador | Vendedor | Distribuidor |
|---|---:|---:|---:|---:|
| Dashboard | 200 | 200 | 403 | 403 |
| Comercial/Stock/Administración/Automatizaciones | 200 | 200 | 403 | 403 |
| Agenda y Alertas | 200 | 200 | 200 | 403 |
| Ventas y Clientes | 200 | 200 | 200 | 403 |

Además, el Inicio del Distribuidor no contiene cards, enlaces ni scripts de Agenda o Alertas.

## Smoke funcional

Todas estas rutas respondieron HTTP 200, con contenido y sin errores PHP visibles:

- Ventas, detalle y comprobante.
- Vehículos y QR.
- Repuestos.
- Clientes y detalle.
- Gastos y balance.
- Postventa.
- Importaciones.
- Mantenimiento y backups.

Las comprobaciones fueron de solo lectura. No se crearon ventas, clientes, gastos, services ni movimientos de stock.

## QA visual

- Dashboard, Ventas, Comercial, Agenda, WhatsApp, Administración y Automatizaciones verificados a 375 px.
- Sin desborde horizontal global en las ocho pantallas comprobadas.
- Botón `Atrás` presente en todas las pantallas operativas verificadas.
- Dashboard comprobado también a 1440 x 900.
- Dark mode, sidebar, cards y controles flotantes conservaron su presentación.

## IA, n8n y notificaciones

- Contenedor `ltecobike_n8n_lan` activo con política `unless-stopped`.
- `http://192.168.50.170:5678/healthz`: HTTP 200, estado `ok`.
- `visita_agendada` y `visita_hora_confirmada`: activos, con URL configurada.
- Últimos envíos registrados de visitas: HTTP 200.
- Eventos de automatización pendientes al cierre: 0.
- Web Push comprobado en Android real, incluida recepción fuera del panel.

Los demás webhooks permanecen desactivados porque no tienen destino ni reglas de negocio acordadas. Activarlos sin esa definición podría generar acciones incorrectas.

## Riesgos aceptados

- WhatsApp/Meta no permite reconstruir mensajes históricos que nunca fueron entregados y guardados por el panel.
- Los webhooks no definidos deberán habilitarse individualmente cuando exista un flujo acordado y probado.
- La deuda de CSP con `style-src 'unsafe-inline'` no bloquea este cierre y queda como hardening futuro.

## Resultado

No se detectaron bloqueos funcionales para el alcance validado. V2 queda apta para continuar en producción con `main` como rama de referencia.

## Limpieza posterior al cierre

Ejecutada el 2026-07-11 con producción activa y sin modificar datos de negocio.

- Se eliminaron copias históricas completas de V2 y V3, dumps intermedios, dumps vacíos y el paquete manual antiguo.
- Se eliminaron snapshots locales de migración, copias antiguas de `.env` y temporales Ltecobike de `/tmp`.
- Se limpiaron 878,4 MB de caché de compilación Docker sin tocar imágenes, contenedores ni volúmenes activos.
- Se revisó el código versionado: no se detectaron copias `.bak`, `.old`, `.orig` ni módulos duplicados aptos para borrar.
- Se conservaron uploads reales, logs activos, `vendor`, rutas legacy documentadas y configuración local de herramientas.

Backups conservados:

- Base final: `/opt/backups/ltecobike/lteco_db_2026-07-11_18-53-38.sql.gz`.
- n8n: `/opt/backups/ltecobike/n8n_before_web_push_20260710_230933/workflows.json`.

Validación posterior: backup final íntegro, suite de 986 aserciones aprobada, panel HTTP 200 y n8n HTTP 200.
