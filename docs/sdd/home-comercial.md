# Home comercial

## Objetivo

Una página pública en `/` que presente Aselto y lleve a entrar. Sirve para captar a autónomos, sociedades y asesorías.

## Fuera de alcance

El registro, los precios, el alta de asesorías y de clientes, el inicio del cliente y la API.

## Criterios de aceptación

- Quien no tiene sesión ve `/` sin pasar por el acceso.
- La página usa el logotipo oficial y explica a quién va y qué hace.
- Hay una sola acción: Entrar. Si ya hay sesión, esa acción abre `/cuenta`.
- `GET /salud` sigue público.

## Comprobado

- Sin sesión, `/` responde 200 y la acción es Entrar, hacia `/entrar`.
- Con sesión, la misma acción es Tu acceso y abre `/cuenta`.
- En una anchura de móvil las tarjetas quedan una bajo otra.
