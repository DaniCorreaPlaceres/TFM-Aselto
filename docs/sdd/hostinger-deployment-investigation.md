# Incidencia previa al primer despliegue automático

## Objetivo

Recuperar la estructura pública correcta e identificar la publicación concurrente antes de ejecutar el primer CD autorizado. Sin modificar datos ni configuración privada del hosting desde el asistente.

## Fuera de alcance

Releases separadas (rechazadas por el usuario), migraciones manuales, cambio de secretos, eliminación sin revisar de archivos y cambios en protecciones de GitHub.

## Criterios de aceptación

- Distinguir el flujo SSH preparado de otros mecanismos que escriban en public_html.
- Recuperar salud/acceso y bloqueo de archivos internos, conservando el estado privado.
- No publicar mediante el PR principal hasta resolver la incidencia y documentar los resultados.

## Comprobado

- El usuario autoriza activar el despliegue y publicar el footer. PR 70 integrado con checks correctos; interruptor inicialmente cambiado a true.
- PR 71 creado de develop a main. GitHub exige actualizar la rama; se crea e integra PR 72 de sincronización main→develop con comprobaciones y paquete correctos. PR 71 queda actualizado y con checks correctos; no se integra todavía.
- Antes de lanzar el job de producción se consulta la web: portada 403, salud/acceso 404, composer.json y config/services.yaml 200, .git/HEAD 403. No se consultan valores privados ni se afirma acceso a claves o credenciales.
- No se ha ejecutado aún el job SSH de publicación de esta ronda. La causa del cambio de públicos no se considera demostrada; no se atribuye automáticamente al usuario ni a una integración concreta.
- Por la incidencia se devuelve HOSTINGER_DEPLOY_ENABLED a false. La API de hooks del repositorio no devuelve webhooks; esto no descarta aplicaciones/integraciones o acciones del hosting.
- Pendientes: listado SSH actual de public_html, revisión del Git nativo en hPanel, recuperación pública y verificación antes de retomar PR 71.
- Listado SSH recibido: public_html contiene el repositorio completo, incluidos .git/.github, src, config, tests, composer, .env de base, vendor y var; no contiene index.php ni .htaccess en su raíz. La carpeta var listada tiene 0777. Los archivos muestran fecha 18/11/2026 20:41. Se confirma la disposición pública incorrecta, no la causa exacta ni exposición de credenciales reales (no se inspeccionan contenidos privados).
- Recuperación propuesta: desactivar la publicación Git nativa en hPanel, conservar íntegramente public_html bajo private/backups con raíz 0700 y crear una raíz pública nueva con recursos de private/aselto/public, bootstrap del ZIP conocido y .htaccess verificado a 0644. Validar ruta, existencia e integridad antes de mover; no eliminar el checkout ni tocar la base, .env.local/JWT/var de private/aselto. Pendiente de ejecución por el usuario.
- Recuperación ejecutada según salida SSH: ZIP original verifica OK; checkout completo conservado en private/backups/public_html-git-20261118T210107Z, con raíz 0700. Archivos públicos repuestos desde la instalación privada, bootstrap del paquete verificado y .htaccess del backup con 0644. Curl posterior a /salud devuelve 200. Falta confirmación explícita de que el despliegue Git nativo ha quedado desactivado antes de reanudar el PR de publicación.
- Verificación HTTP posterior: portada/salud/acceso 200, composer.json/config/services.yaml 404, .git/HEAD 403 y API me sin autenticar 401. El usuario confirma explícitamente que la publicación Git nativa está desactivada.
- Reanudación autorizada: interruptor devuelto a true y PR 71 integrado con todos los checks correctos, sin bypass, en main 3633342b5d3d25f07cc2d22f424f1848a8ec63be. Ejecución GitHub 44397469824 en curso. Todavía no se declara completado el despliegue ni la invalidación de OPcache.
- Resultado final: ejecución 44397469824 y sus ocho jobs completados correctamente, incluido Publicar en Hostinger. Log remoto del 18/11/2026 21:05:48 UTC confirma migraciones ya al día (Version20261114050000) y esquema sincronizado; a las 21:05:51 UTC confirma despliegue verificado del SHA 3633342b5d3d25f07cc2d22f424f1848a8ec63be.
- Copia privada generada por el instalador: private/backups/deploy-3633342b5d3d25f07cc2d22f424f1848a8ec63be-20261118T210540Z-1256618. No se han leído ni descargado sus contenidos ni probado restauración de esta copia concreta.
- HTTP independiente posterior: inicio, salud y acceso 200; API me sin autenticar 401; composer.json/config/services.yaml 404. El HTML de inicio contiene «Daniel Correa Placeres · Versión 1.0.0-dev». Se acredita el footer nuevo servido por web, no una invalidación general de OPcache. La acción manual de caché en hPanel y la comprobación visual del usuario siguen sin confirmación en esta ronda.
- HOSTINGER_DEPLOY_ENABLED continúa true por autorización del usuario. No se relanza la publicación al recuperar la consulta interrumpida por el límite de herramientas: solo se consulta la ejecución ya terminada. Incidencia pública recuperada y primera publicación SSH completada; no se crea etiqueta ni release final.
- Confirmación visual posterior del usuario: «Confirmado se ve» tras solicitar borrado de caché del sitio y recarga completa. Se acredita que ve el footer publicado; no confirma por separado la ejecución del borrado en hPanel ni demuestra invalidación general de OPcache.
