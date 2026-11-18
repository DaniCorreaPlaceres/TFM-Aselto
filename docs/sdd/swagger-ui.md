# Swagger UI

## Objetivo

Ofrecer documentación interactiva en `/api/docs`, usando el contrato existente y autorización Bearer.

## Fuera de alcance

Nuevas operaciones API, generación de contratos y cambios de permisos de negocio.

## Criterios de aceptación

- Página y contrato accesibles sin sesión; las operaciones conservan su autorización JWT.
- Contrato servido desde `openapi/openapi.yaml`, sin copia manual.
- Recursos Swagger UI locales y versión fijada, con licencia.
- Token no persistente y validador externo desactivado.
- Comprobar rutas HTTP, contrato, permisos y las comprobaciones del proyecto.

## Comprobado

- Rama `feature/swagger-ui` creada desde `develop` tras el merge del PR #24.
- Swagger UI 5.34.1 y licencia oficial servidos localmente. En el navegador carga OpenAPI 0.3.0, sus operaciones y el formulario de autorización Bearer.
- Consulta `GET /api/v1/me` ejecutada desde Swagger sin token: 401 JSON `token_missing`, desafío Bearer y respuesta sin caché. No se ha ejecutado manualmente una operación autenticada desde Swagger.
- Contrato HTTP con estado 200 y contenido idéntico al archivo del proyecto; CSS, bundle e inicializador devuelven 200 sin sesión.
- PHPUnit: 276 pruebas y 673 aserciones correctas. Integración de autenticación: 54 comprobaciones correctas.
- PHPStan nivel 6, contenedor, Twig, OpenAPI y formato del controlador correctos. El primer intento de PHPStan con www-data encontró una caché sin permiso de escritura; la ejecución posterior con el usuario del contenedor terminó sin errores.
- Configuración sin persistencia de autorización, sin configuración por consulta y sin validador externo, conforme a la [documentación oficial](https://swagger.io/docs/open-source-tools/swagger-ui/usage/configuration/).
