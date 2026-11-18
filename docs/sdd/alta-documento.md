# Alta de documento

## Objetivo

El cliente añade un documento, escribe los datos, guarda un borrador o confirma el registro. La confirmación la hace la persona.

## Fuera de alcance

La lectura por IA, la validación de la asesoría y el cálculo del trimestre.

## Criterios de aceptación

- Desde «Añadir documento» se puede hacer una foto o subir un archivo PDF, JPEG, PNG o WebP.
- La pantalla de revisión muestra el archivo y los datos: fecha, tercero, NIF, base, tipo de IVA, cuota, total y si es ingreso o gasto.
- La cuota se calcula con la base y el tipo. Los tipos son 0, 4, 10 y 21.
- «Guardar borrador» conserva lo escrito. «Confirmar registro» exige los datos y deja el registro confirmado por el cliente.
- Un documento confirmado no entra en las cifras y no queda validado por la asesoría.
- El cliente solo ve los suyos. Si tiene asesoría, el documento queda ligado a esa.
- Lo que está en borrador o recién subido aparece como pendiente de su revisión.

## Comprobado

- Con `cliente@example.test` se subió un PNG, se escribió el gasto de Papelería Norte (100,00 €, IVA 21 %, total 121,00 €) y se guardó el borrador. La cuota salió 21,00 €. El inicio lo mostró como pendiente y las cifras siguieron en 0,00 €.
- Al confirmar, el registro quedó enviado y desapareció de lo pendiente. La lista lo muestra como gasto enviado. No tiene asesoría, igual que el cliente.
- Un admin que abre ese documento recibe 403. Otro cliente, `mesa@example.test`, recibe 404.
- PHPUnit: 45 pruebas. PHPStan nivel 6, sin errores.
- No se ha probado la cámara de un teléfono. El control de la foto lleva `capture=environment`.
- Si se confirma sin datos, cada campo dice lo que falta y un aviso muestra «Revisa los campos marcados». Al escribir la base y elegir el IVA, el total se rellena solo: 100,00 € al 21 % queda en 121,00 €. Guardar y confirmar muestran el aviso de éxito. Subir sin archivo avisa «Elige un archivo».
