# Comunicación por API

Usar [acceso JWT](README.md) y autorización Bearer. Las rutas aparecen bajo **Comunicación** en [Swagger UI](http://localhost:8080/api/docs).

## Destinatarios y documentos

`GET /api/v1/conversation-recipients` devuelve `recipients` con `client_id` (perfil), `owner_id` (usuario del cliente) y `name`. Un cliente vinculado recibe su propio destinatario; admin y gestor reciben los clientes actuales de su asesoría. Un cliente sin asesoría no tiene destinatarios.

`GET /api/v1/conversation-recipients/{ownerId}/documents` devuelve `documents` con identificador, nombre y estado de los documentos compartidos que pueden enlazarse. No incluye borradores ni documentos de otra asesoría. Aquí se utiliza `owner_id`, no `client_id`.

## Conversaciones y solicitudes

- `GET /api/v1/conversations`: devuelve `conversations` autorizadas, ordenadas por el último mensaje. Listar no registra lectura.
- `POST /api/v1/conversations`: crea una conversación y devuelve 201, su contenido y Location del detalle.
- `GET /api/v1/conversations/{id}`: abre la conversación y registra la lectura individual de los mensajes mostrados, igual que la web. La respuesta muestra `unread=0` para esa secuencia; los mensajes posteriores siguen sin leer.
- `POST /api/v1/conversations/{id}/messages`: responde y devuelve 200 con la conversación actualizada. No registra lectura de mensajes ajenos ni resuelve una solicitud.
- `POST /api/v1/conversations/{id}/resolve`: recibe `{}` como JSON. Admin o gestor confirma la resolución y obtiene 204 sin cuerpo. Solo admite solicitudes pendientes.

Ejemplo ficticio de creación; sustituir `owner_id` por uno obtenido en destinatarios:

```json
{
  "owner_id": "11111111-1111-4111-8111-111111111111",
  "subject": "Información del trimestre",
  "text": "Necesitamos aclarar este movimiento.",
  "request": true,
  "document_id": null
}
```

`request` es opcional y por defecto vale false. Solo el equipo de la asesoría puede crear una solicitud. `document_id` es opcional: UUID de un documento compartido o null; no se admite una cadena vacía. Asunto de 1 a 120 caracteres y texto de 1 a 4000 tras eliminar espacios extremos. Responder requiere solo `{"text":"Respuesta de ejemplo"}`.

Las respuestas incluyen identificadores de conversación, propietario y asesoría, asunto, indicador de solicitud, documento enlazado, resolución con autor y fecha, contador de no leídos y mensajes con autor, fecha ISO 8601, texto y adjuntos. No contienen rutas de almacenamiento ni versiones previas a persistencia. Las escrituras utilizan el bloqueo por cliente y los controles del caso de uso web; el consumidor no envía una versión.

## Adjuntos

Crear y responder admiten `application/json` sin archivos o `multipart/form-data` con los mismos campos y archivos en `attachments[]`. En multipart, `request` se escribe `true` o `false`. Hasta tres archivos por mensaje, PDF/JPEG/PNG/WebP de 8 MB cada uno. Se exige texto también con archivos. El tipo se comprueba por contenido, no por la extensión ni el MIME declarado.

Los adjuntos devueltos tienen `id`, `name`, `media_type` y `url`. Descargar usando `GET /api/v1/conversations/{id}/attachments/{fileId}` con la cabecera Authorization. Se comprueba que el adjunto pertenece a esa conversación y que el acceso sigue vigente. La respuesta fuerza descarga, incluye `nosniff` y no se cachea. Estos archivos generales no se suman a las cifras fiscales; los justificantes documentales mantienen su recorrido propio.

## Permisos y errores

Cliente vinculado, admin y gestor pueden conversar. La atención es compartida entre el equipo y la lectura es individual. Otro cliente, otra asesoría y superadmin no acceden a la conversación ni a sus archivos. La vinculación actual se comprueba en cada operación; al desvincularse se pierde el acceso compartido.

Errores JSON compartidos: 400 para forma, campos o identificadores inválidos; 401 para token ausente o inválido; 403 para papel no permitido; 404 `conversation_not_found` para un recurso o acción no accesible; 422 `invalid_message` para datos, archivo, enlace o resolución no válidos. Las comprobaciones de forma de adjuntos pueden devolver 422 `validation_failed`. Un archivo superior a 8 MB o JSON superior a 32 KiB devuelve 413; un tipo de petición no admitido, 415. Todas las respuestas son privadas y sin caché. El límite global del servidor puede rechazar una petición antes de llegar a Symfony.

Los esquemas completos están en [OpenAPI](../../openapi/openapi.yaml).
