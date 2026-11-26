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

## Verificación de publicación · 26/11/2026

- Preparación integrada mediante PR #78; PR #79 mergeado en main a las 19:10:24 UTC. Commit de publicación: `01af889526befe2db5b2f3bcf2fef5e5c58c3225`.
- [Ejecución 45881627289](https://github.com/DaniCorreaPlaceres/TFM-Aselto/actions/runs/45881627289): seis checks, paquete y job SSH correctos. Despliegue verificado a las 19:12:54 UTC.
- Copia privada previa registrada: `private/backups/deploy-01af889526befe2db5b2f3bcf2fef5e5c58c3225-20261126T191239Z-1085594`. No se ha ensayado la restauración de esta copia concreta.
- Migraciones ya al día en `Version20261118010000`, esquema sincronizado, caché prod limpiada antes y después; comando productivo devuelve `1.0.1`.
- Comprobación externa posterior: home HTTP 200 y footer `Versión 1.0.1`; `/salud` devuelve estado y base de datos correctos.
- El usuario responde «Ok verificado» a la instrucción de comprobar el footer y el listado como cliente y asesoría. Se registra como verificación manual comunicada por el usuario, sin afirmar observación directa del asistente de las pantallas autenticadas. La prueba de envío/descarga de adjuntos en ambos sentidos sigue pendiente.
- Etiqueta anotada `v1.0.1` publicada sobre el commit exacto de main comprobado. `v1.0.0` permanece intacta. No se ha creado una GitHub Release adicional.

La tarea 47 continúa: retomar adjuntos de mensajes y las demás comprobaciones pendientes. Estas evidencias no acreditan recuperación de adjuntos desde copia, capacidad, cierre completo de permisos o vaciado efectivo de OPcache.

## Adjuntos de mensajes · confirmación posterior

El usuario confirma como correcto el recorrido guiado en Hostinger 1.0.1: mensaje del cliente con archivo ficticio, apertura desde la asesoría, respuesta con otro adjunto y apertura desde el cliente. Se registra envío, descarga y apertura en ambos sentidos como comprobación manual comunicada por el usuario. No constituye observación directa del asistente ni prueba de restauración de esos archivos desde una copia. Quedan las solicitudes, la matriz completa de permisos y los demás controles pendientes de la tarea 47.

## Solicitudes · confirmación posterior

El usuario confirma como correctos los cuatro pasos guiados en Hostinger: creación desde la asesoría de una solicitud pendiente, respuesta del cliente sin resolverla automáticamente, resolución por admin/gestor y consulta desde el cliente del estado resuelto con autor y fecha. Evidencia manual comunicada por el usuario. Este recorrido no demuestra por sí solo el rechazo de una petición de resolución manipulada por un cliente ni completa la matriz de permisos. Siguen pendientes los demás controles de la tarea 47.

## Rechazo y corrección documental · confirmación posterior

El usuario confirma expresamente el recorrido guiado con documento ficticio pendiente de un trimestre abierto: rechazo motivado por la asesoría, consulta del motivo y corrección/reenvío del mismo registro por el cliente, validación posterior por la asesoría e historial del rechazo y acciones posteriores conservado, sin duplicar el documento. Resultado manual comunicado por el usuario; no se considera una prueba exhaustiva de concurrencia o de todas las combinaciones de permisos.

## Exportaciones filtradas · confirmación posterior

El usuario comunica como correcta la descarga y apertura de Excel y CSV sobre un listado de prueba filtrado, con coincidencia de registros y acentos/importes correctos. No se ha confirmado que el conjunto abarcase varias páginas: esa variante queda pendiente, sin atribuir a producción la cobertura completa de paginación que existe en las pruebas automatizadas.

## Aislamiento web entre cuentas · confirmación posterior

El usuario dispone de otro cliente de prueba y una segunda asesoría. Tras la instrucción de abrir los enlaces de un documento, su archivo y una conversación con adjunto desde ambas identidades y comprobar su exclusión de los listados, responde «Correcto, todos bloqueados». Se registra como resultado manual comunicado del recorrido de denegación web, sin atribuir códigos HTTP exactos que no se han facilitado ni observación directa del asistente. No acredita por sí solo el enlace directo del adjunto de mensajes, todas las mutaciones o la matriz API. Queda el superadmin y el resto del alcance de la tarea 47.

## Exclusión fiscal del superadmin · confirmación posterior

El usuario confirma el bloqueo desde superadmin de los mismos enlaces de documento, archivo y conversación, y la ausencia de acceso a documentos, cifras trimestrales y mensajes desde los detalles administrativos. Resultado manual comunicado en el chat, sin códigos HTTP exactos ni inspección directa del asistente. No equivale a comprobar todas las rutas o mutaciones de la API.

La revisión final 47 sigue abierta: matriz API completa, acceso directo a adjuntos de mensajes ajenos, exportación filtrada de varias páginas si no se ha ensayado, recuperación de justificantes/adjuntos desde copia, capacidad y límites efectivos, cabeceras/proxy y evidencias de cron/alertas con umbral. Se mantiene la copia externa aplazada y la limitación de OPcache aceptada.

## API del cliente · confirmación posterior

Prueba guiada mediante Swagger UI, con inicio de sesión y autorización JWT realizados por el usuario sin compartir credenciales ni token. El usuario responde «Todo bien» a las tres consultas: `GET /api/v1/me` devuelve 200 con la identidad del cliente, consulta de documento propio devuelve 200 y consulta de documento de otro cliente devuelve 404 sin datos. Resultado manual comunicado; no se considera completada la matriz API de asesoría, superadmin, archivos, conversaciones o mutaciones.

## API de asesoría · confirmación posterior

El usuario confirma como correctas las cuatro consultas guiadas con token de admin o gestor: identidad y asesoría propias en `GET /api/v1/me` (200), documento compartido de su cliente en `GET /api/v1/advisory/documents/{id}` (200), documento de la segunda asesoría (404 sin datos) y descarga de su archivo mediante `/file` (404 sin descarga). No se han compartido tokens ni contraseñas. El papel concreto usado no se ha especificado; este resultado no se atribuye separadamente a admin y gestor ni completa las mutaciones de la API.

## API del superadmin · confirmación posterior

El usuario confirma los cuatro puntos guiados con token de superadmin: identidad propia en `GET /api/v1/me` (200 y rol superadmin), listado de documentos de asesoría bloqueado (403), consulta de documento concreto y su archivo por las rutas de asesoría bloqueadas (403 sin contenido/descarga), y listado de conversaciones bloqueado (403). Resultado comunicado por el usuario, sin tokens compartidos. No acredita por sí solo todas las rutas de cliente, trimestres o mutaciones.

## API de conversaciones y adjuntos · confirmación posterior

Con token de cliente y registros ficticios, el usuario confirma los cuatro resultados: conversación propia en `GET /api/v1/conversations/{id}` (200), descarga de su adjunto por `/attachments/{fileId}` (200 y archivo correcto), conversación de otro cliente (404 sin datos) y adjunto de esa conversación ajena (404 sin descarga). Verificación manual comunicada, sin credenciales compartidas. No se amplía este resultado a todos los papeles, escrituras, lectura concurrente o recuperación de archivos desde copia.

## API de lectura de trimestres · confirmación posterior

El usuario confirma los resultados guiados sobre el año/trimestre de los registros ficticios: cliente consulta su trimestre mediante `GET /api/v1/quarters/{year}/{quarter}` (200 con sus cifras); admin o gestor consulta el de su cliente por la ruta de asesoría (200); ese token consulta un cliente de la segunda asesoría (404); superadmin consulta las rutas de cliente y asesoría (403 sin cifras). Evidencia manual comunicada. No acredita las mutaciones de cierre/reapertura ni todos los ámbitos históricos; el papel concreto de equipo usado no se atribuye simultáneamente a admin y gestor.

## API de cambios no autorizados · confirmación posterior

El usuario confirma los tres intentos guiados y que no se guarda ningún cambio: cliente intenta validar por `POST /api/v1/advisory/documents/{id}/validate` con `{}` (403); cliente intenta resolver una solicitud propia pendiente mediante `POST /api/v1/conversations/{id}/resolve` con `{}` (404 y solicitud pendiente); segunda asesoría intenta añadir texto a una conversación ajena mediante `POST /api/v1/conversations/{id}/messages` con cuerpo válido (404 y sin mensaje añadido). Resultado manual comunicado. Son casos concretos de denegación, no una afirmación de cobertura exhaustiva de mutaciones.

## API de cierre y reapertura · confirmación posterior

Con token de admin o gestor y un trimestre ficticio abierto sin pendientes ni documentos sin fecha, el usuario confirma: consulta de versión/huella actuales; cierre por la ruta de asesoría con esos valores (200 y closed true); repetición con los valores anteriores (409, sin alterar el cierre); reapertura enviando la versión devuelta por el cierre (200, closed false e historial conservado). Verificación manual comunicada por el usuario, sin atribuir el papel concreto utilizado a ambos papeles ni afirmar pruebas de carreras simultáneas.

## Recuperación y cron · decisiones posteriores

- El usuario indica «saltamos este paso» al proponer una copia reciente y recuperación separada de justificantes/adjuntos. Este ensayo se aplaza por decisión expresa; no se ha creado una nueva base ni realizado una restauración. La recuperación inicial de un original ya documentada no sustituye esta prueba. Se mantiene también la copia externa aplazada previamente.
- Para los cron de retención y alertas, tras pedir la fecha/hora y salida de la última ejecución automática en hPanel, el usuario responde que están correctos. Se registra su confirmación general, sin inventar fechas, salidas exactas o códigos de salida no proporcionados. No demuestra entrega de alertas con umbral ni borrado real de registros antiguos.

## Cabeceras públicas y archivos internos · comprobación directa

Peticiones HTTPS de lectura realizadas por el asistente a `/`, `/entrar`, `/api/v1/me` sin token y `/salud`: HTTP 200, 200, 401 y 200 respectivamente. Todas incluyen `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, Permissions-Policy restrictiva y CSP. Las páginas HTML tienen la política completa con nonce distinto entre ambas respuestas y conservan la excepción documentada de estilos inline; Hostinger añade `upgrade-insecure-requests`. No se acredita CSP en todas las vistas autenticadas por estas peticiones.

Home, acceso y respuesta API llevan `Cache-Control: no-store, private`; salud devuelve `must-revalidate, max-age=0, private`. No se observa `Strict-Transport-Security` en las cuatro respuestas: HSTS queda como mejora pendiente, sin modificar configuración del hosting ni recalcular la auditoría histórica.

Acceso público a `/.env`, `/.env.local` y `/.git/HEAD` devuelve 403; `/composer.json` y `/config/services.yaml`, 404. Solo se registran códigos y cabeceras seleccionadas, sin guardar cuerpos de archivos internos, cookies o credenciales. La lectura no demuestra todos los comportamientos del proxy ni los límites de capacidad.

## Configuración PHP · confirmación general

Tras solicitar `memory_limit`, `max_execution_time`, `upload_max_filesize` y `post_max_size` de hPanel, el usuario responde «Todo bien». Se registra su revisión general; no se han proporcionado esos cuatro valores en esta ronda y no se inventan ni se consideran comprobados numéricamente. No se ha ejecutado una prueba de carga ni de concurrencia productiva.

## Cierre aceptado de la revisión final 47

La ronda comprueba los recorridos funcionales seleccionados de web/API, aislamiento, mensajería y adjuntos, solicitudes, rechazo/corrección, exportaciones filtradas y cierre/reapertura, con los resultados comunicados por el usuario descritos arriba. El asistente comprueba directamente CI/despliegue, versión pública, salud, cabeceras y denegación de archivos internos. Las pruebas anteriores de vinculación, histórico, cifras y bloqueo de cierre están recogidas en las evidencias de 1.0.0; no se reatribuyen a una observación directa del asistente.

El usuario acepta expresamente el 26/11/2026 cerrar la revisión del TFM con los límites expuestos. Se marca terminada la tarea 47 como revisión documentada, sin afirmar conformidad OWASP, preparación comercial, cobertura de todas las rutas o desaparición de todos los riesgos. Resumen del cierre en [final-review.md](final-review.md).

Límites que se conservan:

- Recuperación de justificantes/adjuntos desde una copia reciente y copia externa aplazadas por decisión del usuario. No se han restaurado las copias concretas de los despliegues 1.0.0/1.0.1.
- HSTS ausente en las respuestas comprobadas; análisis antimalware F08 aplazado y restantes límites de seguridad/legal previamente documentados.
- Capacidad y concurrencia reales no ensayadas; valores efectivos de PHP no facilitados en esta ronda. Exportación filtrada de varias páginas no confirmada expresamente.
- Cron confirmado de forma general; fecha/hora y salida exactas de su última ejecución no aportadas. Entrega de alertas con umbral y borrado efectivo de registros antiguos no ensayados.
- La matriz API cubre los casos concretos anotados, no cada endpoint, papel y mutación. No se atribuye cada resultado de equipo simultáneamente a admin y gestor. Quedan comportamientos de proxy y vistas no comprobadas.
- OPcache conserva la suposición operativa aceptada sobre la purga manual de hPanel; no se demuestra su invalidación efectiva por el despliegue.

Después del cierre aceptado: integrar las evidencias por PR y preparar la tarea 48, presentación y guion de vídeo. El usuario solicita el commit de estas evidencias el 26/11/2026; queda pendiente su PR a develop.
