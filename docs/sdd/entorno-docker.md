# Entorno local en Docker

## Objetivo

Dejar comprobado que el entorno local arranca y que `GET /salud` confirma que PHP y MySQL responden.

## Fuera de alcance

Las carpetas de módulos, las pantallas, la API de producto, la publicación por Mercure y el acceso de usuarios.

## Criterios de aceptación

- `docker compose up --build` deja la web en el puerto 8080, MySQL en el 3306 y Mercure en el 9080.
- `GET /salud` responde 200 con `estado` igual a `ok` y `base_de_datos` igual a `ok`.

## Comprobado

- `docker compose up --build` deja la web en el puerto 8080, MySQL 8.4 sano en el 3306 y Mercure en el 9080. El hub, consultado sin tema, responde 400.
- `GET http://localhost:8080/salud` responde 200 con `{"estado":"ok","base_de_datos":"ok"}`.
