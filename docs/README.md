# Documentación LTeco ERP System

Estado de referencia: 2026-08-05. El repositorio contiene dos superficies públicas: la web legacy y el nuevo storefront Laravel. Para despliegues, consultar primero [MIGRACION_SERVIDOR.md](MIGRACION_SERVIDOR.md) y [storefront/README.md](../storefront/README.md).

Nivel documental actual: **3/5 - operativo y mantenible, con gobernanza parcial**. La evaluación y la norma de actualización están en [CALIDAD_DOCUMENTACION_ISO.md](CALIDAD_DOCUMENTACION_ISO.md), basada en ISO/IEC 9126 como antecedente e ISO/IEC 25010:2023 + ISO/IEC 25001:2014 como marco vigente SQuaRE.

## Para desarrolladores

| Documento | Descripción |
|-----------|-------------|
| [SETUP.md](SETUP.md) | Setup local, comandos Docker, scripts de mantenimiento, migraciones y páginas nuevas |
| [ARQUITECTURA.md](ARQUITECTURA.md) | Stack actual, arquitectura del panel, web legacy y storefront, roles, datos y seguridad |
| [DOCUMENTACION_OFICIAL_SISTEMA.md](DOCUMENTACION_OFICIAL_SISTEMA.md) | Referencia técnica completa: flujos, tablas, estados, variables de entorno |
| [CALIDAD_DOCUMENTACION_ISO.md](CALIDAD_DOCUMENTACION_ISO.md) | Evaluación de madurez documental, matriz ISO/SQuaRE, brechas y gate de release |

## Para usuarios del panel

| Documento | Descripción |
|-----------|-------------|
| [GUIA_USUARIO.md](GUIA_USUARIO.md) | Guía por rol: cómo usar cada módulo, buenas prácticas, problemas frecuentes |
| [MANUAL_DE_USUARIO.md](MANUAL_DE_USUARIO.md) | Manual detallado del panel legacy por módulo |

## Otros

- `ORDENAMIENTO_ARCHIVOS.md` — criterio de ubicación de archivos
- `PENDIENTES.md` — decisiones técnicas pendientes y known issues de QA
- `MIGRACION_SERVIDOR.md` — procedimiento vigente de migración/despliegue
- `DB_LIFECYCLE_B5.md` — backup, restore, migraciones, deploy y ciclo de vida de base de datos
- `ECOMMERCE_OPERACION.md` — operación diaria del ecommerce, cron, seguridad y pendientes productivos
- `storefront/` — runbooks específicos del ecommerce Laravel
- `assets/fotos-reales-catalogo-ltecobike.png` — fotos del catálogo

## Documentos históricos

Estos archivos conservan evidencia, cierres o planes previos. No usarlos como fuente operativa primaria sin contrastar con los documentos vigentes:

- `MIGRACION_POO_PLAN.md`
- `CIERRE_V2_PRODUCCION.md`
- `IA_V2_INTEGRACION.md`
- `STOREFRONT_CORRECCIONES_2026-07-26.md`
- `VALIDACION_STOREFRONT_2026-07-26.md`

---

Servicios locales:
- Panel: `http://127.0.0.1:8081/lteco-panel/`
- Web pública: `http://127.0.0.1:8080/public-web/`
- Storefront ecommerce: `http://127.0.0.1:8082/`
