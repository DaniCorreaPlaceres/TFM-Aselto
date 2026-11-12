# Comunicación por API

## Objetivo

Exponer conversaciones, solicitudes, lectura y adjuntos con JWT, reutilizando Messaging y los permisos de la web. Completar el bloque de comunicación de la tarea 24.

## Fuera de alcance

Notificaciones, cambios de reglas de producto, nuevos formatos de archivo, cambios de interfaz y migraciones.

## Criterios de aceptación

- Cliente vinculado, admin y gestor consultan e inician conversaciones. El equipo de la asesoría comparte la atención. Sin acceso para otra asesoría, otro cliente, superadmin o cliente desvinculado.
- Destinatarios y documentos enlazables se consultan usando los casos de uso existentes. Solo se enlazan documentos compartidos con ese cliente y asesoría.
- Listar no marca lectura. Abrir el detalle registra únicamente los mensajes mostrados, igual que la web; lectura individual. Responder no marca mensajes ajenos como leídos.
- Solo admin y gestor crean solicitudes y confirman su resolución; responder no resuelve. Doble resolución y resolución de conversación ordinaria se rechazan.
- Crear y responder admiten JSON sin archivos o multipart con hasta tres adjuntos PDF/JPEG/PNG/WebP de 8 MB cada uno. MIME comprobado por la aplicación. Descarga privada autorizada sin rutas internas.
- No se exigen versiones al consumidor: las escrituras usan la transacción y el bloqueo por cliente existentes. No se expone la versión previa a persistencia.
- Entrada estricta, errores JSON y respuestas privadas sin caché. Contrato OpenAPI, Swagger y guía coherentes.
- Integración HTTP con JWT real, permisos, lectura, solicitudes, descargas y rollback de datos y archivos ficticios; regresión web y comprobaciones del proyecto.

## Comprobado

- Rama `feature/api-communication`, creada desde develop tras integrar la API de trimestres.
- Ocho operaciones HTTP: destinatarios, documentos compartidos, listado, creación, detalle con lectura, respuesta, resolución y descarga. Reutilizan Messaging; no hay cambios del dominio ni migraciones.
- PHPUnit: 295 pruebas y 692 aserciones correctas. Incluye JSON inválido, campos inyectados, texto no escalar, resolución con objeto vacío y límite del cuerpo.
- Integración HTTP, MySQL y JWT firmado real: 79 comprobaciones correctas. Aislamiento entre clientes y asesorías, superadmin, cliente sin asesoría, destinatarios, lectura individual, respuesta sin lectura implícita, solicitudes y resolución por equipo, enlaces solo a documentos compartidos, tres archivos, MIME real, límite de 8 MB, descarga exacta y permisos actuales tras desvinculación simulada.
- Los datos sintéticos se revierten; se eliminan sus adjuntos y archivos temporales. Se comparan los documentos existentes antes y después, sin cambios. No se imprimen credenciales ni tokens.
- Regresión de mensajería web: 37 comprobaciones correctas; autenticación JWT: 54.
- PHPStan nivel 6 y contenedor correctos. Formato Symfony de los seis archivos PHP comprobado sin caché en copias LF, ninguna corrección pendiente. OpenAPI 0.5.0 válido.
- Swagger UI carga OpenAPI 0.5.0 y muestra las ocho operaciones de Comunicación. Ejecución autenticada y multipart comprobados mediante integración HTTP; no manualmente mediante Swagger.
- Guía en `docs/api/communication.md`. Tarea 24 marcada como completada para sus bloques acordados. Perfiles fiscales siguen fuera de esta API.

## Cómo probar

Con Docker y claves JWT locales preparados:

`docker compose exec -T --user www-data app php tests/Integration/api-communication-workflow.php`

No modificar documentos manualmente durante la prueba porque compara los existentes antes y después. Los datos y archivos de prueba se retiran al terminar.

En Swagger UI, obtener y autorizar un token, abrir Comunicación y consultar destinatarios. Usar `owner_id` para iniciar una conversación y responder desde el cliente y su equipo. Abrir el detalle para registrar lectura; solo admin o gestor puede crear y resolver solicitudes. Para adjuntos seleccionar multipart y utilizar `attachments[]`. Seguir `docs/api/communication.md` para el detalle.
