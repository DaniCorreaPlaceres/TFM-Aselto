# Planes, activación y consumo

## Reglas aprobadas

- Gratis: 0 €, 20 registros documentales por mes natural de `Atlantic/Canary`.
- Profesional: 4,99 €/mes, para cuentas independientes, sin límite mensual de documentos.
- Asesoría: 2,99 €/mes por cliente vinculado con cuenta activada, cubre a sus clientes sin límite mensual de documentos.
- Impuestos incluidos. Activación manual por superadmin en `/licencias`. No hay cobros, suscripciones, prorrateos ni facturación de la plataforma.
- Las cuentas sin asignación explícita usan Gratis. Los límites de archivo y los permisos no cambian con el plan.

Cada registro documental que se crea consume una unidad, aunque la extracción falle y requiera introducir datos, quede en borrador o se rechace después. Corregir o confirmar ese registro y añadir justificantes no crea otro consumo. Eliminarlo no devuelve cuota. Los mensajes y sus adjuntos generales no son registros documentales y no consumen esta cuota.

Los cambios son inmediatos y conservan documentación y consumo. Una bajada a Gratis con 20 o más unidades usadas impide nuevas subidas hasta el mes siguiente o el cambio de plan; permite consultar y corregir lo existente. Al vincularse, el cliente usa la cobertura Asesoría o, si su asesoría no la tiene, Gratis. Su asignación independiente se conserva para cuando deje de estar vinculado. No se cambia ninguna validación o cierre previo.

## Pantallas y permisos

- Superadmin: menú **Licencias**, asignación a asesorías o independientes con formulario protegido. Lista solo nombre, identificador, tipo y plan; no consulta consumo, documentos ni cifras fiscales.
- Cliente: menú **Mi plan**, plan efectivo y consumo propio del mes. La subida enlaza a esta pantalla y explica el límite.
- Admin o gestor: **Mi plan**, plan de su asesoría, número de clientes vinculados con cuenta activada e importe mensual de referencia con impuestos. No representa una factura.
- Web y API usan `StoreClientDocument`. El límite agotado se muestra en el formulario; la API devuelve HTTP 409, `error.code: document_quota_exceeded`. Contrato en `openapi/openapi.yaml`.

## Persistencia y concurrencia

`account_license` guarda la asignación y un historial de actor, plan y momento. `document_usage` registra una vez el identificador de documento, propietario y mes; no tiene borrado en cascada desde el documento. La creación del registro y el consumo se confirman en la misma transacción. El bloqueo por cliente serializa sus subidas y cambios de vínculo; el bloqueo de la licencia coordina la asignación de planes. El plan se relee con bloqueo antes de consumir para evitar una lectura antigua tras esperar por un cambio concurrente.

El bloqueo se conserva durante la extracción y escritura para no reservar cuota fuera de la transacción ni dejar carreras al vincular o cambiar de plan. Esto serializa las subidas de un cliente y, al compartir la licencia, también las de una asesoría durante esa operación; es una limitación de rendimiento de esta primera versión.

La migración `Version20261114040000` añade ambas tablas y copia el consumo de los documentos existentes, interpretando `created_at` según la convención UTC del reloj de la aplicación y convirtiéndolo a Canarias en PHP. No depende de las tablas de zonas horarias de MySQL y no modifica los documentos originales. Los documentos borrados antes de esta función no pueden reconstruirse. No hay migración inversa que descarte consumo o historial.

Antes de aplicar migraciones en un despliegue: copia de seguridad y detener temporalmente nuevas escrituras/subidas. MySQL hace confirmaciones implícitas al ejecutar DDL; un fallo parcial requiere revisar y recuperar la migración, no repetirla a ciegas. La publicación en Hostinger se concretará en sus tareas de despliegue.

## Comprobación reproducible

Usar siempre una base temporal MySQL 8.4. En entorno `test`, Symfony añade `_test` al nombre de base configurado.

1. En una base vacía terminada en `_test`, ejecutar `php tests/Integration/license-backfill-workflow.php`: migra hasta la versión anterior, inserta dos originales sintéticos, migra a la actual y verifica preservación y meses de verano/invierno. Retira los originales sintéticos.
2. Ejecutar `php tests/Integration/schema-workflow.php`: 13 comprobaciones de esquema y conservación de datos.
3. Ejecutar `php tests/Integration/licenses-workflow.php`: 20 comprobaciones, con dos procesos que compiten desde 19 unidades y crean exactamente un documento. También comprueba eliminación, cambios de plan, fallos con rollback y cobertura al vincularse.
4. Con `DATABASE_URL` apuntando explícitamente a la base temporal para los scripts que usan kernel `dev`: `php tests/Integration/document-upload-workflow.php` (21 comprobaciones) y `php tests/Integration/api-documents-workflow.php` (77). No invocan IA externa y retiran datos y archivos sintéticos.
5. Ejecutar PHPUnit, PHPStan, formato PHP, validación OpenAPI y plantillas/recursos. CI incluye la migración con datos preexistentes y la carrera de dos procesos dentro del trabajo Esquema MySQL.

Para probar en la interfaz: entrar con el superadmin de demostración, abrir Licencias, asignar Profesional a un independiente y entrar con ese cliente para ver Mi plan. Probar el límite con documentos ficticios; cambiar a Gratis conserva su consumo. No usar datos fiscales reales en demostraciones.
