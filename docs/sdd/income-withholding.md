# Retención de IRPF en ingresos de autónomos

## Objetivo

Registrar opcionalmente el porcentaje de retención que figura en el ingreso de un autónomo, calcular su cuota sobre la base y restarla del total. La aplicación no decide si la operación debe llevar retención ni qué tipo corresponde.

## Fuera de alcance

Catálogo o determinación automática del régimen y del tipo fiscal, retenciones en gastos o ingresos de sociedades, varias bases en un documento, validación de la asesoría, trimestre y API.

## Criterios de aceptación

- Campo opcional para el porcentaje de IRPF, vacío por defecto. Se admite entre 0 y 100 con hasta dos decimales, como límite técnico de entrada y no como catálogo de tipos fiscales.
- Solo se admite en ingresos cuyo propietario es autónomo. Se comprueba en los casos de uso desde la ficha guardada, sin confiar en datos enviados por el formulario. Sigue siendo aplicable con IVA, IGIC o IPSI.
- La cuota se calcula sobre la base, con aritmética entera y redondeo al céntimo. Total = base + cuota del impuesto + cuota de recargo, si existe, menos retención. La retención y el recargo no conviven porque corresponden a ingreso y gasto respectivamente.
- La pantalla muestra porcentaje y cuota de retención por separado, calcula el mismo total que el servidor y retira la retención al cambiar a gasto. Un porcentaje inválido se rechaza junto al campo, incluso al guardar borrador.
- El borrador admite base incompleta y retención sin cuota calculada. Se puede corregir o retirar la retención y confirmar; confirmar sigue dejando el documento enviado.
- La lectura solo propone retención explícita en ingresos de autónomos. No la deduce del IVA ni del perfil. Se ignoran cuotas y totales propuestos por el proveedor. Un valor inválido se descarta, conservando el resto de datos útiles. El ejemplo no añade retención.
- La migración conserva los documentos anteriores sin retención y no cambia sus importes, recargo ni estado. El porcentaje y la cuota nuevos se conservan al guardar y recuperar el documento.
- Se prueban límites, decimales, redondeo, incompatibilidades, permiso del propietario, sociedades, extracción, borrador, retirada, confirmación y persistencia.

## Referencia

[AEAT: porcentajes de retención aplicables a las distintas rentas](https://sede.agenciatributaria.gob.es/Sede/irpf/retenciones-ingresos-cuenta-pagos-fraccionados/retenciones-ingresos-cuenta/porcentajes-retencion-aplicables-distintas-rentas.html). Consultada el 8 de noviembre de 2026. La diversidad de supuestos motiva la entrada manual y la revisión humana, sin seleccionar automáticamente un porcentaje.

## Comprobado

- PHPUnit en Docker: 168 pruebas y 424 aserciones correctas. Incluye límites, decimales, redondeo, borrador parcial, retirada, confirmación, persistencia, rechazo en sociedades (también al 0 %), propietario ajeno y perfil inexistente.
- PHPStan nivel 6 sin errores. Plantilla Twig y OpenAPI válidos.
- PHP-CS-Fixer sin diferencias sobre una copia LF de los 45 archivos PHP del módulo, sus pruebas y la migración, como en Linux.
- Controlador de pantalla ejecutado con Node: doce escenarios de importes, diez porcentajes inválidos y retirada y ocultación al pasar a gasto correctos. Incluye comprobación del recargo anterior.
- Migración Version20261108210000 aplicada en MySQL local. Los diez documentos anteriores conservan íntegramente sus datos, importes, recargos y estado, y tienen las columnas nuevas a null.
- Peticiones GET y POST al núcleo HTTP de Symfony: visibilidad del campo, rechazo de porcentaje inválido, borrador, confirmación y recuperación en MySQL para autónomo con IVA, IGIC e IPSI. Ejemplos con base 100 €: IVA 21 % y retención 15 % = 106,00 €; IGIC 7 % y retención 7 % = 100,00 €; IPSI 2,35 % y retención 15 % = 87,35 €.
- Con sociedad, el campo no se muestra y se rechaza una retención inyectada en la petición; su ingreso sin retención se guarda y confirma normalmente. Los datos temporales se revirtieron al terminar.

## Pendiente

- No se ha probado el proveedor de IA real ni se ha realizado revisión visual en navegador. La prueba HTTP se ejecutó contra el núcleo de Symfony, sin pasar por Nginx.
- Persisten los avisos anteriores de OPcache y de versión mínima de PHP, fuera del alcance de esta tarea.
