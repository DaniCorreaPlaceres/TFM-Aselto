# Versionado del producto · tarea 44

## Objetivo

Preparar la primera versión 1.0.0 de Aselto con una fuente única de versión consultable, notas de cambios y procedimiento de etiquetado del commit final de main. Rama `feature/product-versioning` desde develop `067469a`, tras PR #61.

## Cambios planteados

- Parámetro de producto `app.version` con valor inicial `1.0.0-dev`, que identifica la preparación de 1.0.0 sin afirmar una publicación ya realizada.
- Comando `app:version`, sin consultas de negocio ni base de datos, para consultar la versión del código desplegado. No cambiar `/salud` ni añadir un endpoint público o alterar OpenAPI.
- Política MAJOR.MINOR.PATCH y versiones previas; distinguir versión del producto, contrato OpenAPI y migraciones.
- Notas de la versión 1.0.0 en preparación, con funcionalidades existentes y pendientes productivos explícitos.
- Documentar promoción a `1.0.0` mediante PR y etiqueta anotada `v1.0.0` en el commit final integrado en main, sin crearla en esta tarea.

## Fuera de alcance

Crear/publicar etiquetas o releases, integrar en main, cambiar contrato API, licencias, UI, dependencias, migraciones, despliegue o CI/CD Hostinger. Los cambios generados previos de config/reference.php quedan fuera.

## Criterios de aceptación

- Una única fuente de versión del producto y consulta por comando con resultado correcto, sin depender de MySQL.
- Versión de OpenAPI conservada e independiente; documentación distingue claramente preparación y publicación.
- Procedimiento reproducible para promover el valor final, verificar checks y etiquetar main; no etiqueta prematuramente develop ni crea release publicada.
- Solo se documentan comprobaciones realmente ejecutadas; la publicación final y el despliegue siguen pendientes.

## Comprobado

Implementado y comprobado localmente el 15/11/2026. [Criterio y publicación futura](../versioning.md), [notas en preparación](../releases/1.0.0.md).

- `app:version` devuelve `1.0.0-dev` y código 0. Se repite con una conexión MySQL deliberadamente inaccesible y obtiene el mismo resultado, sin depender de la base.
- Modo producción local: la caché anterior no conocía el comando. Tras `cache:clear --env=prod --no-debug`, devuelve `1.0.0-dev` con MySQL inaccesible. Este paso se incorpora a la documentación; no acredita despliegue productivo.
- Contenedor y **24 YAML** correctos; PHPStan correcto; PHP-CS-Fixer con PHP 8.4 sobre copia LF: **406 archivos**, sin diferencias. OpenAPI válido y sin cambios, contrato `0.6.0`.
- No se añaden pruebas unitarias que repliquen un comando de impresión simple ni se repite la batería completa de aplicación. La verificación real del comando, su independencia de MySQL y el wiring del contenedor cubren el cambio. No hay cambios visuales, de permisos, migraciones o datos.
- No se ha creado etiqueta, GitHub Release, commit en main ni despliegue. La promoción final a `1.0.0`, la fecha de publicación y la etiqueta sobre el commit aprobado de main siguen reservadas a la publicación. La tarea completa la preparación, no la publicación.

`config/reference.php` conserva cambios generados previos y queda fuera de esta tarea.
