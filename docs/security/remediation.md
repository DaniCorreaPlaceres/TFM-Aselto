# Seguimiento de correcciones

La [auditoría original](README.md) conserva su fecha, matriz y puntuación. Este documento registra verificaciones posteriores, sin considerar cerrada toda la tarea 43 ni los controles que todavía tengan otras carencias.

## F01 · Revocación JWT al restablecer contraseña

Implementado y verificado en Docker local el **15/11/2026**, en `feature/password-reset-token-revocation`, desde develop `6acdd61`. Ficha: [password-reset-token-revocation](../sdd/password-reset-token-revocation.md). No se acredita todavía despliegue productivo.

`User::withPassword()` incrementa `authVersion`. El caso de uso compartido `ChoosePassword` ya guarda el usuario bajo una transacción con bloqueo; el validador JWT y la comparación de identidades de sesión ya comprueban esta versión. El cambio conserva correo, identidad, rol, asesoría y el contrato HTTP. Se aplica también al elegir la misma contraseña. No necesita migración ni nuevos secretos.

Verificaciones ejecutadas:

- La prueba nueva de dominio y la integración fallaron antes de corregir el contador: la versión no aumentaba.
- PHPUnit completo: **347 pruebas / 869 aserciones**, correctas, incluida la regresión de concurrencia entre correo y contraseña.
- Autenticación web/API: **74 comprobaciones** correctas, en dos ejecuciones. Token anterior válido antes del reset y `401 token_invalid` en tres peticiones posteriores consecutivas; contraseña anterior rechazada y nueva aceptada con JWT válido. Sesión anterior rechazada y actual válida; reset a la misma contraseña revoca igualmente. Enlaces consumidos/caducados e intento con contraseña rechazada no alteran la versión ni invalidan accesos válidos.
- Administración de asesorías y cambio de correo: **70 comprobaciones** correctas.
- [Repetición de la prueba específica de auditoría](evidence/f01-after-reset.json): JWT previo al reset 200 y posterior 401 (`token_invalid`), contraseña anterior 401, nueva 200 e identidades sin cambios tras rollback. El mismo archivo sigue mostrando otros hallazgos no corregidos; no equivale a superarlos.
- PHPStan y formato de los cuatro archivos PHP modificados correctos; comprobación de diferencias sin errores.

Reproducir en el entorno local ya preparado, secuencialmente:

```powershell
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php
docker compose exec -T --user www-data app php tests/Integration/platform-firms-workflow.php
docker compose exec -T --user www-data app php docs/security/audit-probes.php
```

Los workflows crean cuentas sintéticas y revierten sus cambios; no imprimen tokens ni contraseñas, ni envían correo o documentos a proveedores. Las verificaciones de sesión simulan una identidad serializada y pasan por el kernel real; no constituyen una revisión visual de navegador.

Límites: afecta a JWT de API y sesiones en su siguiente petición; no expulsa una respuesta que ya esté en ejecución ni cambia las credenciales Mercure de corta duración. No añade un endpoint general de logout JWT. Los workflows negativos todavía no están incorporados como comprobaciones obligatorias de CI (F13 pendiente).

## F02 · Origen confiable de enlaces y validación de Host

Implementado y verificado en Docker local el **15/11/2026**, en `feature/canonical-account-links`, desde develop `8f1cefa` después de integrar F01 (PR #48). Ficha: [canonical-account-links](../sdd/canonical-account-links.md).

Los notifiers de activación, recuperación, invitación y confirmación de correo comparten `CanonicalAccountUrls`. Este clona el generador de rutas compilado y le asigna un contexto creado desde DEFAULT_URI, sin cambiar el router compartido ni cargar la colección de rutas en cada envío. Conserva esquema, host, puerto y prefijo configurados, y el generador escapa el token. Rechaza configuraciones con credenciales, consulta, fragmento, controles, barra inversa, host/puerto inválido o HTTP fuera del loopback.

`framework.trusted_hosts` usa TRUSTED_HOSTS: nombres exactos autorizados por expresión regular anclada. El valor local permite localhost, 127.0.0.1 y [::1]. El origen y la lista se configuran juntos al desplegar; [instrucciones](../local-installation.md). No se habilita confianza en proxies arbitrarios. La política local de HTTP loopback no acredita HTTPS productivo, que sigue pendiente.

Verificaciones ejecutadas:

- PHPUnit completo: **366 pruebas / 891 aserciones**, correctas. Casos de origen inseguro, HTTPS con puerto/prefijo, token escapado, contexto compartido intacto y HTTP loopback.
- Workflow nuevo: **17 comprobaciones**, correcto. Captura los cuatro correos sensibles en memoria con rutas reales, frente a un contexto de host/puerto/prefijo ajenos. Rechaza hosts ajenos en web/API y no confía en cabeceras X-Forwarded de clientes arbitrarios. Sin SMTP, IA ni escrituras de base de datos.
- [HTTP real a través del servidor local](evidence/f02-http-hosts.json): /recuperar con Host localhost:8080 y 127.0.0.1:8080 responde 200; host ajeno y subdominio engañoso responden 400.
- Regresiones: autenticación web/API **74**, invitaciones **100** y administración de asesorías/cambio de correo **70** comprobaciones correctas; fixtures retirados o revertidos.
- [Repetición del probe de auditoría](evidence/f02-after-links.json): el host del enlace capturado pasa a localhost pese al contexto ajeno; F01 sigue dando 401 con el JWT previo y se conserva la identidad tras rollback. Otros hallazgos como contraseña común y CSRF de salida siguen presentes.
- PHPStan, contenedor, los 22 YAML y PHP-CS-Fixer de los siete PHP modificados/nuevos correctos. Línea base de la auditoría conservada.

Reproducción, además de los workflows de regresión:

```powershell
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T --user www-data app php tests/Integration/canonical-account-links-workflow.php
docker compose exec -T --user www-data app php docs/security/audit-probes.php
(Invoke-WebRequest http://localhost:8080/recuperar -Headers @{Host='audit-invalid.example.test'} -SkipHttpErrorCheck).StatusCode
```

Las evidencias no contienen tokens reales, cuerpos de mensajes privados ni cookies. La prueba utiliza token y destinatario sintéticos y nunca envía correo externo. El despliegue con dominio real, proxy, certificado y correo queda para la tarea de producción. Las instrucciones siguen el mecanismo de [hosts confiables de Symfony](https://symfony.com/doc/current/reference/configuration/framework.html#trusted-hosts) y mantienen independiente el contexto de generación.

## F03 · Límites de acceso y recuperación

Implementado y verificado en Docker local el **15/11/2026**, en `feature/authentication-rate-limits`, desde develop `2b1572a` después de integrar F02 (PR #49). Ficha: [authentication-rate-limits](../sdd/authentication-rate-limits.md). [Evidencias y alcance](evidence/f03-rate-limits.json).

Symfony RateLimiter y Lock añaden ventanas deslizantes persistidas en caché de disco y bloqueos flock. Acceso web/API comparte 5 intentos por minuto por correo normalizado + IP y 60 por IP. Se cuentan también éxitos y no se reinicia el contador con credenciales válidas. No existe un bloqueo global de la cuenta desde otras IP: limita ataques concentrados y variación de correos desde una IP, sin afirmar protección completa frente a botnets distribuidas.

Recuperación HTTP: 3 solicitudes por correo y 10 por IP cada 15 minutos. La comprobación ocurre después de CSRF y antes de consultar la cuenta o generar tokens; aplica igual a cuentas activas, inactivas y ausentes. Bloquear una solicitud no reemplaza enlaces ni emite notificaciones. No hay bloqueo permanente ni modificación de credenciales. Las claves son HMAC; no se guardan el correo ni la IP en claro. La normalización agrupa mayúsculas, espacios exteriores y variantes acentuadas para no depender de diferencias de cotejamiento de la base de datos.

API y web devuelven 429 con Retry-After positivo y sin caché; la API usa `too_many_attempts` y mantiene el esquema JSON. OpenAPI 0.5.1 añade esa respuesta; la versión del producto no cambia. Los caminos codificados se normalizan como en el autenticador de Symfony, evitando evadir el límite mediante percent-encoding. La validación del cuerpo JSON sigue ocurriendo antes de autenticar.

Verificaciones ejecutadas:

- PHPUnit completo: **370 pruebas / 982 aserciones**, correctas. Umbrales, variantes de correo, aislamiento de IP, recuperación por correo entre IP y expiración con estado temporal de prueba, sin esperas reales.
- HTTP sintético: **45 comprobaciones**, correctas. Alternancia web/API, rutas codificadas, 429/Retry-After, acceso desde otra IP, límite global pese a cambio de correo, igualdad de mensajes para cuentas activas/inactivas/inexistentes, supresión de correo/token y CSRF previo al contador. Notificaciones en memoria y rollback de fixtures, sin SMTP/IA reales.
- Persistencia/concurrencia: **6 procesos simultáneos, 30 intentos, 5 aceptados y 25 limitados**, y el proceso padre observa el límite. Usa las políticas de configuración reales con FilesystemAdapter/CacheStorage/FlockStore en un namespace de prueba, sin consumir ni limpiar contadores de la aplicación. Se confirma también la inyección de lock.default.factory en los limitadores del contenedor.
- Regresión de autenticación **74** y enlaces canónicos **17**, correctas; probe de auditoría conserva JWT previo 401, host canónico y rollback. La regresión y el probe aíslan sus contadores para no provocar bloqueos locales ni resultados dependientes de ejecuciones previas.
- PHPStan, contenedor, 23 YAML, OpenAPI y Composer validate correctos; consulta Composer sin avisos ni abandonos. Se añaden dos componentes oficiales; OptionsResolver, ya fijado, pasa a dependencia de ejecución. No se actualizan los paquetes existentes ni se cambian protecciones GitHub.

Reproducción en Docker local preparado, de forma secuencial:

```powershell
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T --user www-data app php tests/Integration/authentication-rate-limits-workflow.php
docker compose exec -T --user www-data app php tests/Integration/authentication-limit-storage-workflow.php
docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php
docker compose exec -T --user www-data app php tests/Integration/canonical-account-links-workflow.php
```

Almacenamiento acotado a procesos de un mismo servidor y disco accesible para el usuario de PHP. Limpiar caché o cambiar el secreto puede reiniciar presupuestos; en varios servidores se requerirá almacenamiento y bloqueos compartidos. La IP procede de Request y no se habilita confianza en proxies arbitrarios. No se ha comprobado Hostinger ni la topología productiva. No sustituye protección de red contra DDoS. Otras altas y correos administrativos conservan sus flujos y quedan fuera de esta corrección acotada. Los workflows siguen pendientes de incorporación como checks obligatorios (F13).

Fuentes técnicas: [RateLimiter de Symfony 7.4](https://symfony.com/doc/7.4/rate_limiter.html) para almacenamiento/bloqueos y [protección de acceso](https://symfony.com/doc/7.4/security.html#limiting-login-attempts) como referencia. Aquí se aplica un presupuesto previo compartido que cuenta todas las solicitudes admitidas, en lugar de contadores separados por firewall.

## F04 · Política de selección y lista local de contraseñas comunes

Implementado y verificado en Docker local el **15/11/2026**, en `feature/password-policy`, desde develop `7d4efd8` tras integrar F03 (PR #50). Ficha: [password-policy](../sdd/password-policy.md). [Política, origen y licencia](password-list.md).

`ChoosePassword` y `PlatformInvitations` comparten `PasswordPolicy`: mínimo 15 y máximo 128 puntos de código Unicode, sin reglas de composición ni rotación periódica. El puerto CommonPasswords tiene un adaptador local con 10.001 entradas importadas y nueve complementos previsibles. Rechaza también valores solo de espacios. No hay consultas externas ni cambio de algoritmo; los espacios y Unicode de una credencial aceptada se conservan exactamente. Solo se normaliza una copia para comparar con la lista.

La política se ejecuta antes de hash/escritura/consumo del enlace. Los errores de longitud/comunes tienen mensajes en español y los POST inválidos se renderizan con 422. No se obliga a cambiar contraseñas existentes: la comprobación de acceso mantiene compatibilidad, incluido un usuario sintético con contraseña anterior de ocho caracteres. No se modifican credenciales de demo.

Verificaciones ejecutadas:

- PHPUnit completo: **388 pruebas / 1.017 aserciones**, correctas. Longitudes 14/15/128/129, UTF-8 inválido, frases sin símbolos, Unicode, variantes comunes, digest/cantidad/pertenencia de la lista, archivo ausente/alterado y hash largo sin truncamiento.
- Activación y recuperación HTTP: **33 comprobaciones**, correctas. Rechazo con mensaje y estado adecuados, conservación del enlace/hash/versión, aceptación posterior, uso único, credencial heredada válida y comparación exacta de contraseña larga con espacios y sufijos distintos tras los primeros 72 bytes.
- Invitaciones HTTP para asesoría, autónomo y empresa: **118 comprobaciones**, correctas, incluyendo comunes/largas rechazadas sin crear cuenta ni consumir invitación y aceptación posterior.
- Regresiones: autenticación **74**, límites de acceso/recuperación **45** y asesorías/cambio de correo **70**, correctas. Fixtures sintéticos revertidos/retirados, sin SMTP ni IA externos.
- [Probe posterior](evidence/f04-after-policy.json): contraseña común corta no aceptada, común larga rechazada por lista, JWT anterior tras reset 401, host canónico e identidades conservadas tras rollback. Otros hallazgos siguen presentes; no se recalcula la auditoría histórica.
- PHPStan, contenedor, 61 Twig y 23 YAML correctos. Recurso externo preservado byte a byte mediante .gitattributes y digest fijado; sin nuevas dependencias de Composer.

Reproducir en Docker local ya preparado, secuencialmente:

```powershell
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T --user www-data app php tests/Integration/password-policy-workflow.php
docker compose exec -T --user www-data app php tests/Integration/platform-invitations-workflow.php
docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php
docker compose exec -T --user www-data app php tests/Integration/authentication-rate-limits-workflow.php
docker compose exec -T --user www-data app php docs/security/audit-probes.php
```

La lista finita no acredita ausencia en todas las filtraciones ni alta entropía de cualquier valor aceptado. Su actualización es manual y verificable; si falta o cambia el recurso sin actualizar el digest, la selección falla explícitamente. Hostinger y revisión visual de navegador no comprobados en esta tarea. Los workflows siguen pendientes de incorporación como checks obligatorios (F13).

## F05 · Caducidad por inactividad y duración absoluta

Implementado y verificado en Docker local el **15/11/2026**, en `feature/session-expiration`, desde develop `239f8e4` después de integrar F04 (PR #51). [Ficha](../sdd/session-expiration.md), [funcionamiento y parámetros](session-expiration.md) y [evidencia](evidence/f05-session-expiration.json).

El servidor registra inicio y última actividad tras el acceso web correcto. La política comprueba 30 minutos de inactividad y 8 horas absolutas antes de los controladores, del firewall y de respuestas de acceso que puedan renderizar navegación autenticada. La actividad renueva solo la inactividad; el inicio absoluto permanece. Fechas futuras, campos inválidos o metadatos ausentes requieren reautenticación. La recolección PHP toma las 8 horas como máximo de conservación, pero no sustituye la validación de aplicación.

Las renovaciones Mercure y fragmentos automáticos no cuentan como actividad. El botón manual se identifica solo en su propia petición. No se utiliza esa marca como prueba de presencia humana. API JWT y recursos/diagnóstico quedan separados. Al caducar se invalida sesión/CSRF, se limpia el token en memoria y la cookie de suscripción; navegación privada vuelve al acceso, un POST se intercepta con 303 y peticiones automáticas reciben 401 privado. Los GET públicos continúan como anónimo según el AccessMap de Symfony, conservando los enlaces públicos de recuperación/invitación sin mantener la sesión caducada.

Verificaciones ejecutadas:

- PHPUnit: **404 pruebas / 1.042 aserciones**, correctas. Umbrales exactos, renovación sin mover el inicio, estados antiguos/incorrectos/futuros y parámetros inválidos.
- Kernel HTTP con reloj controlado: **78 comprobaciones**, correctas. Acceso real con rotación del identificador, fronteras de 30 minutos y 8 horas, actividad automática/manual, reentrada con sesión anónima sustituta, CSRF anterior rechazado, cookie eliminada, independencia JWT y prioridad frente al limitador de acceso. Recuperación pública mantiene el formulario tras descartar autenticación caducada. Datos sintéticos revertidos.
- JavaScript de tiempo real: **11 pruebas**, correctas. La actualización manual marca una sola petición y el 401 de renovación cierra el flujo y retira cifras, conservando el comportamiento previo de permisos.
- Regresiones ejecutadas: autenticación **74**, actualización real **38**, trimestres **43**, mensajería **37**, subida documental **43**, asesorías **70**, invitaciones **118** y clientes independientes **55**, correctas. Los fixtures que inyectaban identidades incluyen ahora marcas válidas mediante un helper de pruebas; no se exceptúan del control productivo.
- Probe general de auditoría conserva F01–F04 e identidades tras rollback; no se interpretan sus resultados como corrección de otros hallazgos. F06 sigue mostrando salida sin CSRF admitida con una sesión vigente.
- PHPStan, contenedor, 61 Twig y 23 YAML correctos. Sin nuevas dependencias ni migraciones. La auditoría original conserva su puntuación.

Las pruebas HTTP usan MockArraySessionStorage y reloj controlado; no esperan horas reales ni prueban replay contra el almacenamiento de Hostinger. La invalidación/rotación se observa en ese entorno y utiliza la API estándar de sesión. La caducidad se verifica al entrar en una petición; no aborta operaciones ya autorizadas, no retira respuestas entregadas y no cambia el TTL del hub. No hay heartbeat de teclado ni aviso preventivo; escribir sin enviar no renueva la sesión del servidor. Producción y revisión visual de navegador pendientes. Los workflows siguen pendientes de incorporación como checks obligatorios (F13).

## Siguiente hallazgo propuesto

**F06: proteger el cierre de sesión con CSRF.** Pendiente en otra rama y ficha. El resto de hallazgos de la línea base sigue pendiente de acuerdo y verificación; la tarea 43 no está cerrada.
