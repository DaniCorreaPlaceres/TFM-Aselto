# Bandeja de la asesoría

## Objetivo

El admin y el gestor revisan los registros enviados de los clientes de su asesoría. Pueden corregir sus datos al validar, rechazar con motivo o pedir un justificante adicional. El cliente responde desde el mismo registro, sin perder el archivo original ni el historial.

## Decisiones confirmadas

- Admin y gestor pueden revisar dentro de su asesoría.
- El rechazo exige motivo y permite al cliente corregir y reenviar el mismo registro, conservando el historial.
- Pedir otro justificante exige explicación. Se adjunta al mismo registro, conservando el original y bloqueando la validación hasta recibirlo.

## Fuera de alcance

Cifras, movimientos y cierre del trimestre (tarea siguiente), chat o solicitudes generales (tarea 22), avisos por correo, vinculación de cuentas existentes, API, edición de registros ya validados y maquetación general con Bootstrap. No existen todavía trimestres cerrados; su bloqueo al validar se incorporará con la tarea de Finanzas.

## Criterios de aceptación

- El acceso de admin y gestor incluye Bandeja. Solo lista enviados, rechazados y validados de clientes actualmente vinculados a su asesoría; no muestra borradores ni documentos de clientes sin asesoría.
- Cada lectura, descarga y acción comprueba el actor, su papel, la asesoría del documento y la vinculación actual del cliente. Otra asesoría, cliente o superadmin no usa esta bandeja.
- La revisión muestra el original y los justificantes, y permite corregir los mismos datos e importes del cliente al validar. IVA, IGIC, IPSI, recargo e IRPF conservan sus reglas y se recalculan en el servidor. No se cambia el impuesto guardado.
- Solo un enviado puede validarse, rechazarse o recibir una solicitud. Una validación con solicitud pendiente se rechaza. Un validado no puede volver a revisarse ni editarse.
- Se exige motivo de 1 a 1000 caracteres para rechazar o pedir justificante. La solicitud deja el registro enviado con aviso de pendiente; no introduce otro estado fiscal.
- El rechazado es editable para su propietario. Guardar borrador y reenviar conserva historial, original y justificantes. Tras reenviar vuelve a la bandeja como enviado.
- Solo el propietario aporta un justificante cuando hay una solicitud pendiente. Se admiten PDF, JPEG, PNG y WebP hasta 8 MB, con nombre seguro e identificador nuevo. No se extraen de nuevo datos fiscales. Aportarlo resuelve esa solicitud y mantiene el estado enviado para revisión humana.
- Se conserva un historial con acción, autor, fecha, motivo y datos anteriores y posteriores a la revisión. El cliente ve el motivo y las solicitudes en su documento y los pendientes en el inicio.
- El guardado detecta escrituras concurrentes y formularios desactualizados para no perder historial ni validar datos distintos de los revisados. Las acciones POST llevan CSRF.
- El inicio indica que el trimestre aún no está calculado, sin mostrar ceros como si fueran totales reales tras validar.
- La migración conserva los documentos e importes existentes. Se prueban estados, motivos, correcciones, aislamiento entre asesorías, permisos de archivos y justificantes, reenvío, concurrencia, persistencia y recorrido web.

## Comprobado

- PHPUnit en Docker: 195 pruebas y 496 aserciones correctas. Incluye estados, motivos, correcciones, reenvío, solicitudes, permisos de roles y de otras asesorías, justificantes, archivos, versiones y nombres de archivo con tildes y porcentajes.
- PHPStan nivel 6 sin errores. Las 32 plantillas Twig y el contrato OpenAPI son válidos. PHP-CS-Fixer no encuentra diferencias en la copia LF de los 65 archivos PHP del módulo, sus pruebas, controladores relacionados y migración, como en Linux.
- Migración Version20261108233000 aplicada en MySQL local. Los diez documentos anteriores conservan íntegramente datos e importes, con historial y justificantes a null y versión inicial 1.
- Recorrido GET/POST al núcleo HTTP de Symfony con dos asesorías de prueba: lista y original propios, denegación de otra asesoría, cliente y superadmin; protección CSRF; solicitud y bloqueo de validación; aportación y descargas del justificante; rechazo, corrección en borrador y reenvío; validación con corrección de base e inclusión de recargo.
- En ese recorrido se recuperó de MySQL un registro validado con seis eventos y un justificante, base 200 €, IVA 21 %, recargo 5,2 % y total 252,40 €. El original permaneció intacto. Se rechazaron un formulario anterior y una escritura con versión desactualizada. Los datos y archivos de prueba se revirtieron o retiraron al terminar.
- Navegador real con admin@example.test: acceso desde /cuenta y bandeja vacía comprobados visualmente. Se ajustaron separación de enlaces, colores, selección y áreas de interacción.
- Se corrigió la propiedad de var/cache/dev para el proceso web tras detectar un directorio de Twig creado por las pruebas de consola con otro usuario. La bandeja volvió a cargar. Las comprobaciones posteriores que arrancan Symfony se ejecutaron como www-data.

## Pendientes y límites

- No se ha recorrido visualmente en navegador la ficha con datos ni la aportación de archivos: esos recorridos se verificaron mediante el núcleo HTTP y MySQL. La extracción real no se modifica ni se prueba en esta tarea.
- El trimestre y el bloqueo de sus cierres siguen en la tarea 21; todavía no existen cierres persistidos. El chat y las solicitudes generales siguen en la tarea 22.
- El almacenamiento de archivos y la base de datos no comparten una transacción: un fallo de guardado posterior a una escritura podría dejar un archivo sin referencia. No se sobrescribe el original.
- Persisten los avisos anteriores de OPcache y versión mínima de PHP.
