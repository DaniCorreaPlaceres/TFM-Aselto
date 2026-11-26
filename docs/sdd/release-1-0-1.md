# Publicación del parche 1.0.1

## Objetivo

Preparar el parche solicitado por el usuario el 26/11/2026 y abrir el PR de publicación a main con autopublicación activa. Incluye la corrección del listado de mensajes del PR #77. Rama `feature/release-1-0-1` desde develop, en copia de trabajo aislada.

## Cambios planteados

- Sincronizar main en la preparación; cambiar la fuente única de versión a 1.0.1 y redactar notas del parche.
- Conservar v1.0.0 y su commit sin modificaciones. Contrato API y migraciones sin cambios por este parche.
- Comprobar versión y footer, integrar la preparación mediante PR a develop con checks correctos y abrir el PR de develop a main.
- Tras el merge de main, verificar CI/despliegue, footer, salud y listado real de mensajes antes de declarar resuelta la incidencia productiva. Etiquetar el commit final solo después de verificarlo.

## Fuera de alcance

Cambiar permisos, mensajes almacenados, base de datos, dependencias, protecciones del remoto o despliegue Git nativo de Hostinger. No mezclar la documentación de pruebas o las evidencias de 1.0.0 que siguen en otras ramas. No cerrar automáticamente la revisión final 47.

## Criterios de aceptación

- Fuente única y footer identifican 1.0.1; notas describen la corrección y su verificación pendiente.
- PR protegido de preparación y PR a main con el código correcto, sin push directo a main. Interruptor de despliegue comprobado.
- No afirmar que la corrección está en Hostinger antes del merge y despliegue; conservar evidencia diferenciada de pruebas locales y productivas.

## Estado inicial

PR #77 mergeado como `57bcb941f3010e0c8424609afb63d43723d91c25`. Sus seis checks y paquete de producción son correctos. `HOSTINGER_DEPLOY_ENABLED=true` comprobado. Publicación de 1.0.1 y validación visual productiva pendientes.
