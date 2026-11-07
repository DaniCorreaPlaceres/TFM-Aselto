# Alta de clientes

## Objetivo

Un cliente se registra directamente, autónomo o sociedad, sin asesoría. El superadmin también puede dar de alta un cliente sin asesoría. El admin da de alta a los clientes de la suya.

## Fuera de alcance

El cobro y la factura de la licencia, el inicio del cliente, los documentos y el alta por correo con enlace.

## Criterios de aceptación

- El formulario público crea un cliente sin asesoría, autónomo o sociedad, con correo y contraseña.
- El superadmin da de alta un cliente sin asesoría y ve solo esos.
- El admin da de alta un cliente de su asesoría y no ve los de otra.
- El gestor ve los clientes de su asesoría y no puede darlos de alta.
- Un correo repetido o una contraseña de menos de 8 caracteres se rechaza.

## Comprobado

- `/registro` crea a Lucía, autónoma, sin asesoría. Entra y su acceso solo tiene Salir. `/clientes` responde 403.
- El superadmin ve a Lucía, a «Cliente de prueba» y da de alta Taller Norte como sociedad. No ve a los clientes de una asesoría.
- El admin de la asesoría de prueba da de alta a Mesa y no ve a Lucía ni a Taller Norte.
- El gestor ve a Mesa y no tiene el formulario de alta.
- PHPUnit cubre el registro sin asesoría, la contraseña corta, el nombre vacío, el correo repetido, el cliente de una asesoría y que otra asesoría no lo ve. Son 24 pruebas.
