# Revocación de accesos al restablecer contraseña · tarea 43 / F01

## Objetivo

Garantizar que un restablecimiento de contraseña invalide los JWT y sesiones anteriores mediante la versión de autenticación persistida del usuario. Primera corrección de la tarea 43, partiendo de develop tras integrar la auditoría.

## Fuera de alcance

Otros hallazgos OWASP, cambios de roles, política de contraseñas, tiempos de sesión, límites de intentos, cambio de formato/TTL JWT y modificaciones de protecciones GitHub. Conservar la auditoría original como línea base, sin recalcular su puntuación histórica.

## Criterios de aceptación

- Cambiar la contraseña incrementa authVersion conservando identidad, correo, rol y asesoría. La escritura usa la transacción/bloqueo de usuario existente y no introduce migraciones.
- Tras un reset real por el caso de uso compartido web/API, un JWT emitido antes devuelve 401 de forma repetible; la contraseña antigua falla y la nueva permite obtener un token válido.
- Las sesiones web antiguas no acceden a una página privada después del cambio, incluso si se restablece el mismo texto de contraseña. Los JWT emitidos antes del cambio carecen de la nueva versión.
- Caducidad y uso único del enlace siguen funcionando; una solicitud fallida no cambia la versión ni revoca accesos válidos.
- Pruebas de dominio/caso de uso y regresión de autenticación con datos sintéticos, sin correo ni IA externos. Documentar solo resultados ejecutados y dejar el resto de la tarea 43 pendiente.

## Comprobado

- Rama creada desde develop `6acdd61` tras integrar PR #47. Corrección limitada a incrementar la versión al cambiar contraseña; sin migraciones ni cambios de TTL/rutas/permisos.
- Pruebas nuevas reproducen el fallo antes de corregir: contador conservado. Después, PHPUnit completo **347 pruebas / 869 aserciones**, correcto.
- Autenticación **74 comprobaciones**, dos ejecuciones correctas. Revocación JWT con `401 token_invalid` repetido, contraseña anterior rechazada, nueva válida, sesiones anteriores rechazadas, nueva identidad válida, reset a la misma contraseña y enlaces inválidos sin revocación accidental.
- Regresión administrativa de asesorías **70 comprobaciones**, correcta. PHPStan correcto; formato de los cuatro PHP modificados y diferencias comprobados.
- [Evidencia específica F01](../security/evidence/f01-after-reset.json): JWT antes 200, después 401; contraseña anterior 401, nueva 200, uso único/caducidad conservados, identidades conservadas tras rollback. Sin correo ni IA externos.
- [Seguimiento](../security/remediation.md): F01 verificado en local; despliegue no comprobado. La línea base de auditoría permanece intacta y la tarea 43 sigue abierta para las demás correcciones.
