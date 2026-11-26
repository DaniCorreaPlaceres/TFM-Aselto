# Publicación de Aselto 1.0.0

## Objetivo

Publicar la versión 1.0.0 del TFM con la vinculación consentida de clientes existentes, mediante PR a main y el CI/CD autorizado de Hostinger. Solicitud del usuario del 19/11/2026. Rama `feature/release-1-0-0` desde develop; integración previa a develop y después PR de develop a main.

## Cambios planteados

- Sincronizar el último main en la rama de preparación para cumplir las comprobaciones estrictas sin modificar protecciones.
- Cambiar la única fuente `app.version` a `1.0.0`; conservar la versión independiente del contrato OpenAPI.
- Actualizar notas de versión y estado de publicación con límites reales, sin atribuir a producción pruebas locales.
- Verificar comando/footer y checks de GitHub antes de integrar. Mantener el interruptor autorizado de despliegue automático.
- Tras integrar main, verificar el despliegue, migración, salud y footer. Asociar `v1.0.0` al commit exacto verificado de main, sin reutilizar etiquetas.
- Preparar pruebas guiadas con el usuario en Hostinger: vinculación, permisos e histórico y demás recorridos finales. Registrar solo resultados observados.

## Fuera de alcance

Cambiar protecciones, activar Git nativo de Hostinger, acceder con credenciales del usuario, cargar datos fiscales reales, resolver todos los pendientes de seguridad/legales o declarar cerrada la tarea 47 sin probarla. La documentación guardada de otra tarea y `config/reference.php` quedan fuera.

## Criterios de aceptación

- Producto y footer identifican `1.0.0` desde el mismo parámetro.
- PR y checks correctos, sin push directo a main. Despliegue automático identificado por SHA, copia previa, migración y esquema correctos.
- Etiqueta anotada solo sobre el commit final de main comprobado; no afirmar publicación antes del resultado real.
- Comprobaciones de Hostinger documentadas como realizadas o pendientes; no exponer claves ni documentos.

## Estado

Preparación local comprobada el 19/11/2026: `app:version` devuelve `1.0.0`, YAML válido y home local HTTP 200 con footer `Versión 1.0.0`. La rama incorpora el último main sin modificar archivos de producto en esa sincronización. El PR #74 de vinculación tiene los seis checks obligatorios y su paquete correctos en GitHub.

Notas actualizadas con los límites aceptados. Integración de versión, despliegue, etiqueta y pruebas productivas de la vinculación pendientes. No se repite la batería local por un cambio de parámetro/documentación; GitHub ejecutará las comprobaciones obligatorias completas.

## Publicación comprobada · 19/11/2026

- PR #75 integrado en develop y PR #76 integrado en main a las 00:56:41 UTC. Commit exacto de publicación: `f85e60f27f57c2f527e5813376758e69c2db1edf`.
- [Ejecución 44433061282](https://github.com/DaniCorreaPlaceres/TFM-Aselto/actions/runs/44433061282): seis checks, paquete y publicación SSH correctos. El log confirma publicación a las 00:59:14 UTC.
- Copia previa privada registrada por el despliegue: `private/backups/deploy-f85e60f27f57c2f527e5813376758e69c2db1edf-20261119T005901Z-1337186`. No se ha ensayado la restauración de esta copia concreta.
- Migración productiva `Version20261118010000`: una migración y una consulta ejecutadas; esquema sincronizado en la MariaDB real del hosting. Limpieza de caché prod antes y después, y comando productivo con salida `1.0.0`.
- Comprobación HTTP externa posterior: `/`, `/entrar`, `/salud`, `/precios` y `/api/docs` devuelven 200. Salud indica `estado: ok` y `base_de_datos: ok`. El footer público muestra `Versión 1.0.0`.
- `/api/v1/me` sin credenciales devuelve 401; `/composer.json` y `/config/services.yaml` devuelven 404.
- Etiqueta anotada `v1.0.0` creada y publicada tras comprobar main, CI/despliegue y versión pública. SHA del objeto de etiqueta: `0a05d519feb3fb050795538306800b692449c8c7`; commit desreferenciado: `f85e60f27f57c2f527e5813376758e69c2db1edf`. No se ha creado una GitHub Release adicional.
- Evidencias preparadas en copia de trabajo aislada, rama `feature/release-1-0-0-verification`, para no interferir con la documentación de otra tarea.

## Recorrido manual comunicado por el usuario

Prueba guiada en Hostinger con cuenta independiente de prueba y cuenta admin/gestor. Resultados confirmados por el usuario en el chat, sin captura ni comprobación directa del asistente de las pantallas autenticadas:

- Invitación recibida por correo. Antes de aceptar, el cliente no aparecía en Clientes ni sus documentos en la bandeja de la asesoría.
- Aceptación desde la cuenta destinataria, nuevo inicio de sesión, datos de contacto visibles en Mi asesoría y cliente visible para el equipo tras vincularse.
- Confirmación explícita de Pendientes e Histórico personal: registros de periodos abiertos pendientes de validación y documentos de cierres personales consultables sin validación profesional.
- Validación de un registro de prueba de trimestre abierto desde la asesoría, aparición en Validados, inclusión del importe al actualizar las cifras y estado validado visible desde la cuenta cliente. Resultado comunicado por el usuario como correcto.
- Cierre de un trimestre de prueba sin pendientes ni documentos sin fecha desde la asesoría; consulta del cierre y conservación de cifras desde el cliente; reapertura desde la asesoría e historial de ambas acciones con autor y fecha. Resultado comunicado por el usuario como correcto. Este recorrido no prueba por sí solo el rechazo del cierre con pendientes.
- Petición de justificante con motivo por la asesoría, aportación de archivo ficticio por el cliente al mismo registro, apertura del original y justificante desde la asesoría, validación e historial de petición/aportación conservado. El usuario confirma expresamente los cuatro pasos. Comprueba el uso actual del justificante; no acredita su recuperación desde una copia de seguridad.
- Intento de cierre desde la asesoría con un documento de prueba fechado en ese trimestre y pendiente de validar: cierre bloqueado, aviso de pendientes y trimestre conservado abierto. Resultado confirmado por el usuario. El caso de documento sin fecha sigue pendiente.
- Bloqueo por documento sin fecha: tras resolver el pendiente fechado y usar un trimestre abierto sin otros pendientes, el cliente deja un documento ficticio en borrador sin fecha; la asesoría intenta cerrar y el trimestre permanece abierto. Resultado confirmado por el usuario.

Incidencia durante la prueba de Mensajes: el usuario comunica que, al enviar texto sin adjunto en cualquiera de los dos sentidos, el emisor ve su mensaje pero la otra cuenta no lo ve. No se marca como correcta la mensajería ni sus adjuntos. Diagnóstico en curso: distinguir actualización del listado, acceso directo y permisos antes de atribuirlo al archivo opcional o modificar código.

Quedan por probar en producción: resolución de la incidencia de mensajería y adjuntos generales, recuperación de justificantes/adjuntos desde copia y los demás recorridos de la tarea 47. No se da por completada la matriz de permisos de todos los roles o asesorías por este recorrido. Estas evidencias tampoco acreditan el vaciado de OPcache.

Seguimiento posterior: la incidencia del listado se corrigió y publicó en 1.0.1; las pruebas manuales posteriores y el cierre aceptado de la revisión 47, con límites expresos, constan en [release-1-0-1.md](release-1-0-1.md) y [final-review.md](final-review.md). Los pendientes descritos arriba corresponden al estado de esta primera ronda.
