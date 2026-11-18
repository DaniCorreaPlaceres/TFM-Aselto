# Documentos por API

Segundo bloque de la tarea 24. Requiere un JWT obtenido mediante [el acceso de la API](README.md). Todas las operaciones usan los mismos casos de uso que la web. El contrato completo está en [OpenAPI](../../openapi/openapi.yaml).

## Cliente

- `GET /api/v1/documents`: documentos propios, en `documents`.
- `POST /api/v1/documents`: subir un original mediante multipart, con un campo `file`.
- `GET /api/v1/documents/{id}`: ficha, versión, datos e historial.
- `PUT /api/v1/documents/{id}/draft`: reemplazar los datos editables del borrador.
- `POST /api/v1/documents/{id}/confirm`: confirmar o reenviar tras un rechazo.
- `POST /api/v1/documents/{id}/amend`: iniciar una corrección personal; el confirmado vuelve a borrador y debe confirmarse de nuevo.
- `GET /api/v1/documents/{id}/file`: original autorizado.
- `POST /api/v1/documents/{id}/supporting-files`: aportar el justificante solicitado, mediante multipart con `file` y `version`.
- `GET /api/v1/documents/{id}/supporting-files/{fileId}`: justificante autorizado.

## Admin y gestor

Usan `/api/v1/advisory/documents` para listar los compartidos y `/{id}` para consultar. Las descargas usan `/{id}/file` y `/{id}/supporting-files/{fileId}`.

- `POST /{id}/validate`: datos revisados y versión; permite corregir al validar.
- `POST /{id}/reject`: JSON con `version` y `reason`.
- `POST /{id}/request-supporting-file`: JSON con `version` y `reason`.

Solo se comparten enviados, rechazados y validados del cliente actual de esa asesoría. No se muestran borradores. Otro cliente, otra asesoría y superadmin no acceden a sus fichas ni archivos.

## Datos y versiones

Cada cambio de un registro existente exige su versión actual, como número entero en JSON. En multipart se envía como campo de formulario. Una respuesta correcta devuelve la versión persistida para la siguiente operación.

Los datos editables son `direction` (`income` o `expense`), `issued_on` (fecha `YYYY-MM-DD`), `party_name`, `tax_id`, `base`, `tax_rate`, `surcharge_rate` y `withholding_rate`. Importes y porcentajes se envían como **cadenas decimales con punto**, sin separadores de miles: `"100.00"`, `"21"`, `"5.2"`. No se admiten números JSON para estos campos, comas ni campos desconocidos.

PUT reemplaza los datos editables: los omitidos, null o vacíos quedan sin indicar. Un borrador puede estar incompleto; confirmar o validar exige todos los obligatorios. Los tipos de impuesto, cuotas, total, estado, propietario y asesoría no se pueden imponer desde el cliente.

La respuesta muestra importes en céntimos (`base_cents`, `tax_cents`, `surcharge_cents`, `withholding_cents`, `total_cents`) y los porcentajes como cadenas. Incluye `proposal`, `pending_request`, `history`, `file_url` y `supporting_files`. No contiene rutas internas de almacenamiento.

La extracción solo propone. Sin asesoría, confirmar es control personal; con asesoría, el registro queda pendiente de validación. Un justificante aportado conserva el original y no valida automáticamente.

## Archivos y errores

PDF, JPEG, PNG o WebP, hasta **8.000.000 bytes**, con un único campo `file`. Se detecta el tipo del archivo en el servidor. La creación devuelve 201 y Location; la aportación de un justificante devuelve la ficha actualizada y Location apunta al archivo añadido.

Las descargas requieren el Bearer y mantienen las cabeceras privadas, sin caché y `nosniff`. No se añade el token a sus URLs.

Errores JSON con el formato común `error.code` y `error.message`:

- 400: JSON, campos o versión con formato incorrecto.
- 403: papel no autorizado en esa familia de rutas.
- 404: registro o archivo inexistente o no visible.
- 409 `document_changed`: versión obsoleta; consultar de nuevo.
- 409 `quarter_closed`: reabrir el periodo desde la web antes de corregir o confirmar. También se comprueba la fecha original al mover un registro.
- 413: archivo o cuerpo demasiado grande.
- 415: usar JSON para cambios o multipart para archivos.
- 422: datos incompletos o incompatibles, estado no editable, motivo ausente o formato de archivo no admitido. `incomplete_document` incluye `error.fields` con los nombres de los campos obligatorios ausentes.

## Ejemplo local

Obtener primero el token como en la guía de acceso y conservar temporalmente `$apiAccess`. Utilizar únicamente un documento de prueba:

```powershell
$apiDocument = curl.exe --silent --show-error --request POST `
  'http://localhost:8080/api/v1/documents' `
  --header "Authorization: Bearer $($apiAccess.token)" `
  --form 'file=@C:\Ruta\documento-prueba.pdf' | ConvertFrom-Json

$apiDocument | Select-Object id, status, tax, version, proposal
```

Revisar los datos y elegir el porcentaje que figure en ese documento. El siguiente cuerpo contiene datos ficticios y debe adaptarse al registro de prueba y al impuesto guardado:

```powershell
$apiRate = Read-Host 'Porcentaje del impuesto del documento de prueba (usa punto para los decimales)'
$apiDocumentBody = @{
  version = $apiDocument.version
  direction = 'expense'
  issued_on = '2026-11-01'
  party_name = 'Proveedor sintético'
  tax_id = '00000000T'
  base = '100.00'
  tax_rate = $apiRate
  surcharge_rate = $null
  withholding_rate = $null
} | ConvertTo-Json

$apiDocument = Invoke-RestMethod `
  -Uri "http://localhost:8080/api/v1/documents/$($apiDocument.id)/confirm" `
  -Method Post -ContentType 'application/json' `
  -Headers @{ Authorization = "Bearer $($apiAccess.token)" } `
  -Body $apiDocumentBody

$apiDocument | Select-Object id, status, version, base_cents, tax_cents, total_cents
```

La integración automatizada cubre el recorrido con usuarios ficticios y firma real, sustituyendo solo el extractor para no llamar al proveedor de IA. Revierte sus datos y elimina los archivos creados:

```powershell
docker compose exec -T --user www-data app php tests/Integration/api-documents-workflow.php
```

Ejecuta la integración sin modificar documentos manualmente en esa misma base: comprueba su contenido antes y después.
