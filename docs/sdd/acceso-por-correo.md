# Acceso por correo

## Objetivo

Nadie escribe la contraseña de otra persona. Al dar de alta un acceso se envía un correo: el enlace confirma el correo y permite crear la contraseña. También se puede pedir otro enlace si se olvida.

## Fuera de alcance

El cobro de la licencia, el inicio del cliente y el envío de correos que no sean de acceso.

## Criterios de aceptación

- El alta de una asesoría, de un gestor y de un cliente pide el correo y no la contraseña.
- El correo llega al buzón local. El enlace caduca y, al usarlo, la persona crea su contraseña.
- Sin ese paso no se puede entrar.
- Quien olvida la contraseña pide otro enlace y crea una nueva.
- El admin y el superadmin no llegan a conocer esa contraseña.
- Los accesos de desarrollo que ya tenían contraseña siguen entrando.

## Comprobado

- El registro público, el alta de una asesoría y la invitación a un gestor piden el correo y no la contraseña.
- El correo llega a Mailpit, en http://localhost:8025. El enlace abre el formulario de la contraseña.
- Una contraseña de menos de 8 caracteres se rechaza. El enlace usado ya no vale.
- Sin crear la contraseña, el acceso dice «Todavía no has creado tu contraseña. Revisa tu correo.»
- Tras crearla, esa persona entra. Quien olvida la contraseña recibe otro enlace y entra con la nueva.
- `superadmin@example.test` y `admin@example.test` siguen entrando con `aselto-dev`.
- PHPUnit: 31 pruebas. PHPStan, nivel 6, sin errores.
