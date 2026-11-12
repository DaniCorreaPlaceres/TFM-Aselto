# API v1: documentos y revisión de asesoría

## Objetivo

Exponer el recorrido documental ya existente mediante JWT, reutilizando los casos de uso de la web: subida, consulta, borrador, confirmación, corrección personal, revisión y justificantes.

## Fuera de alcance

API de trimestres, comunicación, perfiles fiscales, altas y vinculación de cuentas, nuevos estados o reglas fiscales, varias bases por documento y cambios del extractor de IA.

## Contrato previsto

- Cliente: `/api/v1/documents`, ficha `/{id}`, `PUT /{id}/draft`, `POST /{id}/confirm`, `POST /{id}/amend`, original `/{id}/file`, aportación `POST /{id}/supporting-files` y descarga `/{id}/supporting-files/{fileId}`.
- Admin y gestor: `/api/v1/advisory/documents`, ficha `/{id}`, original y justificantes, `POST /{id}/validate`, `POST /{id}/reject` y `POST /{id}/request-supporting-file`.
- Subidas multipart con un campo `file`, los mismos formatos y límite de 8 MB existentes. El justificante exige también `version`. Las demás mutaciones usan un objeto JSON y versión entera positiva; no se aceptan campos desconocidos ni importes JSON de coma flotante.
- Los datos de edición usan nombres ingleses: `direction` (`income` o `expense`), `issued_on`, `party_name`, `tax_id`, `base`, `tax_rate`, `surcharge_rate`, `withholding_rate`. Importes y porcentajes son cadenas decimales con punto, sin separadores de miles. Null o cadena vacía permiten datos incompletos en un borrador. PUT reemplaza los datos editables; los omitidos se dejan vacíos.
- Impuesto, cuotas, total, estado, propietario y asesoría los determina el servidor. La consulta devuelve importes en céntimos, porcentajes como cadenas, versión, propuesta, historial y enlaces protegidos a archivos, sin rutas de almacenamiento.
- Guardar o confirmar devuelve la versión recargada. Un registro rechazado se corrige y reenvía con su historial; los confirmados personales requieren iniciar la corrección. Los validados no se editan.

## Criterios de aceptación

- JWT obligatorio. Cliente solo opera sus documentos. Admin y gestor solo revisan compartidos de su asesoría y cliente actual. Otra asesoría, otro cliente y superadmin no acceden a datos ni descargas. Los borradores no se comparten con la asesoría.
- Se usan StoreClientDocument, ListClientDocuments, OpenClientDocument, SaveDocumentDraft, ConfirmDocument, AmendPersonalDocument, ListFirmDocuments, FirmDocumentAccess, ReviewSubmittedDocument, SupplySupportingDocument y ReadSupportingDocument. No se recalculan importes en el controlador.
- Una lectura de IA no confirma ni valida. Se mantienen IVA, IGIC, IPSI, recargo e IRPF y el impuesto guardado del documento.
- Todas las mutaciones sobre un registro existente exigen versión. Una versión obsoleta y un trimestre cerrado devuelven 409 sin modificar datos. Se comprueban fecha original y destino mediante los guardas existentes.
- Rechazo y solicitud exigen motivo; aportar un justificante conserva original e historial y no valida automáticamente. La validación con justificante pendiente se rechaza.
- Errores JSON: formato 400, permiso 403, registro oculto o inexistente 404, conflicto 409, contenido demasiado grande 413, tipo de petición 415 y validación de datos o archivo 422. No se exponen excepciones, contenido fiscal ajeno ni rutas internas.
- Las descargas y respuestas JSON son privadas y sin caché. Se comprueba permiso antes de leer el archivo. Los listados solo contienen los documentos autorizados.
- OpenAPI y guía de uso describen todas las rutas y ejemplos ficticios. Pruebas de permisos, versiones, impuestos, justificantes, bloqueo de trimestre, firma JWT real y regresión de la web.

## Comprobado

- Rama `feature/api-documents`, creada desde `develop`.
- PHPUnit: **276 pruebas y 673 aserciones**, correctas. Incluye entrada estricta, importes decimales sin coma ni números flotantes JSON, campos calculados no admitidos, versión obligatoria, borradores incompletos, nombres de campos en errores y conflictos sin detalles internos.
- Integración con MySQL, kernel HTTP y JWT firmado real: **74 comprobaciones**, correctas. Recorre subida, consulta y original, confirmación personal con retención, corrección y reconfirmación con recargo, bloqueo de trimestre y subida tardía, envío a asesoría, petición y aportación de justificante, descargas de ambas partes, rechazo, corrección, reenvío y validación con cambios e historial. Comprueba aislamiento, borradores no compartidos, nuevas versiones, impuesto conservado tras cambiar perfil, IGIC, IPSI manual, retención no admitida para sociedades, límite de 8 MB y corrección de fecha hacia un trimestre cerrado.
- El extractor se sustituye solo en el contenedor de integración por una propuesta sintética para no enviar archivos a IA externa. Los datos se revierten y los archivos creados se eliminan tras verificar su ruta dentro del almacenamiento. Documentos existentes sin cambios; no se imprimen tokens ni contraseñas.
- Regresión JWT: **54 comprobaciones** correctas. Regresión del trimestre web: **43**. Regresión de mensajería: **37**. Pruebas JavaScript existentes: **9**, correctas.
- PHPStan nivel 6 y contenedor correctos. OpenAPI 0.3.0 validado con las operaciones documentales, esquemas de importes, versiones, historial, descargas y errores. Formato Symfony comprobado en las copias LF de los siete archivos PHP de la tarea; `git diff --check` correcto.
- Ruta documental comprobada también a través de Nginx sin credenciales: 401 JSON con desafío Bearer y sin caché.
- Guía de uso en [../api/documents.md](../api/documents.md). La tarea 24 conserva pendientes los bloques de trimestres y comunicación. No se han cambiado las pantallas ni las reglas del dominio y no hay nuevas migraciones.

## Cómo probar

Con el entorno Docker y las claves JWT locales preparados:

`docker compose exec -T --user www-data app php tests/Integration/api-documents-workflow.php`

La prueba usa usuarios y archivos ficticios y los retira al terminar. No modificar documentos manualmente durante su ejecución porque compara el contenido existente antes y después.

Para probar manualmente, obtener un Bearer con un cliente y seguir [la guía documental](../api/documents.md). Como asesoría, usar un token de admin o gestor de la misma asesoría y la familia `/api/v1/advisory/documents`. Un cliente sin asesoría puede confirmar para control personal; uno vinculado envía a revisión.

## Pendiente

Los trimestres se cierran y reabren desde la web hasta el siguiente bloque de API. No se han expuesto perfiles fiscales, comunicación ni nuevas operaciones de vinculación.
