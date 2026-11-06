# Estructura básica de Symfony

## Objetivo

Crear los módulos previstos, cada uno con `Domain`, `Application`, `Infrastructure` y `Ui`, y dejar el dominio fuera del autowire.

## Fuera de alcance

Pantallas, casos de uso, tablas, mapeos de Doctrine y mover `GET /salud`. Los nombres de los módulos siguen los de la memoria.

## Criterios de aceptación

- Existen `Shared`, `Identidad`, `Asesoria`, `Cliente`, `Documento`, `Finanzas` y `Comunicacion`.
- Cada módulo tiene `Domain`, `Application`, `Infrastructure` y `Ui`.
- Las carpetas `Domain` no se registran como servicios.
- El contenedor compila y `GET /salud` sigue respondiendo.

## Comprobado

- Los siete módulos tienen `Domain`, `Application`, `Infrastructure` y `Ui`.
- `config/services.yaml` excluye las carpetas `Domain` del autowire. `php bin/console lint:container` termina bien.
- `GET http://localhost:8080/salud` responde 200 con `{"estado":"ok","base_de_datos":"ok"}`.
