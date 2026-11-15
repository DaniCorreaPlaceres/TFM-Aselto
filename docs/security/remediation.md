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

## Siguiente hallazgo propuesto

**F02: origen canónico y validación de Host en enlaces sensibles.** Pendiente en otra rama y ficha; después, límites de intentos F03. El resto de hallazgos de la línea base sigue pendiente de acuerdo y verificación.
