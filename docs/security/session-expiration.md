# Caducidad de sesión web

La sesión web requiere volver a entrar tras **30 minutos sin actividad HTTP de usuario** o **8 horas desde el último acceso correcto**, aunque haya actividad reciente. Son valores del producto, configurados en `config/services.yaml` mediante `app.session_idle_seconds` y `app.session_absolute_seconds`. Deben ser positivos y el primero no puede superar al segundo.

El servidor registra inicio y última actividad; no toma horas del navegador ni de parámetros de la petición. Cada petición web con sesión autenticada comprueba los límites antes del firewall, del controlador y de otras respuestas de acceso que puedan mostrar la navegación autenticada. Una actividad válida renueva únicamente la última actividad. El inicio absoluto se sustituye solo al volver a autenticarse correctamente mediante la web.

## Qué cuenta como actividad

Navegar por la web, enviar formularios autenticados y usar el botón de actualización manual de cifras. La actualización manual envía una marca explícita que se consume en esa petición; los refrescos siguientes vuelven a ser automáticos. La marca describe el comportamiento del cliente, no demuestra presencia humana.

No renuevan los tiempos las autorizaciones periódicas de Mercure, los fragmentos automáticos del trimestre, la API JWT, los recursos estáticos ni `/salud`. Escribir en un formulario sin enviarlo no produce actividad del servidor: no se añade heartbeat ni aviso preventivo en esta tarea. Usar otra pestaña con la misma sesión sí puede renovar su inactividad mediante una petición normal; no mueve la frontera absoluta.

## Al caducar

Se invalida la sesión, se elimina el token web en memoria y se crea un identificador anónimo nuevo. Los CSRF anteriores dejan de servir. Se limpia también la cookie de suscripción de Mercure mediante un evento entre adaptadores, sin añadir dependencias HTTP a dominio/aplicación.

La navegación privada vuelve a `/entrar` con un aviso en español; un POST caducado vuelve con 303 y no se ejecuta. Un GET permitido como público por las reglas de seguridad continúa como anónimo después de invalidar la sesión: los enlaces de recuperación, activación e invitación siguen siendo utilizables, sin conservar la autenticación anterior. También se limpia la cookie de suscripción en esa respuesta pública.

Las peticiones automáticas reciben 401, `session_expired`, sin HTML fiscal y con caché privada desactivada. El controlador de tiempo real cierra la conexión y retira las cifras al recibir esa respuesta, como hace cuando cambian los permisos.

Los tiempos se comprueban al recibir peticiones; no se abortan operaciones que ya empezaron autorizadas ni se retiran respuestas previamente entregadas. No se cambia el TTL de las credenciales del hub ni se garantiza revocar instantáneamente conexiones ya abiertas; su renovación queda bloqueada y el cliente las cierra al detectar la caducidad. Las credenciales y duración de la API JWT permanecen independientes.

## Incorporación y almacenamiento

Las sesiones anteriores que no contengan marcas válidas requieren entrar de nuevo una vez al desplegar el cambio. Una marca ausente, con tipo incorrecto, desordenada o futura no se completa con la hora actual: se invalida. El acceso posterior conserva la rotación de identificador de Symfony.

`framework.session.gc_maxlifetime` toma el máximo absoluto de 8 horas, para no depender del valor PHP anterior, que podría retirar datos antes. La recolección de sesiones es mantenimiento; no sustituye a los dos controles de aplicación. Los atributos de cookie y el transporte HTTPS siguen su configuración previa. No hay migraciones ni nuevas dependencias.

Las pruebas antiguas que inyectaban identidades de sesión usan ahora `tests/Support/AuthenticatedSession.php` para representar accesos actuales. No se introduce ninguna excepción a la política productiva para que los tests pasen.

## Comprobación

```powershell
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T --user www-data app php tests/Integration/session-expiration-workflow.php
node --test tests/Frontend/live-quarter.test.mjs
```

El workflow usa el kernel y autenticador reales, un reloj controlado y almacenamiento de sesión simulado; no espera 30 minutos ni 8 horas, ni altera el reloj del equipo. Comprueba umbrales, actividad automática/manual, inicio absoluto, reentrada, CSRF, cookie, metadatos antiguos/futuros y separación JWT. Sus identidades y perfiles son sintéticos y se revierten. Los demás workflows conservan sus comprobaciones de permisos y negocio.

Para una comprobación manual local se pueden reducir temporalmente ambos parámetros, limpiar la caché y entrar de nuevo; restaurar los valores antes de guardar cambios. Visitar una página privada tras la espera debe pedir acceso; dejar el trimestre abierto con renovaciones automáticas tampoco debe mantenerlo indefinidamente. No hay un endpoint público para mover el reloj o cambiar estos tiempos.

[Evidencia](evidence/f05-session-expiration.json) y [seguimiento de F05](remediation.md). Hostinger, revisión visual de navegador y replay contra el almacenamiento productivo no se han verificado. La invalidación se apoya en la [API de sesión de Symfony 7.4](https://symfony.com/doc/7.4/session.html); los límites de inactividad y absolutos se inspiran en [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), sin presentar los valores elegidos como una certificación.
