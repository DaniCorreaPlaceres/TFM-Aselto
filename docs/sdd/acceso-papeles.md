# Acceso y papeles

## Objetivo

Una persona entra con correo y contraseña. La sesión web conoce su papel: superadmin, admin de la asesoría, gestor o cliente.

## Fuera de alcance

Alta de asesorías, invitar al equipo, alta de clientes, el inicio de producto, la API y el JWT.

## Criterios de aceptación

- Hay cuatro papeles. El superadmin no pertenece a una asesoría. El admin y el gestor sí. El cliente puede no tener asesoría.
- `GET /entrar` muestra el formulario. Un acceso correcto lleva a `/cuenta` y muestra el papel. Salir cierra la sesión.
- `GET /salud` sigue público.
- Los usuarios de prueba son solo de desarrollo y no usan datos fiscales reales.

## Comprobado

Pendiente.
