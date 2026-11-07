# Alta de asesorías y de su equipo

## Objetivo

El superadmin da de alta una asesoría y a su admin. El admin invita a los gestores de la suya.

## Fuera de alcance

El alta de clientes, el inicio del cliente, los precios y el envío de correos.

## Criterios de aceptación

- El superadmin crea una asesoría con nombre, correo del admin y contraseña inicial.
- El admin entra y ve solo el equipo de su asesoría. Puede invitar a un gestor.
- Un gestor no da de alta asesorías ni invita.
- Una asesoría no ve los datos de otra.

## Comprobado

- El superadmin entra, da de alta Norte con `norte@example.test` y la lista muestra esa asesoría junto a la de prueba.
- El admin de Norte entra, ve solo su equipo e invita a `gestor-norte@example.test`. El gestor entra.
- El admin de la asesoría de prueba ve a su admin y a su gestor, y no ve a Norte.
- Una contraseña de menos de 8 caracteres se rechaza y el formulario conserva el nombre y el correo.
- El admin no abre `/asesorias`. El gestor no abre `/equipo`. En desarrollo la respuesta es 403.
- PHPUnit cubre el nombre vacío, la contraseña corta, el correo repetido y la invitación a una asesoría que no existe. Son 17 pruebas.
