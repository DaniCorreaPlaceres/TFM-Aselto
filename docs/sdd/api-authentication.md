# API v1: acceso JWT y usuario actual

## Objetivo

Abrir el primer bloque de la tarea 24: acceso por correo y contraseña y consulta del usuario actual mediante JWT, con el mismo proveedor de usuarios, comprobación de activación y datos de identidad que la web.

## Fuera de alcance

Documentos, perfiles fiscales, trimestres, mensajes, altas e invitaciones por API, renovación y revocación de tokens, aplicación móvil y cambios en el acceso web.

## Cambios previstos

- `POST /api/v1/auth/login`: JSON con `email` y `password`. Devuelve un JWT firmado, `token_type: Bearer` y `expires_in: 3600`. Las cuentas inexistentes, inactivas o con contraseña incorrecta reciben el mismo error.
- `GET /api/v1/me`: exige `Authorization: Bearer …` y devuelve solo identificador, correo, papel, identificador de asesoría opcional y activación del usuario actual. No permite elegir otro usuario ni acceder a datos fiscales.
- Firewalls de API independientes y sin sesión. La cookie web no autentica la API y el JWT no crea una sesión web. En cada petición se recarga el usuario; no se confía en papeles antiguos del token.
- Errores JSON con `error.code` y `error.message` en español, códigos HTTP coherentes, sin trazas ni detalles internos. Las respuestas de autenticación y datos de usuario no se cachean. El token se admite solo en la cabecera Authorization.
- Claves RSA locales fuera de Git, usando la configuración Lexik existente. Se documenta su generación sin sobrescribir claves existentes. No se publican tokens ni contraseñas en ejemplos o resultados de pruebas.
- Contrato OpenAPI de ambos endpoints y ejemplos con datos ficticios.

## Criterios de aceptación

- Los cuatro papeles activos pueden obtener un token y consultar únicamente su identidad. El usuario sin asesoría devuelve `firm_id: null`.
- Falta de credenciales, JSON mal formado, cuerpo que no sea un objeto o campos de tipo incorrecto se rechazan. La contraseña no se recorta ni normaliza; el correo usa la normalización del proveedor existente.
- Contraseña incorrecta, cuenta inexistente e inactiva producen el mismo 401. No se devuelve el motivo de activación ni datos de una cuenta ajena.
- Token ausente, malformado, alterado, caducado, firmado con otra clave o de un usuario eliminado o desactivado se rechaza con 401 JSON. Los cambios de papel y asesoría se reflejan al consultar, sin privilegios heredados del JWT.
- Una sesión web sin Bearer no accede a `/api/v1/me`. Un JWT no autentica `/cuenta`. La web conserva sus rutas, CSRF y acceso actuales.
- Rutas API inexistentes y métodos incorrectos devuelven JSON 404/405 y cabeceras HTTP apropiadas. Una excepción interna devuelve 500 genérico, sin contraseñas, token ni trazas.
- OpenAPI describe petición, autenticación, respuestas y errores. Pruebas de formato, errores y recorrido HTTP completo con firma real, además de regresiones de web y casos de uso existentes.

## Referencias

[Documentación oficial de LexikJWTAuthenticationBundle](https://symfony.com/bundles/LexikJWTAuthenticationBundle): firewalls separados, JSON login y autenticación JWT. Configuración contrastada con la versión instalada.

## Comprobado

- Rama `feature/api-authentication`, creada desde `develop`. Solo cubre el primer bloque de la tarea 24; documentos, trimestres y comunicación siguen pendientes.
- PHPUnit en Docker: **261 pruebas y 653 aserciones**, correctas. Incluye JSON de acceso mal formado y tipos incorrectos, contraseña con espacios, tratamiento de errores internos sin detalles, aislamiento respecto a errores web y conservación de cabeceras HTTP.
- Integración con MySQL, kernel HTTP y firma RSA real: **54 comprobaciones**, correctas. Acceso y consulta de los cuatro papeles, normalización del correo, contraseña sin recortar, respuestas sin caché y sin cookie de sesión, errores indistinguibles de credenciales, tokens ausentes, alterados, caducados o firmados por otra clave, papeles y asesoría actuales frente a claims antiguos, cuentas eliminadas o desactivadas, separación entre sesión web y Bearer y errores JSON 404/405.
- Los usuarios sintéticos se crean en una transacción y se deshacen al terminar. La prueba no imprime contraseñas ni tokens y usa un contenedor de pruebas con caché separada para simular peticiones independientes.
- Regresión del trimestre: **43 comprobaciones** correctas. Regresión de mensajería: **37**, correctas. Sus datos y adjuntos se deshicieron, conservando los documentos y cierres existentes.
- Petición real a Nginx: `GET /api/v1/me` sin credenciales devuelve 401 JSON, `WWW-Authenticate: Bearer` y `Cache-Control: no-store, private`.
- PHPStan nivel 6, contenedor y contrato OpenAPI correctos. Formato Symfony comprobado en las copias LF de los ocho archivos PHP de la tarea. `git diff --check` correcto.
- Claves locales generadas con `--skip-if-exists` como www-data. Se comprobó con `git check-ignore` que ambas claves PEM quedan fuera de Git. No se ha modificado ninguna clave existente.
- OpenAPI actualizado a 0.2.0 y guía de uso en [../api/README.md](../api/README.md), con prueba manual que no imprime el token.

## Cómo probar

Generar primero las claves locales si faltan:

`docker compose exec -T --user www-data app php bin/console lexik:jwt:generate-keypair --skip-if-exists`

Ejecutar la comprobación con usuarios ficticios:

`docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php`

Para una prueba manual con un usuario existente, seguir los comandos de PowerShell de [la guía de la API](../api/README.md). La sesión del navegador no sustituye a la cabecera Bearer.

## Pendiente

Los siguientes bloques de la tarea 24 incorporarán documentos, revisión de asesoría, trimestres, mensajes y sus permisos de negocio reutilizando los casos de uso existentes. No se ha implementado renovación, revocación ni un cierre de sesión JWT.
