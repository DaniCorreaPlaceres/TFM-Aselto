# Registros exentos de impuesto indirecto

## Funcionamiento aprobado

En la revisión de un documento, «Exento de IVA», «Exento de IGIC» o «Exento de IPSI» conserva su territorio y marca una exención expresa, distinta del porcentaje 0 %. La persona comprueba si corresponde; Aselto no identifica automáticamente exenciones ni decide su procedencia.

Con base conocida, la cuota del impuesto indirecto es cero. No hay porcentaje aplicable ni recargo de equivalencia. En ingresos de autónomos puede mantenerse la retención opcional de IRPF: por ejemplo, base de 100 € y retención del 15 % producen cuota indirecta 0 €, retención 15 € y total 85 €. Los permisos y la prohibición de IRPF en ingresos de sociedades se mantienen.

Marcar la opción en la web elimina el porcentaje y el recargo del formulario; desmarcarla exige elegir de nuevo el tipo. El servidor aplica también esta normalización cuando se envía el formulario sin JavaScript. Los cambios requieren guardar o confirmar; no se modifica el documento por abrir su ficha o marcar el control. Una propuesta del extractor nunca puede asignar la exención; si intenta hacerlo, el documento queda para entrada manual.

## Documentos y cierres anteriores

La migración `Version20261114050000` añade `client_document.tax_exempt` con valor inicial falso. No cambia porcentajes, importes ni históricos existentes. Las instantáneas y eventos sin el atributo se interpretan como no exentos. Los nuevos guardan el indicador para distinguirlo del tipo cero también en el histórico.

Cambiar un registro confirmado requiere el proceso de corrección existente. La asesoría sigue validando los suyos. Un trimestre cerrado debe reabrirse con los permisos, versión e historial habituales; no puede trasladarse la fecha a otro periodo para eludir el cierre del periodo de origen. La instantánea del cierre anterior conserva su estado e importes.

Antes de migrar en otro entorno: copia de seguridad, detener escrituras mientras se aplican las migraciones y validar el esquema. La migración no ofrece una bajada que descarte la exención. La copia local de desarrollo previa permanece en `var/aselto-before-exemption.sql`, fuera del repositorio.

## API y exportaciones

Las operaciones de guardar borrador, confirmar y validar admiten `tax_exempt` como booleano JSON. Omitirlo equivale a false, siguiendo la semántica actual de reemplazo de campos del formulario/API; los consumidores deben enviarlo cuando mantengan una exención. No se aceptan cadenas, números ni null para este indicador.

Para un exento, `tax_rate` y `surcharge_rate` se omiten o se envían como null/cadena vacía. Un porcentaje, incluso «0», o un recargo junto con `tax_exempt: true` se rechazan con HTTP 422 y `invalid_tax_exemption`. Las entradas con tipo incorrecto del indicador responden HTTP 400. Las cuotas e importes calculados siguen siendo solo de salida.

Ejemplo de campos para un ingreso de autónomo, junto con la versión y los demás datos necesarios:

```json
{"tax_exempt": true, "tax_rate": null, "surcharge_rate": null, "withholding_rate": "15"}
```

Las respuestas documentales, sus cambios anteriores/posteriores y los movimientos de trimestres incluyen el indicador. El impuesto conserva `iva`, `igic` o `ipsi`. El listado trimestral y las exportaciones Excel/CSV identifican «IVA · Exento» (o su territorio), con la cuota cero. No se convierte en un cuarto territorio ni en un tipo 0 %.

## Cómo probarlo

1. Con un cliente de demostración, abrir un borrador desde Documentos o añadir uno ficticio. Marcar Exento: desaparecen el porcentaje y el recargo; se calcula cuota cero.
2. Para un ingreso de autónomo, introducir una retención permitida y comprobar el total. Guardar o confirmar con el flujo habitual.
3. Consultar el documento y su trimestre: se muestra Exento; revisar también el listado/exportación y el historial del documento.
4. Desmarcar en un borrador: el porcentaje vuelve a ser necesario. Para cambiar un confirmado o validado se mantienen las restricciones de su estado y asesoría.
5. Intentar corregir uno de un trimestre cerrado: se exige la reapertura. El cierre anterior conserva su instantánea.

Pruebas reproducibles: `tests/Documento/TaxExemptionTest.php`, `tests/Frontend/document-exemption.test.mjs` y los workflows de integración de documentos web/API, esquema y actualización en vivo. Los workflows usan datos sintéticos y retiran sus archivos. `license-backfill-workflow.php` se ejecuta solo en una base temporal vacía y comprueba el esquema antiguo sin depender del ORM actual.
