# Plan Maestro de Migración POO — Ltecobike

> **Documento histórico.** La migración descrita aquí se cerró en junio de
> 2026. La rama operativa actual es `main`; no cambiar a
> `migracion-poo-total` para ejecutar el sistema. Para el estado vigente usar
> `docs/ARQUITECTURA.md`, `docs/SETUP.md` y `HANDOFF.md`.

> **ESTADO: CERRADA (2026-06-21).** La migración POO está **completa funcional y
> arquitectónicamente** en la rama `migracion-poo-total`. Último commit de
> migración: `00ff81c "Mover health-check de mantenimiento fuera de la vista"`.
> Suite verde (`php tests/run.php` → **823 aserciones**), `php -l` limpio, y QA
> con navegador headless PASS en Repuestos, Vehículos y Ventas/crear. Las
> páginas del panel quedaron como handlers/vistas finos que delegan en `src/`
> (`Domain` / `Application` / `Infrastructure` / `Presentation/Panel`). El único
> SQL procedural inline restante bajo `lteco-panel/` es `scripts/cleanup_test_data.php`,
> aceptado por ser un script CLI de test/mantenimiento. Lo que queda en §13.bis
> son **decisiones deliberadas**, no deuda abierta urgente. Detalle del cierre y
> de la tanda UX final en `CLAUDE.md` → "Current state".

## 1. Objetivo de la migración

El objetivo de esta migración no es “hacer POO por capricho”, sino ordenar progresivamente el sistema Ltecobike para que sea más mantenible, testeable y seguro de modificar.

El sistema original creció con páginas PHP que mezclan:

* permisos
* validación de request
* reglas de negocio
* consultas SQL
* HTML
* auditoría
* redirects
* operaciones de archivos
* lógica de stock, ventas, garantías y postventa

La migración busca separar esas responsabilidades sin reescribir todo desde cero y sin cambiar el comportamiento comercial existente.

## 2. Arquitectura objetivo

La arquitectura objetivo es una estructura POO liviana, sin framework, compatible con el sistema actual.

### 2.1. `lteco-panel/`

Las páginas PHP del panel deben quedar como handlers/vistas finas.

Responsabilidades permitidas:

* `require` de dependencias
* permisos y guards
* CSRF
* lectura de `$_GET`, `$_POST`, `$_FILES`
* validación superficial de request
* apertura/cierre de transacción si aplica
* llamada a servicios de `src/Application`
* auditoría
* redirects
* render HTML

Responsabilidades que deben salir progresivamente:

* SQL directo de escritura
* reglas de negocio complejas
* cálculos comerciales
* manipulación fuerte de estados
* lógica de stock
* lógica de garantías/services
* lógica de comisiones

### 2.2. `src/Application/`

Contiene casos de uso del sistema.

Ejemplos:

* crear venta
* anular venta
* crear vehículo
* editar vehículo
* cambiar estado de vehículo
* realizar service
* cancelar service
* guardar intervención
* actualizar estados postventa
* registrar venta distribuidor

Responsabilidades:

* orquestar repositories
* aplicar reglas de flujo
* devolver resultados claros al handler
* lanzar excepciones controladas cuando corresponde
* ser transaction-agnostic salvo decisión explícita

No debe contener:

* HTML
* redirects
* `$_POST`
* `$_GET`
* `$_FILES`
* `echo`
* headers HTTP
* auditoría directa salvo decisión puntual
* apertura de transacciones si el handler necesita controlar más operaciones alrededor

### 2.3. `src/Infrastructure/Repository/`

Contiene acceso a datos.

Responsabilidades:

* `SELECT`
* `INSERT`
* `UPDATE`
* `DELETE`
* queries legacy preservadas
* mapping simple de datos

No debe contener:

* HTML
* redirects
* auditoría
* reglas de UI
* `$_POST`
* `$_GET`
* transacciones propias
* decisiones de permisos

Regla importante:

> Los repositories no deben abrir ni cerrar transacciones. La transacción pertenece al handler o al Application Service de nivel superior.

### 2.4. `src/Domain/`

Contiene reglas puras sin base de datos.

Ejemplos:

* cálculos de venta
* comisiones
* IVA
* estados
* validaciones puras
* normalizaciones

Debe ser testeable sin DB.

### 2.5. `src/Presentation/Panel/`

Contiene soporte de presentación del panel que no debería vivir como
implementación dentro de la carpeta pública.

Responsabilidades:

* bootstrap de rutas del panel
* includes compartidos de soporte (`auth`, `helpers`, `whatsapp`, `auditoria`,
  `db`)
* includes de vista reutilizados por páginas públicas (`header`, `sidebar`,
  `footer`, `flash`)

Regla de compatibilidad:

> `lteco-panel/includes/` se mantiene como capa pública estable mediante
> wrappers finos. No se debe romper URLs, includes legacy ni configuración
> Apache/Docker mientras el panel siga publicándose bajo `/lteco-panel`.

### 2.6. `tests/`

Tipos de tests usados:

* `tests/Characterization/`: congela comportamiento y wiring sin tocar DB.
* `tests/integration/`: tests con DB real de desarrollo, siempre dentro de transacción con rollback.
* `tests/Support/`: utilidades de tests.

Regla:

> Toda migración importante debe tener wiring test y, si toca DB, integration test con rollback.

## 3. Reglas generales de migración

### 3.1. No hacer big bang

No se debe pedir:

> Migrá todo el sistema a POO.

La migración se hace por olas y bloques funcionales.

### 3.2. Mantener comportamiento idéntico

Salvo que el objetivo explícito del commit sea corregir un bug, la migración debe conservar:

* mensajes
* redirects
* permisos
* validaciones
* auditoría
* estados
* cálculos
* queries equivalentes
* orden de efectos cuando sea relevante

### 3.3. Commits separados

Cada commit debe tener un propósito claro.

Ejemplos buenos:

* `Migrar acciones simples de vehiculos a servicio`
* `Migrar operaciones de postventa a servicio`
* `Corregir evento de historial para notificacion WhatsApp`
* `Migrar creacion de vehiculos a servicio`

Ejemplo malo:

* `Migrar varias cosas`
* `Refactor general`
* `Cambios POO`

### 3.4. Bugs detectados durante migración

Si durante una migración se detecta un bug legacy:

1. No corregirlo dentro del commit de migración.
2. Preservar el comportamiento.
3. Documentarlo.
4. Corregirlo luego en commit separado.

Ejemplo ya aplicado:

* Bug: `service_historial.TipoEvento` no aceptaba `NOTIFICACION_WA`.
* Primero se migró preservando comportamiento.
* Luego se corrigió en commit separado.

### 3.5. Validación obligatoria antes de commit

Antes de commitear cualquier bloque:

```bash
docker exec ltecobike_panel php /var/www/html/tests/run.php

for t in tests/integration/*Test.php; do
  docker exec ltecobike_panel php "/var/www/html/$t" || exit 1
done

find lteco-panel src tests -name '*.php' -print0 \
  | xargs -0 -I{} docker exec ltecobike_panel php -l "/var/www/html/{}"

git diff --check
git status --short
git diff --stat
git diff --name-only
```

## 4. Estado actual de la migración

Estado actual de referencia:

* Rama del snapshot histórico: `migracion-poo-total` (la rama operativa actual es `main`)
* Último commit: `9307982 Actualizar plan maestro tras Ola H`
* Cierre técnico de Ola H: `907e0f4 Completar Ola H de infraestructura transversal POO`
* Cierre técnico de Ola G: `b3e9b49 Completar Ola G de dashboard auditoria busqueda y limpieza final`
* Cierre técnico de Ola F: `4da5378 Completar Ola F de usuarios y configuracion`
* Checkpoint de Ola F: `checkpoint-wave-usuarios-configuracion-completa-4da5378`
* Checkpoint de cierre A-B-C: `checkpoint-cierre-tecnico-olas-a-b-c-abe931f`
* Estado del working tree: **limpio y commiteado** (`git status --short` vacío).
  Olas A-I cerradas y commiteadas; la tanda UX final (paginación, acciones
  CSP-friendly, selector de repuestos lazy, QR lazy, diagramas Mermaid) y la
  extracción del health-check de mantenimiento también quedaron commiteadas y
  con QA navegador headless PASS. Ver el banner de cierre al inicio de este
  documento y `CLAUDE.md` → "Current state".

### Magnitud real del trabajo (verificado 2026-06-15)

* `lteco-panel/` tiene **100 archivos `.php`** tras retirar
  `includes/dashboard_logic.php`, que no tenía consumidores.
* `lteco-panel/includes/` queda como capa pública de wrappers finos; la
  implementación compartida del panel vive en `src/Presentation/Panel/`.
* En `distribuidores/`, C1-C7 están implementados: stock/pedidos, venta, lecturas, reportes, estado de cuenta, CRUD y limpieza final delegan en `src/`.
* `usuarios/` y `configuracion/` quedaron migrados en Ola F, incluidos CRUD,
  MFA, configuración general y mantenimiento.
* Lectura: los módulos de **escritura crítica**, el módulo financiero,
  Dashboard, Auditoría y Búsqueda están migrados. La deuda cross-module de
  cliente/comisiones de `distribuidores/nueva_venta.php` también quedó cerrada.
* Ola H cerró el SQL residual de negocio e infraestructura transversal en
  handlers/helpers: clientes/gastos de venta, importaciones/cliente en
  vehículos, login/rate limiting, ownership, auditoría, WhatsApp/cron, schema,
  tipo de cambio, imágenes y configuración pública.
* Ola I reubicó los includes compartidos del panel bajo `src/Presentation/Panel`
  manteniendo wrappers públicos en `lteco-panel/includes/` para no romper rutas.

### Tags de cierre existentes

* Vehículos + Postventa: `checkpoint-wave-vehiculos-postventa-completa-4ce1e0b`
* Distribuidores: `checkpoint-wave-distribuidores-completa-6c780fb`
* Financiera (Gastos + Importaciones + Balance): `checkpoint-wave-financiera-completa-937c2c3`
* Usuarios + Configuración: `checkpoint-wave-usuarios-configuracion-completa-4da5378`

## 5. Módulos ya migrados o muy avanzados

### 5.1. Ventas

Estado: Ola A cerrada técnicamente.

> Corrección 2026-06-15: todas las páginas de `ventas/` (`guardar`, `anular`, `crear`, `comprobante`, `detalle`, `exportar`, `index`, `whatsapp_reenviar`) delegan en servicios/repositorios.

Ya existe estructura importante:

* `VentaRepository`
* servicios de venta
* servicios de lectura/listado
* cálculo comercial
* líneas de venta
* anulación
* tests de caracterización e integración

Migrado/encaminado:

* creación de venta
* cálculo comercial
* líneas de venta
* garantías/services generados por venta
* anulación con rollback
* lectura/listado/exportación
* alcance por vendedor
* reglas de comisiones principales

Consolidación final:

* `whatsapp_reenviar.php` delega sus lecturas en `VentaQueryService`
* los bloqueos/lecturas de líneas de `guardar.php` delegan en `VentaLineasService`
* la validación de cliente ajeno para Vendedor está presente
* cliente (Ola D) y gastos (Ola E) ya migrados a servicios; las notificaciones conservan sus límites actuales

### 5.2. Vehículos

Estado: Ola B cerrada técnicamente.

Servicios actuales:

* `VehiculoEstadoService`
* `VehiculoCrearService`
* `VehiculoEditarService`
* `VehiculoImagenService`

Repository:

* `VehiculoRepository`

Migrado:

* `toggle_destacado.php`
* `toggle_web.php`
* `eliminar.php`
* `cambiar_estado.php`
* `reservar.php`
* backend principal de `crear.php`
* backend principal de `editar.php`
* gestión de imágenes dentro de `editar.php`

Consolidación final:

* `index.php` delega listado/filtros
* QR, etiquetas múltiples y escaneo delegan en `VehiculoConsultaService`
* orden web delega en `VehiculoEstadoService`
* lecturas propias de acciones y edición delegan en servicios/repositorios
* Olas D (Clientes) y E (Importaciones) ya cerradas; la lectura cross-module residual de cliente de reserva / importaciones dentro de `vehiculos/` queda como deuda menor cross-module, no bloqueante
* QA visual completa sigue siendo recomendable, pero no bloquea el cierre backend

### 5.3. Postventa

Estado: Ola B cerrada técnicamente junto con Vehículos.

Servicios actuales:

* `PostventaService`
* `PostventaIntervencionService`

Repository:

* `PostventaRepository`

Migrado:

* `vehiculos/service_realizar.php`
* `postventa/service_cancelar.php`
* `postventa/service_observacion_agregar.php`
* `postventa/marcar_notificado_wa.php`
* `postventa/actualizar_estados.php`
* `postventa/intervencion_guardar.php`

Corregido:

* `NOTIFICACION_WA` agregado al enum `service_historial.TipoEvento`

Consolidación final:

* `index.php` y `detalle.php` delegan sus lecturas
* notificaciones del batch y ownership de intervenciones delegan al servicio
* acciones, estados e intervenciones quedan cubiertos por integration tests con rollback

## 6. Módulos pendientes principales

### 6.1. Distribuidores

Estado: Ola C cerrada técnicamente.

#### C1 — Pedidos / stock asignado

Estado: cerrado.

Incluye:

* `asignar_stock.php`
* `nuevo_pedido.php`
* `pedidos_admin.php`

* `DistribuidorStockService`
* `DistribuidorPedidoService`
* `DistribuidorStockRepository`
* `DistribuidorPedidoRepository`
* tests de wiring para los tres handlers
* tests de integración con rollback para asignación, creación y resolución de pedidos

Commits:

* `c7292a5 Migrar asignacion de stock de distribuidores a servicio`
* `51cb473 Migrar nuevo pedido de distribuidores a servicio`
* `df05feb Migrar administracion de pedidos distribuidores a servicio`

#### C2 — Nueva venta distribuidor

Estado: cerrado.

`distribuidores/nueva_venta.php` delega su matemática comercial pura en:

* `src/Domain/Distribuidor/VentaDistribuidorCalculo.php`
* `tests/Characterization/DistribuidorVentaCalculoTest.php`

El cálculo extraído preserva subtotal, costo total, comisión del distribuidor,
comisión del vendedor interno, IVA incluido, total sin IVA y ganancia estimada,
con los mismos redondeos legacy.

Bloques cerrados:

* C2.1: cálculo comercial puro en `VentaDistribuidorCalculo`
* C2.2: descuento de `distribuidor_stock` y stock global de repuesto
* C2.3: persistencia legacy de `venta` y `venta_detalle`
* C2.4: facturación y vínculo del remito pendiente
* C2.5: orquestación en `DistribuidorVentaService`

Estructura creada:

* `src/Application/Distribuidor/DistribuidorVentaService.php`
* `src/Infrastructure/Repository/DistribuidorVentaRepository.php`
* `src/Domain/Distribuidor/VentaDistribuidorCalculo.php`
* `tests/integration/DistribuidorVentaServiceTest.php`
* wiring C2 ampliado en `VentaDistribuidorWiringTest`

`nueva_venta.php` conserva transacción, permisos, CSRF, auditoría, redirect y
UI. La limpieza cross-module de Ola G delegó:

* selector, alta y validación de cliente en `ClienteCrudService`
* IVA y usuario interno en `VentaCommercialService`
* comisión distribuidor y gastos de ambas comisiones en `DistribuidorVentaService`

Commits y checkpoint:

* `d66477f Extraer calculo de venta distribuidor a dominio`
* `b81c65f Migrar venta de distribuidores a servicio`
* `26be89b Actualizar plan maestro tras C2 distribuidores`
* `checkpoint-c2-distribuidor-venta-completa-26be89b`

Además, desde la Wave 1, `nueva_venta.php` ya delega los efectos de vehículo,
garantía y services en `Lteco\Application\Venta\VentaLineasService`.

#### C3 — Lecturas simples de distribuidores

Estado: cerrado.

Archivos:

* `index.php`
* `pedidos.php`
* `ventas.php`
* `busqueda.php`
* `reportes_admin.php`

Estructura creada:

* `DistribuidorConsultaService`
* `DistribuidorConsultaRepository`
* `DistribuidorConsultaWiringTest`

Commit:

* `4802d61 Migrar lecturas de distribuidores a servicios`

#### C4 — Reportes/problemas de distribuidores

Estado: cerrado.

Archivos:

* `reportar_problema.php`
* `reporte_detalle.php`

Estructura creada:

* `DistribuidorReporteService`
* `DistribuidorReporteRepository`
* `DistribuidorReporteWiringTest`
* `DistribuidorReporteServiceTest` con rollback

Commit:

* `267424c Migrar reportes de problemas de distribuidores`

#### C5 — Estado de cuenta / comisiones

Estado: cerrado.

Archivo principal:

* `estado_cuenta.php`

Estructura creada:

* `DistribuidorCuentaService`
* `DistribuidorCuentaRepository`
* `DistribuidorCuentaWiringTest`
* `DistribuidorCuentaServiceTest` con rollback

Incluye:

* selección de distribuidor
* normalización del período
* lectura y resumen de comisiones
* transición `Pendiente` / `Aprobada` / `Pagada` / `Anulada`
* preservación legacy de `FechaPago`

#### C6 — CRUD distribuidores

Estado: cerrado.

Archivos:

* `crear.php`
* `editar.php`

Estructura creada:

* `DistribuidorCrudService`
* `DistribuidorCrudRepository`
* `DistribuidorCrudWiringTest`
* `DistribuidorCrudServiceTest` con rollback

Se preservaron validaciones, defaults, mensajes, permisos, auditoría y UI.

#### C7 — Limpieza final de Distribuidores

Estado: cerrado.

Realizado:

* revisar `_common.php`
* eliminar seis helpers legacy sin consumidores
* agregar test de limpieza de `_common.php`
* documentar deuda que permanece fuera de Ola C
* ejecutar smoke HTTP autenticado para administración y distribuidor
* actualizar el plan maestro

Formalización:

* commit `6c780fb Completar Ola C de distribuidores`
* tag `checkpoint-wave-distribuidores-completa-6c780fb`
* smoke HTTP autenticado completado
* QA visual en navegador sigue recomendada; el entorno no dispone de Chromium de Playwright

#### Deuda y dependencias

La deuda cross-module de cliente final y comisiones/gastos fue cerrada durante
la limpieza de Ola G. Los query builders de stock/pedidos y el helper legacy de
comisiones fueron retirados de `_common.php`. El QA visual sigue siendo una
verificación complementaria.

### 6.2. Repuestos

Estado estimado: bajo

Pendiente:

* CRUD/listado
* stock
* búsqueda
* edición
* integración con ventas/postventa

Riesgo: medio.

### 6.3. Clientes

Estado estimado: bajo

Pendiente:

* CRUD/listado
* validaciones
* alcance por vendedor
* datos de empresa/RUT
* reutilización en ventas/distribuidores

Riesgo: medio-bajo.

### 6.4. Gastos / Importaciones / Balance

Estado: **Ola E cerrada técnicamente, commiteada y tagueada.** Ver detalle en
[Ola E — Gastos + Importaciones + Balance](#ola-e--gastos--importaciones--balance).

Migrado:

* gastos (lecturas y gestión)
* importaciones (lecturas y gestión)
* tipo de cambio aplicado asociado a gastos preservado tal cual
* balance (resumen y exportación) con dominio de cálculo puro
* reportes/exportaciones preservados

Riesgo restante: bajo. El backend financiero está encapsulado y validado con
tests de caracterización, integración (rollback) y golden numbers.

### 6.5. Usuarios / Configuración / Seguridad

Estado: **Ola F cerrada técnicamente, commiteada y tagueada.** Ver detalle en
[Ola F — Usuarios + Configuración](#ola-f--usuarios--configuración).

Migrado:

* listado, CRUD, estado y cambio de clave de usuarios
* configuración y recuperación de MFA
* configuración general de empresa y WhatsApp
* comandos y lecturas de mantenimiento para backup/download/restore
* lectura de contacto de empresa para la prueba WhatsApp

Riesgo restante: bajo en backend. QA manual/visual básico de Ola F: OK aparente.
Por tratarse de seguridad y mantenimiento, siguen recomendadas pruebas manuales
más profundas como control complementario.

### 6.6. Dashboard / Auditoría / Búsqueda

Estado: **Ola G cerrada técnicamente y commiteada.**

Migrado:

* Auditoría: filtros, opciones, conteo y paginación
* Búsqueda global: recientes, búsqueda multi-entidad, detalle y scope vendedor
* Dashboard: datasets en repository y cálculos visibles en dominio puro
* limpieza de `dashboard_logic.php` y helpers sin consumidores

Pendiente: tag de checkpoint y QA manual/visual.

## 7. Roadmap recomendado

### Ola A — Ventas

Estado: cerrada técnicamente.

Objetivo:

* ventas con servicios/repositorios/tests
* anulación segura
* cálculo comercial preservado

Estado actual: servicios/repositorios/tests cubren creación, líneas, cálculo,
listados, detalle, anulación y reenvío WhatsApp. Las dependencias de Clientes
(Ola D) y Gastos (Ola E) ya están migradas a servicios.

### Ola B — Vehículos + Postventa backend

Estado: cerrada técnicamente.

Commits principales:

* `bff7700` acciones simples vehículos
* `8570205` operaciones postventa
* `8b5d55b` fix historial WhatsApp
* `296d546` actualización estados postventa
* `7c53ee5` intervenciones postventa
* `e7885e2` creación vehículos
* `95e39c4` edición vehículos
* `b19a4a5` imágenes vehículos

Incluye también:

* listados y lecturas de Vehículos/Postventa
* QR, etiquetas, escaneo y orden web
* lecturas de edición y acciones de vehículos
* notificaciones batch y ownership de intervenciones
* checkpoint `checkpoint-wave-vehiculos-postventa-completa-4ce1e0b`

Pendiente no bloqueante: QA visual/manual integral.

### Ola C — Distribuidores

Debe dividirse así:

#### C1 — Pedidos / stock asignado

Estado: cerrado.

Incluye:

* `asignar_stock.php`
* `nuevo_pedido.php`
* `pedidos_admin.php`
* `DistribuidorStockService`
* `DistribuidorPedidoService`
* `DistribuidorStockRepository`
* `DistribuidorPedidoRepository`

Commits:

* `c7292a5 Migrar asignacion de stock de distribuidores a servicio`
* `51cb473 Migrar nuevo pedido de distribuidores a servicio`
* `df05feb Migrar administracion de pedidos distribuidores a servicio`

#### C2 — Nueva venta distribuidor

Estado: cerrado.

Incluye:

* cálculo comercial puro
* descuento de stock
* persistencia `venta` + `venta_detalle`
* facturación de remito
* `DistribuidorVentaService`
* `DistribuidorVentaRepository`
* `VentaDistribuidorCalculo`

Commits:

* `d66477f Extraer calculo de venta distribuidor a dominio`
* `b81c65f Migrar venta de distribuidores a servicio`
* `26be89b Actualizar plan maestro tras C2 distribuidores`
* checkpoint `checkpoint-c2-distribuidor-venta-completa-26be89b`

#### C3 — Lecturas simples de distribuidores

Estado: cerrado.

Archivos:

* `index.php`
* `pedidos.php`
* `ventas.php`
* `busqueda.php`
* `reportes_admin.php`

Servicios/repositorios:

* `DistribuidorConsultaService`
* `DistribuidorConsultaRepository`

Commit:

* `4802d61 Migrar lecturas de distribuidores a servicios`

#### C4 — Reportes/problemas de distribuidores

Estado: cerrado.

Archivos:

* `reportar_problema.php`
* `reporte_detalle.php`

Servicios/repositorios:

* `DistribuidorReporteService`
* `DistribuidorReporteRepository`

Commit:

* `267424c Migrar reportes de problemas de distribuidores`

#### C5 — Estado de cuenta / comisiones

Estado: cerrado.

Archivo principal:

* `estado_cuenta.php`

Servicios/repositorios:

* `DistribuidorCuentaService`
* `DistribuidorCuentaRepository`

Validación:

* wiring test
* integration test con rollback para lecturas, resumen y cambios de estado

#### C6 — CRUD distribuidores

Estado: cerrado.

Archivos:

* `crear.php`
* `editar.php`

Servicios/repositorios:

* `DistribuidorCrudService`
* `DistribuidorCrudRepository`

Validación:

* wiring test
* integration test con rollback para alta, edición y validaciones legacy

#### C7 — Limpieza final de Distribuidores

Estado: cerrado.

Realizado:

* revisar `_common.php`
* eliminar helpers legacy sin uso comprobado
* documentar deuda pendiente
* smoke HTTP autenticado del portal distribuidor y administración
* actualizar plan maestro final de Ola C

Formalización:

* commit `6c780fb Completar Ola C de distribuidores`
* tag `checkpoint-wave-distribuidores-completa-6c780fb`
* QA visual en navegador pendiente como control complementario

### Ola D — Repuestos + Clientes

Estado: cerrada técnicamente, commiteada y tagueada. Pendiente QA manual/visual.

Objetivo cumplido:

* ordenar CRUDs medianos
* sacar SQL de páginas (handlers → Application Service → Repository)
* services/repositories básicos con wiring + integration tests

#### D1 — Repuestos CRUD

Archivos migrados: `repuestos/crear.php`, `repuestos/editar.php`, `repuestos/eliminar.php`.

Estructura creada:

* `src/Application/Repuesto/RepuestoCrudService.php`
* `src/Infrastructure/Repository/RepuestoCrudRepository.php`
* `tests/Characterization/RepuestoCrudWiringTest.php`
* `tests/integration/RepuestoCrudServiceTest.php` (rollback)

El repository resuelve internamente el tipo de cambio de importación y hace los
dos INSERT/UPDATE (producto + repuesto) dentro de la transacción del handler.
Deuda preservada: `buscarPorProducto()` conserva el set de columnas legacy y **no**
trae `NumeroImportacion` (el form de edición nunca preseleccionó la importación
guardada — bug latente a corregir aparte, §3.4).

#### D2 — Repuestos lecturas

Archivo migrado: `repuestos/index.php`.

Estructura creada:

* `src/Application/Repuesto/RepuestoConsultaService.php`
* `src/Infrastructure/Repository/RepuestoConsultaRepository.php`
* `tests/Characterization/RepuestoConsultaWiringTest.php`
* `tests/integration/RepuestoConsultaServiceTest.php` (rollback)

Incluye listado con filtros (estado/búsqueda/importación), vista distribuidor
(Disponible + Stock>0), conteos de pills e importaciones, y resolución del
distribuidor de sesión.

#### D3 — Clientes CRUD

Archivos migrados: `clientes/crear.php`, `clientes/editar.php`.

Estructura creada:

* `src/Application/Cliente/ClienteCrudService.php`
* `src/Infrastructure/Repository/ClienteCrudRepository.php`
* `tests/Characterization/ClienteCrudWiringTest.php`
* `tests/integration/ClienteCrudServiceTest.php` (rollback)

Las verificaciones de unicidad (`telefonoDisponible`/`correoDisponible`) replican
`valorUnicoOpcional` y movieron su SQL al repository. La normalización con helpers
y el mensaje genérico para Vendedor se conservan en el handler.

#### D4 — Clientes lecturas

Archivos migrados: `clientes/index.php`, `clientes/detalle.php`.

Estructura creada:

* `src/Application/Cliente/ClienteConsultaService.php`
* `src/Infrastructure/Repository/ClienteConsultaRepository.php`
* `tests/Characterization/ClienteConsultaWiringTest.php`
* `tests/integration/ClienteConsultaServiceTest.php` (rollback)

Conserva textualmente las expresiones de moneda (USD→UYU con TipoCambioAplicado y
fallback) y el alcance por vendedor (EXISTS + join scope). El cómputo de resumen
del detalle (con `convertirMontoVentaAUyu`) y el filtro en memoria por tipo quedan
en el handler/servicio sin tocar reglas.

#### D5 — Clientes exportación

Archivo migrado: `clientes/exportar.php`.

Estructura creada:

* `ClienteConsultaRepository::listarParaExport()` (incluye `FechaCliente`, sin alcance)
* `tests/Characterization/ClienteExportWiringTest.php`
* `tests/integration/ClienteExportServiceTest.php` (rollback)

El armado del CSV (headers, `csvFilaSegura`, `fputcsv`) permanece en el handler.

#### Validación Ola D

* `tests/run.php`: 584 aserciones verdes
* 29/29 integration tests verdes (4 nuevos de Ola D)
* `php -l` verde en los 28 archivos cambiados/nuevos
* `git diff --check` limpio

#### Deuda y dependencias pendientes (Ola D)

* `repuestos/editar.php`: el form no preselecciona la importación guardada (bug
  legacy preservado, corregir en commit separado).
* La normalización de clientes (helpers `normalizarTextoHumano`, `telefonoValido`,
  etc.) sigue en el handler por diseño (los services no cargan helpers globales).

### Ola E — Gastos + Importaciones + Balance

Estado: **cerrada técnicamente, commiteada y tagueada.**

Commit:

* `937c2c3 Completar Ola E financiera`

Tag:

* `checkpoint-wave-financiera-completa-937c2c3`

Objetivo cumplido:

* ordenar módulo financiero (handlers → Application Service → Repository, más un Domain de cálculo puro)
* preservar reglas financieras actuales
* no mezclar con ventas/distribuidores

#### Componentes migrados

E1 — Gastos lecturas:

* `gastos/index.php`
* `gastos/exportar.php`
* `GastoConsultaService`
* `GastoConsultaRepository`

E2 — Gastos gestión:

* `gastos/guardar.php`
* `gastos/editar.php`
* `GastoCrudService`
* `GastoCrudRepository`

E3 — Importaciones lecturas:

* `importaciones/index.php`
* `ImportacionConsultaService`
* `ImportacionConsultaRepository`

E4 — Importaciones gestión:

* `importaciones/crear.php`
* `importaciones/editar.php`
* `ImportacionCrudService`
* `ImportacionCrudRepository`

E5 — Balance:

* `balance/index.php`
* `balance/exportar.php`
* `BalanceService`
* `BalanceRepository`
* `Domain/Balance/BalanceCalculo` (cálculo financiero puro)

#### Tests y validación (Ola E)

* `tests/run.php`: 651 aserciones OK
* integration: 34/34 OK (incluye los nuevos de Ola E con rollback)
* `php -l` global OK
* `git diff --check` OK
* `BalanceCalculoTest` con golden numbers para IVA 22/122, ingresos netos, utilidad y flujo de caja

#### Reglas preservadas (Ola E)

* reglas financieras actuales (no se "mejoró" el modelo)
* tipo de cambio aplicado en gastos (`TipoCambioAplicado` resuelto en el handler y pasado al repositorio; el UPDATE no lo toca)
* filtro de gastos activos (`COALESCE(Estado, 'Activo') = 'Activo'`)
* tratamiento de ventas anuladas/activas en el balance
* comportamiento de importaciones/lotes históricos (la edición no modifica `Numero`; sin auditoría/flash, igual que el legacy)
* cálculo de balance (IVA 22/122, netos, utilidad, flujo, series, redondeos)
* reportes/exportaciones (CSV de gastos y balance sin cambios)
* permisos, filtros, ordenamientos, redondeos, moneda, tipo de cambio y UI

#### Deuda y dependencias pendientes (Ola E)

* La conversión a UYU por fila y los porcentajes del balance siguen en la vista/handler porque dependen de helpers globales (`convertirAUyu`, `convertirMontoVentaAUyu`, `porcentajeSeguro`), igual que el patrón de Ola D.
* Las comisiones/gastos generados desde venta distribuidor no entraron en Ola E;
  esta deuda fue cerrada posteriormente en la limpieza cross-module de Ola G.
* QA manual/visual del módulo financiero aún pendiente como control complementario.

### Ola F — Usuarios + Configuración

Estado: **cerrada técnicamente, commiteada y tagueada.**

Commit:

* `4da5378 Completar Ola F de usuarios y configuracion`

Tag:

* `checkpoint-wave-usuarios-configuracion-completa-4da5378`

#### Componentes migrados

F1 — Usuarios lecturas/listado:

* `usuarios/index.php`
* `UsuarioConsultaService`
* `UsuarioRepository`

F2 — Usuarios CRUD/estado/cambio de clave:

* `usuarios/crear.php`
* `usuarios/editar.php`
* `usuarios/eliminar.php`
* `usuarios/toggle_activo.php`
* `usuarios/cambiar_clave.php`
* `UsuarioCrudService`
* `UsuarioRepository`

F3 — Usuarios MFA:

* `usuarios/mfa.php`
* `UsuarioMfaService`
* `UsuarioRepository`
* `mfa_verificar.php` quedó sin cambios porque no tenía SQL inline migrable; se
  preservó el flujo existente de MFA/login.

F4 — Configuración general:

* `configuracion/index.php`
* `configuracion/guardar.php`
* `ConfiguracionService`
* `ConfiguracionRepository`

F5/F6 — Mantenimiento:

* `configuracion/mantenimiento/backup.php`
* `configuracion/mantenimiento/download.php`
* `configuracion/mantenimiento/restore.php`
* `configuracion/mantenimiento/whatsapp_probar.php`
* `Domain/Mantenimiento/BackupComando`
* `ConfiguracionService` / `ConfiguracionRepository` para la lectura del
  contacto de empresa usada por la prueba WhatsApp

#### Tests y validación (Ola F)

* `tests/run.php`: 741 aserciones OK
* integration: 37 tests OK
* `php -l` global OK
* `git diff --check` OK
* warning no bloqueante preexistente en `RepuestoCrudServiceTest.php`

#### Reglas de seguridad preservadas (Ola F)

* roles, permisos y jerarquía de usuarios
* hashing de contraseñas
* MFA crypto, recovery codes y sesiones
* CSRF y auditoría
* guards de eliminación, toggle y cambio de clave
* configuración comercial, empresa y WhatsApp
* backup/download/restore sin relajar la seguridad de paths
* no se ejecutó un restore real durante los tests
* no se envió un WhatsApp real durante los tests

QA manual/visual básico de Ola F: OK aparente. No se considera una validación
manual profunda de Usuarios, MFA, Configuración y Mantenimiento.

### Ola G — Dashboard + Auditoría + Búsqueda + limpieza final

Estado: **cerrada técnicamente y commiteada. Pendiente tag y QA manual/visual.**

Commit:

* `b3e9b49 Completar Ola G de dashboard auditoria busqueda y limpieza final`

#### G1 — Auditoría

* `auditoria/index.php`
* `AuditoriaConsultaService`
* `AuditoriaRepository`
* filtros, opciones, conteo y paginación preservados

#### G2 — Búsqueda global

* `busqueda/index.php`
* `BusquedaService`
* `BusquedaRepository`
* recientes, búsqueda de vehículos/clientes/ventas/repuestos
* detalle de clientes, services y alcance por vendedor preservados

#### G3 — Dashboard

* `dashboard.php`
* `DashboardService`
* `DashboardRepository`
* `Domain/Dashboard/DashboardCalculo`
* métricas visibles, conversiones históricas, inventario, deuda, top clientes y
  últimas ventas preservados

#### G4 — Limpieza final cross-module

* retirado `includes/dashboard_logic.php` sin consumidores
* retirado helper global `obtenerUsuarioComisionDistribuidor`
* `nueva_venta.php` delega clientes en `ClienteCrudService`
* configuración comercial delegada en `VentaCommercialService`
* comisiones y gastos delegados en `DistribuidorVentaService`
* retirado `distribuidorRegistrarComision` de `_common.php`

#### Tests y validación (Ola G)

* wiring tests para Auditoría, Búsqueda y Dashboard
* dominio puro de Dashboard con golden numbers
* integration tests con rollback para G1-G3
* integración C2 ampliada para ambas comisiones y sus gastos
* `tests/run.php`: 773 aserciones OK
* integration: 40 tests OK
* `php -l` global OK
* `git diff --check` OK
* warning no bloqueante preexistente en
  `tests/integration/RepuestoCrudServiceTest.php` por `MostrarEnWeb`

### Ola H — SQL residual + infraestructura transversal

Estado: **cerrada técnicamente, commiteada. Pendiente tag y QA manual/visual.**

Objetivo: retirar el SQL inline residual de negocio y de infraestructura
transversal sin convertir el panel en framework ni mover responsabilidades HTTP.

Componentes migrados:

* `ventas/guardar.php`: cliente nuevo/existente y gastos de comisiones delegan
  en `ClienteCrudService` y `GastoCrudService`
* `vehiculos/crear.php`, `vehiculos/editar.php`, `vehiculos/reservar.php`:
  importaciones, unicidad de motor/slug, cliente de reserva, orden web,
  imágenes y empresa principal delegan en servicios/repositorios
* `login.php`, `includes/auth.php`, `includes/helpers.php`: login,
  rate limiting, ownership de vendedor, MFA recovery, schema, tipo de cambio y
  resumen mensual delegan en `SeguridadService`, `SchemaRepository`,
  `UsuarioMfaService`, `ImportacionConsultaService` y `BalanceService`
* `includes/auditoria.php`: escritura de auditoría delega en
  `AuditoriaEscrituraService`
* `includes/whatsapp.php`, `cron/whatsapp_services.php` y
  `distribuidores/_common.php`: configuración, notificaciones, ensure de
  estructura, consulta de services y teléfono de distribuidor delegan en
  `WhatsappService`

SQL procedural residual permitido:

* `configuracion/mantenimiento/index.php`: health check de MariaDB (`SELECT 1`)
* `scripts/cleanup_test_data.php`: script administrativo destructivo/dry-run
  diseñado para operar sobre DB

Tests y validación (Ola H):

* `OlaHInfraWiringTest` congela que no vuelva SQL inline a handlers/helpers y
  que el SQL residual quede limitado a los dos archivos procedurales permitidos
* `tests/run.php`: 802 aserciones OK
* integration: 40 tests OK
* `php -l` global OK
* `git diff --check` OK
* warning no bloqueante preexistente en
  `tests/integration/RepuestoCrudServiceTest.php` por `MostrarEnWeb`

### Ola I — Presentación del panel y wrappers públicos

Estado: **cerrada técnicamente en working tree; pendiente commit/tag y QA
manual/visual.**

Objetivo: separar la implementación compartida de presentación del directorio
público sin romper URLs, includes legacy, Docker ni Apache.

Decisión de arquitectura:

* `lteco-panel/` sigue siendo la capa pública del panel.
* No se mueve el DocumentRoot ni se elimina `/lteco-panel`, porque hoy Apache
  redirige a `/lteco-panel/login.php` y Docker monta esa carpeta como superficie
  pública.
* `lteco-panel/includes/*.php` queda como wrapper fino hacia `src/`.
* La implementación real vive en `src/Presentation/Panel/Support` y
  `src/Presentation/Panel/View/Includes`.

Componentes reubicados:

* `src/Presentation/Panel/bootstrap.php`
* `src/Presentation/Panel/Support/auth.php`
* `src/Presentation/Panel/Support/helpers.php`
* `src/Presentation/Panel/Support/whatsapp.php`
* `src/Presentation/Panel/Support/auditoria.php`
* `src/Presentation/Panel/Support/db.php`
* `src/Presentation/Panel/View/Includes/header.php`
* `src/Presentation/Panel/View/Includes/sidebar.php`
* `src/Presentation/Panel/View/Includes/footer.php`
* `src/Presentation/Panel/View/Includes/flash.php`

Compatibilidad preservada:

* `lteco-panel/includes/auth.php`
* `lteco-panel/includes/helpers.php`
* `lteco-panel/includes/whatsapp.php`
* `lteco-panel/includes/auditoria.php`
* `lteco-panel/includes/db.php`
* `lteco-panel/includes/header.php`
* `lteco-panel/includes/sidebar.php`
* `lteco-panel/includes/footer.php`
* `lteco-panel/includes/flash.php`

Tests y validación (Ola I):

* `OlaHInfraWiringTest` actualizado para validar que los wrappers públicos
  delegan a `src/Presentation/Panel` y que la implementación movida sigue sin
  SQL inline.
* `tests/run.php`: 812 aserciones OK
* integration: 40 tests OK
* `php -l` global OK
* smoke de wrapper `lteco-panel/includes/helpers.php`: OK
* warning no bloqueante preexistente en
  `tests/integration/RepuestoCrudServiceTest.php` por `MostrarEnWeb`

## 8. Definición de terminado por bloque

Un bloque se considera terminado solo si cumple:

* `git status --short` limpio antes de empezar
* alcance definido
* archivos permitidos/prohibidos claros
* tests escritos o actualizados
* `tests/run.php` verde
* todos los integration tests verdes
* `php -l` verde
* `git diff --check` limpio
* `git diff --stat` revisado
* commit con mensaje claro
* tag de checkpoint si el bloque fue importante

## 9. Formato de prompt para Claude/Codex

Cada bloque debe pedirse con este formato:

```text
Estamos en /opt/ltecobike, rama migracion-poo-total.

Objetivo:
[describir bloque]

Base actual:
Verificar primero:
git status --short
git log --oneline -10
git tag --points-at HEAD

HEAD esperado:
[commit esperado]

Tag esperado:
[tag esperado]

Archivos permitidos:
- [...]

Archivos prohibidos:
- [...]

Objetivo técnico:
página PHP → Application Service → Repository

Reglas:
- No cambiar UI visual.
- No cambiar reglas de negocio.
- No cambiar nombres de campos.
- Mantener permisos, CSRF, auditoría, redirects y mensajes.
- No abrir/cerrar transacciones dentro de repositories.
- No commitear.

Trabajo esperado:
1. Leer archivos.
2. Proponer plan antes de editar.
3. Escribir tests primero si corresponde.
4. Migrar lógica.
5. Validar.

Validación obligatoria:
docker exec ltecobike_panel php /var/www/html/tests/run.php

for t in tests/integration/*Test.php; do
  docker exec ltecobike_panel php "/var/www/html/$t" || exit 1
done

find lteco-panel src tests -name '*.php' -print0 \
  | xargs -0 -I{} docker exec ltecobike_panel php -l "/var/www/html/{}"

git diff --check

Al final mostrar:
git status --short
git diff --stat
git diff --name-only
git ls-files --others --exclude-standard
```

## 10. Flujo de trabajo real con IA (Claude → Codex → ChatGPT)

El flujo es **secuencial por agotamiento de tokens**, no por tipo de tarea: se trabaja con Claude Code hasta el límite diario, luego se sigue con Codex, y ChatGPT cierra/supervisa. Para perder el menor tiempo posible, el objetivo es **minimizar los traspasos** y que cada herramienta reciba el estado sin tener que reconstruir contexto.

### Principio: el plan + la memoria son el handoff

Cada herramienta arranca leyendo, en este orden:

1. `docs/MIGRACION_POO_PLAN.md` (este archivo) — la línea maestra.
2. `git log --oneline -10`, `git tag --points-at HEAD`, `git status --short` — dónde quedó.
3. La memoria `project_migracion_poo` (si la herramienta la tiene) — fase detallada y gotchas.

> Regla de oro: **nadie deja trabajo a medias sin dejarlo verde o claramente marcado.** Antes de quedarte sin tokens, dejá tests verdes o un bloque RED documentado, y anotá en el commit message / en el plan dónde seguir.

### Fase 1 — Claude Code (mientras haya tokens)

Aprovechar el contexto amplio para lo más caro de razonar:

* abrir cada bloque nuevo (leer archivos, proponer plan antes de editar).
* diseño de services/repositories y TDD multi-archivo (escribir tests primero).
* la migración de lógica en sí (página → Application Service → Repository).
* dejar el bloque **verde y sin commitear** (esperar aprobación del usuario para commitear).

Cuando se acerque el límite: terminar el slice actual hasta verde, o si no llega, dejar el test en RED con un comentario `// TODO handoff:` describiendo el siguiente paso exacto.

### Fase 2 — Codex (cuando se acaban los tokens de Claude)

Continuar **sin rediseñar**: tomar el slice donde quedó.

* cerrar diffs iniciados, completar la extracción pendiente.
* corregir/ajustar tests hasta verde.
* refactors mecánicos medianos dentro del mismo bloque.
* correr la validación obligatoria (§3.5) y dejar el `git status`/`git diff --stat` a la vista.

No abrir bloques grandes nuevos con Codex si el diseño no está ya fijado por Claude.

### Fase 3 — ChatGPT (supervisión y decisión)

* revisar el diff/outputs y decidir si se commitea.
* armar el prompt del siguiente bloque con el formato de §9.
* detectar riesgo y mantener la línea maestra (actualizar este plan).
* interpretar errores de validación.

### Atajos para no perder tiempo

* **Un bloque = un commit.** No mezclar (ver §3.3). Si quedó a mitad entre dos herramientas, igual debe cerrar como un único commit coherente.
* **Siempre validar antes de cambiar de herramienta** (§3.5): así la siguiente IA arranca sobre verde, no debugueando lo anterior.
* **No re-leer todo el repo en cada handoff**: este plan + el último commit + el tag son suficientes para retomar.
* Modelos chicos / grep solo para búsquedas y tareas mecánicas; nunca para macro-migración.

## 11. Próxima decisión recomendada

Ola I está cerrada técnicamente en el working tree. La próxima decisión es
formalizarla con commit/tag después de revisar el diff y luego hacer QA
manual/visual básico de los flujos tocados por includes compartidos:
login, navegación con header/sidebar/footer, permisos, flashes, auditoría y
WhatsApp.

Base actual:

```bash
HEAD 9307982
tag en HEAD: ninguno
working tree con Ola I sin commitear
A, B, C, D, E, F, G, H e I cerradas técnicamente
```

## 12. QA manual recomendado para la ola actual

### Vehículos

Probar:

* crear vehículo interno
* crear vehículo publicado en web
* crear vehículo con slug manual
* slug duplicado
* subir imágenes
* editar datos principales
* editar publicación web
* marcar imagen principal
* eliminar imagen
* mover imagen
* cambiar estado
* reservar
* ocultar/eliminar
* destacar/quitar destacado
* mostrar/ocultar web

### Postventa

Probar:

* entrar a listado postventa
* entrar a detalle
* realizar service
* cancelar service
* agregar observación
* marcar WhatsApp notificado
* guardar intervención sin repuesto
* guardar intervención con repuesto
* verificar descuento de stock de repuesto
* actualización automática de estados

### Distribuidores C1-C4

Probar si se decide hacer cierre manual:

* asignar vehículo y repuesto a un distribuidor
* crear pedido nuevo
* aprobar pedido
* rechazar pedido
* verificar stock interno y stock distribuidor
* verificar remito pendiente generado por asignación/pedido
* registrar venta distribuidor con vehículo
* registrar venta distribuidor con varias unidades de repuesto
* verificar importes, IVA, comisiones y ganancia calculados
* verificar que persisten los efectos legacy todavía no migrados
* recorrer listados de stock, pedidos, ventas y búsqueda
* enviar un reporte de problema sin imagen
* enviar un reporte de problema con imagen
* revisar el reporte desde administración
* cambiar el estado a Revisado y Resuelto

### Gastos / Importaciones / Balance (Ola E)

QA manual/visual aún recomendado:

* listar gastos y aplicar filtros por categoría, método y rango de fechas
* verificar totales en UYU y total del mes
* exportar gastos a CSV con y sin filtros
* crear un gasto (verificar tipo de cambio aplicado guardado)
* editar un gasto (verificar que no cambia el tipo de cambio aplicado)
* listar importaciones y verificar conteo de vehículos
* crear una importación nueva
* editar una importación (verificar que el número no cambia)
* abrir el balance y revisar resumen, IVA, ingresos netos y utilidad
* aplicar filtros de fecha en el balance
* exportar el balance a CSV
* verificar que las ventas anuladas no aparecen en el balance

### Usuarios + Configuración (Ola F)

QA manual/visual básico de Ola F: OK aparente.

La recorrida realizada fue básica. No implica que se hayan ejecutado en
profundidad todos los siguientes controles, que siguen recomendados:

* listar usuarios con cada rol y verificar la jerarquía visible
* crear y editar usuarios dentro de los permisos de cada rol
* cambiar contraseña propia y de otro usuario autorizado
* activar/desactivar usuarios y verificar los guards
* intentar eliminar usuarios permitidos y protegidos
* configurar MFA, validar login MFA y usar recovery codes
* regenerar recovery codes y desactivar MFA
* revisar y guardar configuración de empresa/comercial/WhatsApp
* generar un backup y verificar descarga autorizada
* verificar validaciones de path en download/restore
* revisar la pantalla de restore sin ejecutar una restauración real
* probar la pantalla WhatsApp sin realizar un envío real

Estimación aproximada de migración global:

* Ventas: 98%+ (Ola H cerró clientes/gastos residuales del handler)
* Vehículos backend: 98%+ (Ola H cerró clientes/importaciones y helpers residuales)
* Postventa backend: 95%+ (Ola B cerrada)
* Distribuidores: 98%+ (Ola H cerró helper WhatsApp y unicidad residual)
* Repuestos: 90%+ (Ola D cerrada; CRUD + lecturas delegan en servicios)
* Clientes: 90%+ (Ola D cerrada; CRUD + lecturas + export delegan en servicios)
* Gastos/Importaciones/Balance: 95%+ (Ola H cerró tipo de cambio y resumen mensual residual)
* Usuarios/Configuración: 95%+ (Ola H cerró login, MFA recovery, schema y configuración pública residual)
* Dashboard/Auditoría/Búsqueda: 95%+ (Ola H cerró escritura de auditoría)
* Infraestructura transversal: 95%+ (login/rate limit, ownership, WhatsApp,
  schema, helpers SQL residuales delegan en `src/`)
* Presentación pública del panel: 95%+ (Ola I dejó `lteco-panel/includes/`
  como wrappers y movió la implementación compartida a `src/Presentation/Panel`)

Estimación total del proyecto:

* Migración POO funcional/profesional: 98% - 99%
* Migración casi total: 90% - 95%
* Reestructuración full tipo framework: no recomendada por ahora

## 13.bis. Pendientes deliberados (decisiones, NO deuda abierta urgente)

Con la migración cerrada, lo que sigue son **trade-offs decididos a propósito**,
no tareas pendientes que bloqueen nada. **No corregir dentro de un commit de
migración** (ver §3.4); si alguna se aborda, es un cambio aparte con su propio
alcance.

* **Red de paridad `tests/Characterization/ReferenciaCalculoVenta.php`:** réplica línea-por-línea del cálculo legacy; se mantiene como red temporal y se borra cuando ya no aporte sobre `ReglasComerciales`.
* **`venta_detalle` de venta distribuidor:** el repository C2 preserva el INSERT legacy sin columna explícita `Moneda`; la DB aplica su default `UYU`. Comportamiento legacy deliberado.
* **Costo legacy de `venta_detalle`:** `CostoUnitario` sigue usando `precioMinimo`; C2 lo preserva deliberadamente y cualquier corrección debe ser un bugfix separado.
* **CSP `style-src 'unsafe-inline'`:** deuda intencional reservada para un hardening de frontend futuro (sacarla requiere refactor de estilos inline en ~29 archivos).
* **Efecto preexistente (no es regresión del refactor):** poner un vehículo en `Oculto` limpia `DestacadoWeb`, y reactivarlo a `Disponible` no restaura el destacado. Vive en `VehiculoEstadoService` y es anterior al refactor de botones CSP-friendly.

### Saldado en el cierre

* **Health-check de mantenimiento:** el `SELECT 1` inline de `configuracion/mantenimiento/index.php` se movió a `Lteco\Infrastructure\Repository\MantenimientoRepository` (SQL) + `Lteco\Application\Mantenimiento\EstadoSistemaService` (orquestación). La vista ya no tiene SQL inline. Único SQL procedural residual bajo `lteco-panel/`: `scripts/cleanup_test_data.php` (script CLI de test).

## 14. Criterio de prioridad

Priorizar primero módulos con escritura crítica:

1. ventas
2. vehículos
3. postventa
4. distribuidores
5. repuestos
6. clientes
7. gastos/importaciones
8. usuarios/configuración
9. dashboard/auditoría/búsqueda

No priorizar lectura/reportes antes que escrituras críticas salvo que bloqueen desarrollo.

## 15. Advertencias

* La rama `migracion-poo-total` está siendo ejecutada por Docker porque los bind mounts apuntan a `/opt/ltecobike`.
* `main` sigue estable en Git, pero no es lo que el contenedor está sirviendo mientras la carpeta esté en esta rama.
* Las migraciones de base de datos no dependen de la rama Git.
* Todo cambio de esquema debía intentar ser idempotente y separado de refactors cuando fuera posible; el estado actual contiene migraciones que requieren revisión antes de reejecutarse.
* No enviar zips a otra IA incluyendo `.env`, `.git`, `storage/`, backups o uploads reales.
