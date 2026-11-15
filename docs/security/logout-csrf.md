# Cierre de sesión con CSRF

El botón **Salir** envía POST a `/salir` con el campo `_csrf_token`, generado para el identificador `logout`. El formulario ya incluía ese valor; ahora `security.firewalls.main.logout.enable_csrf` activa su comprobación nativa en Symfony.

También se restringe a POST el importador `security.route_loader.logout` en `config/routes/security.yaml`. Registraba `_logout_main` con método ANY, aunque el controlador de la aplicación tenía su ruta `logout` declarada como POST. Restringir solo el controlador no protegía el otro registro. Ambas rutas resuelven ahora únicamente POST.

Con una sesión vigente:

- Campo ausente, incorrecto, con tipo no válido, de otra sesión o de otro propósito: **403**, sin cerrar sesión ni limpiar la cookie de suscripción.
- GET, HEAD, OPTIONS, PUT, PATCH o DELETE: **405**, incluso si se incluye un token válido en la URL.
- Token válido del formulario: **302** al acceso, invalidación de sesión, tiempos e identificador y limpieza de cookie de Mercure. Los tokens de esa sesión no sirven después de volver a entrar.

Se mantiene el lector de parámetros de Symfony. El formulario usa el cuerpo de la petición; no se añade un mecanismo propio paralelo ni se cambia la generación de URLs del framework. La salida web no revoca un JWT de API ni modifica su duración. Si la sesión ya ha caducado, F05 la invalida antes del chequeo de logout: un POST puede recibir 303 de reentrada, sin necesitar un token de una sesión vencida.

## Comprobación local

```powershell
docker compose exec -T --user www-data app php tests/Integration/logout-csrf-workflow.php
docker compose exec -T --user www-data app php tests/Integration/live-quarter-workflow.php
docker compose exec -T --user www-data app php tests/Integration/session-expiration-workflow.php
docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php
```

El workflow nuevo realiza login real, genera tokens desde los formularios de la aplicación y mantiene dos sesiones separadas. Comprueba los rechazos sin perder acceso, la salida válida, la reentrada, la cookie de suscripción y JWT. Usa identidades sintéticas, sesión simulada y una caché de contadores aislada; revierte los datos y no imprime credenciales ni tokens. No envía SMTP ni IA externos.

Para comprobarlo manualmente en local, entrar y usar **Salir** debe volver al acceso. Desde una sesión vigente, una petición POST sin el campo debe recibir 403 y permitir seguir navegando; abrir `/salir` como enlace GET debe recibir 405. Recargar una página de la plataforma permite obtener el formulario actual para volver a intentar la salida.

[Evidencias](evidence/f06-logout-csrf.json), [probe posterior](evidence/f06-after-logout.json) y [seguimiento](remediation.md). No se ha realizado revisión visual de navegador, ataque entre sitios reales ni comprobación de Hostinger. Las páginas generales de error no cambian. Referencia: [configuración CSRF de logout en Symfony 7.4](https://symfony.com/doc/7.4/reference/configuration/security.html#security-firewalls-name-logout-enable-csrf). La auditoría original conserva su puntuación.
