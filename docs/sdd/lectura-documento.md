# Lectura del documento

## Objetivo

Al añadir un documento, un extractor propone los datos. La persona los revisa, los corrige, guarda un borrador o confirma. La lectura no confirma el registro.

## Fuera de alcance

La validación de la asesoría, el cálculo del trimestre y un porcentaje de confianza.

## Criterios de aceptación

- Si hay clave de OpenAI, el adaptador de visión lee el archivo y propone fecha, tercero, NIF, base, tipo de IVA y si es ingreso o gasto.
- Si no hay clave, un adaptador de ejemplo rellena datos de prueba y la pantalla lo dice.
- La cuota y el total se calculan con la base y el tipo. No se usan los importes que devuelva el modelo para esos dos campos.
- Si la lectura falla, el documento queda subido y la persona escribe los datos.
- El texto, cuando la lectura es real, es «Hemos extraído estos datos. Revísalos antes de confirmar».
- Confirmar sigue siendo una acción de la persona. Las cifras del inicio no cambian.

## Comprobado

- Sin clave, con `cliente@example.test`, se subió un PNG. La revisión mostró un gasto de ejemplo: Proveedor de ejemplo, NIF 00000000T, 12/03/2026, base 100,00 €, IVA 21 %, cuota 21,00 € y total 121,00 €. El texto era «No hay lectura automática. Estos datos son un ejemplo. Revísalos antes de confirmar».
- Se corrigió el tercero, se guardó el borrador y el aviso dijo «El borrador está guardado». El texto de ejemplo se mantuvo.
- Al confirmar, el registro quedó enviado. El inicio siguió en 0,00 € y el documento salió de lo pendiente.
- PHPUnit: 56 pruebas. PHPStan nivel 6, sin errores. La migración `Version20261107104500` está aplicada en local.
- Con la clave en `.env.local`, se subió una factura de prueba. La revisión dijo «Hemos extraído estos datos. Revísalos antes de confirmar» y propuso el gasto: Papelería Ejemplo, NIF B12345674, 12/03/2026, base 80,00 €, IVA 21 %, cuota 16,80 € y total 96,80 €. Las pruebas del adaptador siguen usando una respuesta simulada y comprueban que un fallo no enseña la clave ni inventa el ejemplo.
