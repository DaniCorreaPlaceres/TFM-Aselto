# Decisión operativa sobre OPcache de Hostinger

## Objetivo

Documentar la decisión expresa del usuario de conservar el despliegue actual y asumir operativamente que la acción de borrar la caché del sitio de Hostinger renueva también OPcache. Rama feature/hostinger-opcache-assumption desde develop, sin modificar el instalador.

## Fuera de alcance

Releases separadas, invalidación automática de OPcache, endpoints de operaciones, cambios de PHP, activación del CD, migraciones y publicación real.

## Criterios de aceptación

- Se distingue la suposición aceptada de una comprobación técnica.
- Se conserva la estructura actual y se identifica la acción manual en hPanel, que no realiza el workflow.
- Se mantienen pendientes la primera publicación y su verificación; no se afirma que la caché de Symfony sustituya OPcache.

## Evidencias y decisión

- El usuario comparte respuesta del proveedor: PHP web 8.4.27 y OPcache activo; validate_timestamps y revalidate_freq no visibles ni confirmados. Se describe borrado de caché del sitio/servidor y posible CDN, sin confirmación de reinicio del pool web u OPcache.
- El asistente propone releases versionadas; el usuario las rechaza y pide mantener lo existente bajo la suposición indicada.
- Se acepta esa decisión operativa sin convertirla en evidencia de invalidación. Tras publicar, el operador realizará el borrado de caché del sitio en hPanel y comprobará el comportamiento esperado de la versión; salud 200 por sí sola no identifica el código servido.
- HOSTINGER_DEPLOY_ENABLED no se modifica en esta tarea. La tarea 46 conserva pendiente su primer despliegue real. config/reference.php se mantiene fuera de alcance.
