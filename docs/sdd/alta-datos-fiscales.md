# Alta con datos fiscales

## Objetivo

El alta de un cliente y el de una asesoría piden NIF o CIF, teléfono, dirección, código postal, municipio y provincia. El NIF, el CIF o el NIE se comprueban. El código postal tiene que corresponder a la provincia.

## Fuera de alcance

Editar el perfil, el impuesto del documento, el IGIC, el IPSI, el recargo de equivalencia, la retención de IRPF, Bootstrap y arrastrar el archivo. El alta de un gestor no pide estos datos.

## Criterios de aceptación

- `/registro`, el alta de cliente de una asesoría y el alta de una asesoría muestran esos campos.
- Un NIF, CIF o NIE mal formado no se guarda. El mensaje es «El NIF no es válido.»
- El teléfono son 9 cifras. El código postal son 5 y coincide con la provincia.
- Los datos quedan guardados con el cliente o con la asesoría.
- Quien ya estaba dado de alta sigue pudiendo entrar. Esos registros pueden tener los campos vacíos hasta el perfil.

## Comprobado

- En `/registro`, un NIF `00000000A` muestra «El NIF no es válido.» y no crea el acceso.
- Con `00000000T`, teléfono `600000000`, calle Mayor 1, `28001` y Madrid, el alta redirige a entrar y dice «Te hemos enviado un correo para crear tu contraseña.» El perfil guardado tiene esos datos.
- `cliente@example.test` sigue entrando. Su ficha anterior queda con los campos fiscales vacíos.
- `/asesorias/nueva` muestra los mismos campos. PHPUnit: 60 pruebas, incluida la que guarda el contacto de la asesoría. PHPStan nivel 6, sin errores. La migración `Version20261107120000` está aplicada en local.
