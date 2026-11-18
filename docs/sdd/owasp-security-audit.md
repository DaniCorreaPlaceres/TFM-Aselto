# Auditoría OWASP · tarea 42

## Objetivo

Diagnóstico reproducible del código, web y API de Aselto en develop, con controles definidos, evidencias, puntuación transparente y priorización para acordar después la tarea 43.

## Fuera de alcance

Corregir hallazgos, certificación ASVS, pentest exhaustivo, ataques a terceros, pruebas destructivas o de carga, infraestructura de Hostinger no desplegada y lectura/publicación de secretos o documentos reales.

## Criterios de aceptación

- Identificar commit, fecha, superficie local y limitaciones. Referencias oficiales con versiones fijadas: ASVS 5.0.0, Top 10 web 2025 y API Top 10 2023, comprobadas en sus fuentes.
- Matriz de 40 controles propios (ocho grupos de cinco) definida antes de puntuar, inspirada en esas referencias; no se presenta como evaluación íntegra de ASVS ni equivalencia con sus niveles.
- Estados: cumple, no cumple, no comprobado y no aplica. Cada control incluye método y evidencia o justificación del estado.
- Publicar denominadores: cumplimiento observado C/(C+F), cobertura (C+F)/(N-NA) y resultado conservador C/(N-NA). No comprobados no cuentan como cumplidos.
- Probar permisos, aislamiento y archivos mediante workflows sintéticos; observar HTTP y configuración; revisar dependencias con fecha. No emitir puntuación sin terminar evidencias y estados.
- Hallazgos con reproducción acotada, impacto, alcance, prioridad y corrección propuesta, sin ejecutar correcciones. Producción no comprobada se distingue de un fallo local esperado.
- Informe y datos reproducibles en docs/security; resumen navegable local sin dependencias ni telemetría. Lista actualizada con evidencias reales y mejoras pendientes para decidir en tarea 43.

## Catálogo previo (40 controles)

- AUT-01..05: autenticación y contraseñas: activación y roles; hash; política de contraseñas; fuerza bruta; enumeración en recuperación.
- SES-01..05: sesiones/tokens: CSRF; atributos de cookies; expiración de sesión; validación/revocación JWT; enlaces de un solo uso con caducidad.
- ACL-01..05: autorización: documentos/archivos entre clientes; aislamiento de asesorías; restricciones de superadmin; asignación de campos sensibles; comunicación y eventos privados.
- INP-01..05: entradas/salidas: SQL parametrizado; escape de HTML; JSON estricto; redirecciones/enlaces; errores sin trazas en modo producción.
- FIL-01..05: ficheros: formatos/tamaño; almacenamiento privado; autorización y cabeceras de descarga; análisis de contenido/antimalware; recursos/quotas y adjuntos.
- CFG-01..05: configuración: secretos fuera de Git; cabeceras del navegador; HTTPS y transporte productivo; CORS/orígenes; servicios de desarrollo aislados en producción.
- OPS-01..05: operación/suministro: avisos Composer; bibliotecas de navegador; registro de eventos de seguridad; backups/restauración; cobertura y protección de CI.
- DAT-01..05: negocio/IA/datos: cierres e historial; concurrencia; extracción con revisión humana; limitación del proveedor externo; minimización y retención de datos.

## Comprobado

- Diagnóstico sobre commit `78c73e52fc5c54a5b44a603e6fb6ce8e3c11c016`, 14/11/2026. [Informe](../security/README.md), [matriz JSON](../security/audit.json), evidencias y [resumen HTML local](../security/report.html).
- 40 controles: 23 cumplen, 12 no cumplen, 5 no comprobados, ninguno no aplicable. Conservador 23/40 = 57,5 %; observado 23/35 = 65,7 %; cobertura 35/40 = 87,5 %. No equivale a certificación ASVS ni seguridad productiva.
- PHPUnit: 346 pruebas / 860 aserciones. Workflows API autenticación 54, documentos 108, comunicación 79; fichas independientes 55 y asesorías 70; trimestres 43 y eventos reales 38, correctos. Fixtures sintéticos retirados/revertidos, sin correo ni IA externos.
- Pruebas específicas de JWT/reset, tokens de uso único, contraseña común, origen del correo en memoria, logout sin CSRF, escape HTML y error 404 con Kernel prod. Identidades conservadas tras rollback. El JWT anterior se acepta después del reset en las repeticiones guardadas; también se observó una ejecución con 401, por lo que se describe como revocación no garantizada, no como acceso universal.
- Consulta Composer actual sin avisos ni abandonos (exit 0); observación HTTP y configuración. Main protegida mediante ruleset activo; sin checks obligatorios según reglas efectivas consultadas. No se modifica ninguna protección.
- 13 hallazgos con impacto, alcance y verificación propuesta. Prioridades: revocación JWT, origen canónico de enlaces y límites de intentos. Pendiente acordar y ejecutar correcciones de tarea 43.
- Generación HTML verifica 40 identificadores únicos, estados, correspondencia de hallazgos y las tres fórmulas. Revisión visual del HTML no ejecutada: el navegador integrado no permite abrir archivos locales. El resumen es local, sin dependencias ni telemetría.
- Herramienta PHP con sintaxis y PHP-CS-Fixer correctos; JSON de evidencias y sintaxis del JavaScript del informe válidos; `git diff --check -- docs` correcto. Resumen interactivo complementario sincronizado con audit.json y sintaxis TSX comprobada; no se acredita comprobación completa de tipos ni renderizado visual.
- No comprobados: HTTPS y aislamiento de servicios productivos, avisos JS, restauración y retención. No se acredita Hostinger, un nivel ASVS íntegro, penetración exhaustiva ni ausencia de todos los riesgos.
