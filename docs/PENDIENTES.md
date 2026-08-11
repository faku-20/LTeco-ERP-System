# Pendientes

## Cierre histórico de migración POO (2026-06-21)

La migración POO que se trabajó en `migracion-poo-total` quedó **cerrada funcional y
arquitectónicamente** (último commit de migración `00ff81c`, suite 823
aserciones verde, `php -l` limpio, QA navegador headless PASS en Repuestos,
Vehículos y Ventas/crear). Detalle en `CLAUDE.md` → "Current state" y en
`docs/MIGRACION_POO_PLAN.md` (banner de cierre + §13.bis). La rama de trabajo
vigente es `main`; esta sección no indica una rama que deba ejecutarse.

Lo que sigue abierto **no es deuda de migración urgente**, son decisiones
deliberadas que se abordan, si acaso, como cambios separados con su propio
alcance:

- **`ReferenciaCalculoVenta`**: red de paridad temporal en `tests/`; retirar cuando no aporte sobre `ReglasComerciales`.
- **`venta_detalle` distribuidor**: mantiene comportamiento legacy a propósito (`Moneda` default `UYU`, `CostoUnitario = precioMinimo`); cambiarlo es un bugfix separado.
- **CSP `style-src 'unsafe-inline'`**: deuda intencional para un hardening de frontend futuro (~29 archivos con estilos inline).
- **Efecto preexistente (no regresión)**: poner un vehículo en `Oculto` limpia `DestacadoWeb` y reactivarlo no lo restaura (`VehiculoEstadoService`).
- **SQL procedural residual aceptado**: solo `lteco-panel/scripts/cleanup_test_data.php`, por ser script CLI de test.

---

## Reescritura de rutas publicas

- Antes de mover URLs publicas ya emitidas por QR, comprobantes o WhatsApp, definir redirecciones explicitas.
- Confirmar cualquier cambio que afecte rutas visibles para clientes o distribuidores.

---

## Revisión de código (2026-05-30)

### Código duplicado / deuda técnica

**`dbTieneColumna()` eliminado del panel**
- Código de compatibilidad para migraciones ya aplicadas.
- Ya se eliminaron los chequeos redundantes de ventas, clientes, balance, dashboard, distribuidores, usuarios, gastos, login, configuración y WhatsApp contra el esquema confirmado de producción.
- La función muerta del panel fue removida de `includes/helpers.php`.
- La web pública conserva `dbTieneColumnaPublic()` para tolerar despliegues parciales del catálogo/verificación.

---

## QA visual cerrado (2026-07-11)

### Tablas anchas en ventas/dashboard

- Se verificaron Dashboard, Comercial, Stock, Ventas, Administración y Automatizaciones en escritorio y 375 px.
- Se verificaron WhatsApp, Asistente IA, Acciones IA, Agenda, Alertas y n8n sin desborde horizontal global ni errores persistentes de consola.
- Las tablas anchas conservan desplazamiento horizontal dentro de su contenedor sin romper el layout de la página.
- Agenda, Alertas, IA acciones y WhatsApp usan fichas apiladas en 320-375 px; escritorio conserva tablas completas.
- Resultado del cierre: health score 100/100 para el alcance probado y 986 aserciones aprobadas.

---

## Cierre de producción V2 (2026-07-11)

- Backup final creado y validado en `/opt/backups/ltecobike/lteco_db_2026-07-11_18-53-38.sql.gz`.
- Suite completa: 986 aserciones aprobadas dentro del contenedor.
- Anulación: 11 aserciones de dominio y 10 comprobaciones de cableado aprobadas sin tocar ventas reales.
- Lint: 368 archivos PHP sin errores de sintaxis.
- Matriz de permisos validada para Superadmin, Administrador, Vendedor y Distribuidor.
- Smoke de solo lectura aprobado para ventas, comprobante, QR, stock, clientes, gastos, balance, postventa, importaciones y mantenimiento.
- QA visual aprobado en escritorio y 375 px, sin desborde horizontal en las pantallas críticas.
- Web Push fue comprobado en un Android real con recepción fuera del panel.
- n8n responde `200` en `/healthz`; los webhooks de visita están activos y sus últimos envíos finalizaron en HTTP 200.

No quedan bloqueos funcionales conocidos para el alcance de cierre. Los webhooks sin destino o reglas acordadas permanecen desactivados deliberadamente.
