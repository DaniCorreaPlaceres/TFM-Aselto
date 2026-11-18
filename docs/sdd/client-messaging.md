# Mensajes y solicitudes entre cliente y asesoría

## Objetivo

Permitir conversaciones con atención compartida por admin y gestor de la asesoría actual del cliente. Ambas partes pueden iniciar y responder. La asesoría crea solicitudes generales y confirma su resolución. Los mensajes admiten texto, un documento compartido opcional y adjuntos generales.

## Decisiones y alcance

- Conversaciones por asunto y cliente, visibles para ese cliente y el equipo de su asesoría actual. Sin asesoría se explica que no hay destinatario disponible. El superadmin no tiene acceso.
- Una solicitud es una conversación con estado pendiente o resuelto. Solo admin y gestor pueden crearla y resolverla. Resolver no borra sus mensajes; conserva autor y fecha. Se puede seguir respondiendo después de resolverla, sin reabrirla automáticamente.
- Lectura individual por usuario. El aviso de pendientes muestra conversaciones con mensajes posteriores a la última lectura de cada persona y solicitudes sin resolver; no hay confirmación pública de lectura.
- Un documento vinculado debe pertenecer al cliente y estar compartido con esa misma asesoría: enviado, rechazado o validado. No se comparten borradores ni documentos personales por este mecanismo.
- Adjuntos PDF, JPEG, PNG o WebP, máximo 8 MB por archivo y tres por mensaje. PHP y Nginx admiten peticiones de hasta 32 MB para cubrir el conjunto. Se verifican contenido y tipo, se guardan fuera del directorio público y se descargan tras comprobar los permisos de la conversación. No se extraen ni crean registros fiscales desde estos adjuntos.
- Los mensajes tienen texto obligatorio de 1 a 4000 caracteres; asunto de 1 a 120. No se editan ni borran. Las solicitudes ya implementadas de justificantes conservan su flujo documental y no se duplican automáticamente aquí.

## Fuera de alcance

Correo y notificaciones externas, Mercure, chat entre miembros del equipo, asignación a un gestor concreto, cambios de asesoría y vinculación por invitación, API, otros formatos de archivo y rediseño general con Bootstrap.

## Criterios de aceptación

- Cliente vinculado, admin y gestor pueden crear conversaciones y responder. La lista identifica asunto, cliente, solicitud pendiente y mensajes nuevos para ese usuario.
- Solo la asesoría crea solicitudes y las resuelve. Una respuesta del cliente no resuelve por sí sola. El historial conserva quién y cuándo resolvió.
- Cada consulta, respuesta, lectura, resolución y descarga verifica usuario activo, papel, asesoría y relación actual del cliente. Otra asesoría, otro cliente, usuarios inactivos y superadmin no acceden.
- Los enlaces a documentos se validan en el servidor. Las descargas de adjuntos no aceptan rutas aportadas por el usuario ni exponen archivos sin autorización. Se conservan nombre original seguro, tipo y fecha.
- Crear y responder guarda el mensaje y sus adjuntos de forma coherente. Escrituras concurrentes de una conversación no pierden mensajes; una lectura guarda la última secuencia mostrada, sin marcar mensajes posteriores como leídos.
- Inicio del cliente y navegación de la asesoría dan acceso a mensajes y muestran sus pendientes. Un cliente sin asesoría ve una explicación y no puede enviar.
- Formularios protegidos con CSRF, errores en español y conservación del texto ante errores. Ninguna acción de comunicación altera cifras, validaciones ni cierres del trimestre.
- Migración aditiva sin cambiar documentos existentes. Pruebas de permisos, solicitudes, no leídos, documentos vinculados, adjuntos, persistencia y recorrido HTTP.

## Comprobado

- Rama `feature/client-messaging`, creada desde `develop`.
- PHPUnit en Docker: **246 pruebas y 633 aserciones**, correctas. Incluye lectura individual del admin y gestor, secuencias antiguas que no consumen mensajes posteriores, solicitudes, resolución y autor, permisos de otro cliente y asesoría, usuarios inactivos y superadmin, documentos compartidos, adjuntos, limpieza ante errores y límites de texto y archivos.
- Integración MySQL y kernel HTTP: **37 comprobaciones**, correctas. Conversaciones y lectura persistidas tras recargar Doctrine; solicitud resuelta por gestor; descarga autorizada con cabeceras seguras; rechazo de HTML disfrazado de PDF; aislamiento entre clientes y asesorías; cliente sin asesoría; CSRF; conservación del texto tras error; HTML escapado; alta multipart con adjunto, respuesta y resolución desde formularios reales.
- Los datos sintéticos se revierten y sus adjuntos se eliminan. Se compararon documentos y cierres existentes antes y después: sin cambios.
- Regresión del trimestre: **43 comprobaciones de integración** correctas, incluida la espera de dos conexiones por el bloqueo de cliente que reutilizan las escrituras de comunicación.
- PHPStan nivel 6, contenedor y mapeo Doctrine sin errores. **39 plantillas Twig** y OpenAPI válidos. Formato Symfony comprobado en las copias LF de los 21 archivos PHP de esta tarea.
- Revisión visual de escritorio en navegador: lista vacía de mensajes, selección de cliente y formulario con asunto, texto, solicitud, documento relacionado y adjuntos. Los hilos con respuestas y archivos se verificaron por HTTP; no se ha ejecutado una revisión visual específica en móvil.
- `git diff --check` correcto.
- Migración `Version20261109020000` aplicada en local. Solo añade `client_conversation`; el descenso se bloquea si hay conversaciones. Aplicación local reconstruida y Nginx recargado para los límites de subida de 32 MB.

## Cómo probar

1. Como admin o gestor, abrir **Mensajes → Nuevo mensaje**, elegir cliente, escribir asunto y texto y adjuntar, si se desea, hasta tres archivos admitidos. Opcionalmente vincular un documento compartido o marcar que se trata de una solicitud.
2. Como cliente vinculado, abrir **Inicio → Ver mensajes** o **Mi asesoría → Mensajes y solicitudes**. Abrir la conversación, descargar el adjunto y responder. También puede iniciar una conversación.
3. Volver a la asesoría: aparecen mensajes nuevos para ese usuario. Abrir como otro gestor permite comprobar que su lectura es independiente. Responder no resuelve la solicitud; admin o gestor debe marcarla como resuelta.
4. Una cuenta de otra asesoría, otro cliente o el superadmin no accede al hilo ni al adjunto. Un cliente sin asesoría ve una explicación y no puede enviar.

Prueba de integración repetible sobre la base local migrada:

`docker compose exec -T --user www-data app php tests/Integration/messaging-workflow.php`

La regresión del trimestre se ejecuta con `tests/Integration/quarter-workflow.php`. No modificar documentos manualmente en esa base mientras se ejecutan estas pruebas, porque contrastan su contenido antes y después.

## Límites

Los avisos se actualizan al consultar las páginas. No hay correos ni actualización en vivo. Los mensajes y el progreso de lectura se guardan en el agregado de conversación; para un volumen de mensajería elevado convendría paginar y normalizar los mensajes, fuera del alcance actual del TFM.
