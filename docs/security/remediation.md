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

## Siguiente hallazgo propuesto

**F03: limitar intentos de acceso y recuperación.** Pendiente en otra rama y ficha. El resto de hallazgos de la línea base sigue pendiente de acuerdo y verificación; la tarea 43 no está cerrada.
