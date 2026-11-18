# Trimestres por API

Requiere un token Bearer del [acceso API](README.md). Las operaciones están disponibles en [Swagger UI](http://localhost:8080/api/docs).

## Consulta

- Cliente: `GET /api/v1/quarters/{year}/{quarter}`.
- Admin o gestor: `GET /api/v1/advisory/clients/{id}/quarters/{year}/{quarter}`. `id` es el identificador del perfil de cliente, no del usuario.

Año de 1 a 9999 y trimestre de 1 a 4. El único parámetro de consulta permitido es `scope`: si se omite se usa el ámbito actual (asesoría o personal). `scope=personal` permite consultar el histórico personal conservado tras vincularse. Un ámbito no autorizado devuelve 404.

La respuesta contiene `client_id`, `year`, `quarter`, `scope`, `firm_id`, `closed`, `version`, `fingerprint`, `can_manage`, `summary`, `pending`, `events` e `history`.

- `summary` muestra la instantánea guardada si está cerrado o el cálculo actual si está abierto. Importes enteros en céntimos: bases de ingresos y gastos, resultado, recargo y retenciones. `taxes` separa IVA, IGIC e IPSI; `output`, `input` y `balance` también son céntimos. `movements` conserva el detalle de cada movimiento.
- `pending` recoge registros pendientes del periodo y todos los documentos sin fecha de ese ámbito. Los documentos subidos después de un cierre no alteran la instantánea.
- `events` conserva cada cierre y reapertura del periodo elegido, con `actor_id`, fecha ISO 8601 e instantánea. `history` enumera los periodos guardados que el actor puede consultar, sus ámbitos, estados y eventos. No enumera periodos nunca cerrados.
- `version` vale 0 hasta el primer cierre. `fingerprint` identifica los datos actuales y es necesario para cerrar; debe copiarse de una consulta reciente del mismo periodo y ámbito.

No es una liquidación ni una presentación fiscal. Las respuestas son privadas y sin caché.

## Cierre y reapertura

Añadir `/close` o `/reopen` a la ruta de consulta y usar POST con `Content-Type: application/json`. Si se eligió un ámbito, conservar el mismo parámetro `scope` en la mutación.

Cierre de un periodo nunca cerrado, usando la huella real recibida en GET:

```json
{"version":0,"fingerprint":"0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"}
```

Reapertura tras el primer cierre:

```json
{"version":1}
```

La respuesta devuelve el trimestre actualizado y su versión persistida. Para volver a cerrar, consultar las cifras actuales y enviar su nueva versión y huella. No se aceptan importes, estados, propietarios ni otros campos en el cuerpo.

El cliente sin asesoría gestiona sus cierres personales. Tras vincularse conserva ese permiso sobre los cierres personales existentes. Admin y gestor gestionan periodos de clientes actuales de su propia asesoría; los históricos personales son de solo lectura para el equipo. El cliente vinculado consulta el ámbito de asesoría pero no lo cierra ni reabre. El superadmin no accede a datos fiscales mediante estas rutas.

## Errores

- 400: cuerpo, parámetros o periodo inválidos (`invalid_quarter` si el año o número está fuera del rango admitido).
- 401/403: token ausente o no válido, o papel no permitido en la ruta.
- 404 `quarter_not_found`: cliente, ámbito o acción no accesible, sin revelar datos ajenos.
- 409 `quarter_changed`: versión o huella desactualizada, doble cierre o reapertura de un trimestre que no está cerrado. Consultar de nuevo antes de decidir si se repite la operación.
- 422 `quarter_has_pending_documents`: resolver los registros pendientes fechados en el trimestre y los documentos sin fecha antes de cerrar.
- 413: cuerpo superior a 4096 bytes. 415: enviar JSON.

Los errores siguen el formato compartido `{"error":{"code":"...","message":"..."}}`. La documentación completa de esquemas está en [OpenAPI](../../openapi/openapi.yaml).
