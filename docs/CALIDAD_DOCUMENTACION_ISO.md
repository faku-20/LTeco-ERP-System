# Calidad documental ISO/SQuaRE

Estado de referencia: 2026-08-05  
Sistema relevado: repositorio `/opt/ltecobike`  
Alcance: documentación, código, tests, migraciones y runbooks versionados

Este documento evalúa el nivel de la documentación de Ltecobike y fija el
estándar mínimo para mantenerla. Usa ISO/IEC 9126 como antecedente histórico y
la familia ISO/IEC 25000 SQuaRE como marco vigente.

## Marco normativo usado

ISO/IEC 9126 fue el modelo clásico de calidad de producto software. Para este
proyecto se conserva como vocabulario histórico, pero no como referencia
principal. La referencia vigente es SQuaRE:

- ISO/IEC 25010:2023: modelo de calidad de producto ICT/software.
- ISO/IEC 25001:2014: planificación y gestión de especificación/evaluación de
  requisitos de calidad.
- ISO/IEC 25012:2008: modelo de calidad de datos, aplicable a inventario,
  ventas, clientes, auditoría, reservas y pedidos.

Fuentes consultadas:

- ISO/IEC 25010:2023: https://www.iso.org/standard/78176.html
- ISO/IEC 25010:2011, retirada y reemplazada: https://www.iso.org/standard/35733.html
- ISO/IEC 25001:2014: https://www.iso.org/standard/64787.html
- ISO/IEC 25012:2008: https://www.iso.org/standard/35736.html

## Resultado ejecutivo

Nivel documental actual: **3/5 - Operativo y mantenible, con gobernanza parcial**.

La documentación permite instalar, operar, desplegar y entender la arquitectura
principal. Hay buena cobertura de setup, seguridad, roles, migraciones, manuales
de usuario, operación ecommerce y runbooks de servidor/storefront. La brecha es
que no existía una matriz de calidad explícita: ownership documental, vigencia,
criterios ISO, trazabilidad de evidencias y reglas de actualización estaban
distribuidos entre documentos sin un control único.

La meta de calidad para el próximo cierre es **4/5 - Gestionado por evidencia**:
cada módulo crítico debe tener referencia vigente, operación, validación,
riesgos conocidos y pruebas asociadas enlazadas desde el índice.

## Evidencia relevada

| Elemento | Evidencia |
|----------|-----------|
| Documentos en `docs/` | 24 archivos Markdown/HTML, 6322 líneas relevadas |
| Referencia técnica | `docs/ARQUITECTURA.md`, `docs/DOCUMENTACION_OFICIAL_SISTEMA.md` |
| Setup y deploy | `docs/SETUP.md`, `docs/MIGRACION_SERVIDOR.md`, `docs/DB_LIFECYCLE_B5.md` |
| Usuario/operación | `docs/GUIA_USUARIO.md`, `docs/MANUAL_DE_USUARIO.md`, `docs/ECOMMERCE_OPERACION.md` |
| Storefront | `storefront/README.md`, `docs/storefront/*.md` |
| Tests versionados | 157 archivos `*Test.php` entre suite raíz y Laravel |
| Migraciones | 46 migraciones SQL/Laravel relevadas |
| Superficie HTTP/API relevada | 67 declaraciones de rutas/endpoints aproximadas |

## Escala de madurez documental

| Nivel | Estado | Criterio |
|-------|--------|----------|
| 1 | Fragmentario | Hay notas aisladas, sin índice ni validación |
| 2 | Descriptivo | Explica partes del sistema, pero no guía operación completa |
| 3 | Operativo | Permite instalar, operar y mantener el sistema con riesgo controlado |
| 4 | Gestionado | Cada módulo crítico tiene dueño, vigencia, evidencias y criterios de calidad |
| 5 | Auditable | La documentación se valida en cada release con matriz ISO, pruebas y trazabilidad completa |

## Evaluación Diataxis

| Cuadrante | Nivel | Evidencia | Brecha |
|-----------|-------|-----------|--------|
| Tutorial | 2/5 | `docs/SETUP.md` tiene setup local en 5 pasos | Falta tutorial de primer flujo real: publicar moto, comprar/reservar, revisar pedido |
| How-to | 4/5 | Setup, migración de servidor, DB lifecycle, ecommerce, staging | Hay how-tos duplicados entre manuales y docs históricas |
| Reference | 4/5 | Arquitectura, documentación oficial, variables, roles, tablas, APIs | Falta tabla única de endpoints y contratos de API privados |
| Explanation | 3/5 | Arquitectura explica decisiones legacy/POO/Laravel y trade-offs | Falta ADR o historial de decisiones vigente separado de planes históricos |

## Matriz ISO/IEC 25010 aplicada

La matriz usa las características de calidad como checklist documental. No
certifica el producto; indica si la documentación actual da evidencia suficiente
para evaluar esa característica.

| Característica | Nivel doc | Evidencia actual | Actualización obligatoria |
|----------------|-----------|------------------|---------------------------|
| Adecuación funcional | 4/5 | Módulos y flujos en `DOCUMENTACION_OFICIAL_SISTEMA.md`, manuales, tests de caracterización | Mantener flujos críticos con enlace a pruebas que los cubren |
| Eficiencia de rendimiento | 2/5 | No hay presupuesto de latencia/carga; hay health checks y cron | Documentar métricas esperadas de checkout, catálogo, panel y cron |
| Compatibilidad | 3/5 | Conviven panel, web legacy y Laravel; API HMAC documentada parcialmente | Agregar matriz de integración panel/web/storefront/n8n/WhatsApp |
| Usabilidad/interacción | 3/5 | Guía y manual de usuario cubren módulos | Agregar capturas actuales o checklist UX por rol |
| Fiabilidad | 3/5 | Backups, restore, DB lifecycle, tests críticos, health checks | Definir RTO/RPO, criterios de rollback y smoke tests post-deploy |
| Seguridad | 4/5 | CSRF, MFA, roles, sesiones, HMAC, headers, privacidad y runbooks | Mantener inventario de secretos y controles por endpoint sensible |
| Mantenibilidad | 4/5 | Arquitectura por capas, migración POO, tests, convenciones | Separar planes históricos de decisiones vigentes y deuda activa |
| Portabilidad | 3/5 | Docker Compose, migración de servidor, `.env.example` | Agregar matriz de versiones soportadas por entorno |
| Flexibilidad | 3/5 | Storefront separado, flags de entorno, pagos/envíos deshabilitados por config | Documentar feature flags comerciales y criterios de activación |

## Matriz ISO/IEC 25001 aplicada

ISO/IEC 25001 se usa acá como gestión de calidad: quién mantiene requisitos,
herramientas, evidencias y evaluación.

| Área de gestión | Estado | Regla para Ltecobike |
|-----------------|--------|----------------------|
| Responsabilidad | Parcial | Cada documento vigente debe declarar estado, alcance y fecha de referencia |
| Planificación | Parcial | Cambios de release deben actualizar `docs/README.md` y el doc afectado |
| Herramientas | Bueno | Docker, scripts, tests y health checks están versionados |
| Experiencia/criterio | Bueno | Arquitectura y manuales contienen criterios reales de operación |
| Evaluación | Parcial | Falta checklist documental formal antes de release |
| Evidencia | Parcial | Hay tests y runbooks, pero los documentos no siempre enlazan la prueba concreta |

## Norma interna de actualización

Todo cambio que afecte comportamiento visible, datos, seguridad, despliegue o
operación debe actualizar documentación en el mismo cambio.

Checklist mínimo:

1. Actualizar el documento fuente vigente, no solo un cierre histórico.
2. Cambiar la fecha de referencia si el documento deja de representar el estado anterior.
3. Enlazar evidencia: test, script, endpoint, migración o runbook.
4. Marcar riesgos conocidos en `docs/PENDIENTES.md` si quedan fuera del cambio.
5. Si cambia una API, actualizar contrato, autenticación, errores y consumidores.
6. Si cambia una migración, documentar orden, idempotencia, backup y rollback.
7. Si cambia seguridad/privacidad, actualizar controles y operación de incidentes.
8. Si cambia UX, actualizar guía/manual y capturas pendientes.

## Documentos vigentes y rol

| Documento | Rol Diataxis | Estado |
|-----------|--------------|--------|
| `docs/README.md` | Índice | Vigente; debe ser el punto de entrada |
| `docs/SETUP.md` | Tutorial / how-to | Vigente |
| `docs/ARQUITECTURA.md` | Reference / explanation | Vigente |
| `docs/DOCUMENTACION_OFICIAL_SISTEMA.md` | Reference | Vigente |
| `docs/GUIA_USUARIO.md` | How-to | Vigente |
| `docs/MANUAL_DE_USUARIO.md` | How-to / reference usuario | Vigente, necesita capturas actuales |
| `docs/MIGRACION_SERVIDOR.md` | How-to operativo | Vigente |
| `docs/DB_LIFECYCLE_B5.md` | How-to operativo | Vigente |
| `docs/ECOMMERCE_OPERACION.md` | How-to operativo | Vigente |
| `docs/storefront/*.md` | How-to / runbook | Vigente |
| `docs/PENDIENTES.md` | Registro de deuda | Vigente |
| `docs/MIGRACION_POO_PLAN.md` | Histórico / explicación | Histórico; no usar como instrucción operativa primaria |
| `docs/CIERRE_V2_PRODUCCION.md` | Evidencia histórica | Histórico |
| `docs/IA_V2_INTEGRACION.md` | Evidencia histórica | Histórico |
| `docs/STOREFRONT_CORRECCIONES_2026-07-26.md` | Evidencia histórica | Histórico |
| `docs/VALIDACION_STOREFRONT_2026-07-26.md` | Evidencia histórica | Histórico |

## Brechas prioritarias

1. Crear referencia única de endpoints panel/storefront/API privada.
2. Crear tutorial de primer flujo ecommerce completo con datos de prueba.
3. Definir objetivos medibles de rendimiento y disponibilidad.
4. Enlazar cada módulo crítico con sus tests de caracterización/integración.
5. Completar capturas o evidencias visuales actuales del manual.
6. Separar decisiones vigentes de planes/cierres históricos.
7. Agregar matriz de variables por entorno: local, staging y producción.

## Puerta de calidad documental por release

Antes de cerrar un release, validar:

```bash
php tests/run.php
scripts/test-critical.sh
docker compose -f docker-compose.storefront.test.yml run --rm storefront_test
```

Si el entorno no permite correr todos los comandos, el documento de release debe
decirlo explícitamente y listar qué evidencia reemplaza esa validación.

La documentación pasa el gate cuando:

- El índice enlaza todo documento vigente nuevo o actualizado.
- No hay contradicción entre `docs/README.md`, `docs/ARQUITECTURA.md` y
  `docs/DOCUMENTACION_OFICIAL_SISTEMA.md`.
- Las rutas, comandos y variables documentadas existen en el repo.
- Los documentos históricos están señalados como históricos.
- Cada riesgo residual tiene dueño documental: documento vigente o
  `docs/PENDIENTES.md`.
