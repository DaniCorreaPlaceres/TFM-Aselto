# CSRF del cierre de sesión · tarea 43 / F06

## Objetivo

Exigir el token CSRF de logout que ya genera el formulario de la plataforma, usando la protección nativa de Symfony. Un intento no válido no debe cerrar una sesión vigente ni limpiar su suscripción de Mercure. Rama desde develop tras integrar F05.

## Fuera de alcance

Cambiar permisos, TTL/revocación JWT, política de contraseñas, caducidad de sesión, páginas generales de error y otros hallazgos. No recalcular la auditoría histórica ni añadir dependencias/migraciones.

## Criterios de aceptación

- POST /salir requiere `_csrf_token` con identificador `logout`, coincidiendo con el formulario existente.
- Restringir también a POST la ruta `_logout_main` generada por el importador de seguridad, que inicialmente admitía cualquier método pese a la declaración del controlador.
- Con sesión vigente, token ausente, incorrecto o de otra sesión devuelve 403 y conserva acceso, identificador, CSRF y cookie de suscripción; GET devuelve 405 sin cerrar sesión, incluso llevando token en la URL. El formulario sigue enviándolo en el cuerpo, sin cambiar el comportamiento nativo del lector de parámetros de Symfony.
- El token válido del formulario permite cerrar: redirección al acceso, invalidación de sesión/CSRF y limpieza de cookie Mercure. El token anterior no sirve tras volver a entrar.
- JWT mantiene su independencia. La caducidad F05 puede invalidar una sesión vencida antes del control de logout, sin depender de un token CSRF.
- HTTP sintético de cierre real y regresiones de tiempo real, autenticación y caducidad, con fixtures revertidos y sin SMTP/IA externos.
- Documentar resultados ejecutados y límites de comprobación; conservar la línea base y dejar los demás hallazgos pendientes.

## Comprobado

- Rama desde develop `a866b17`, tras PR #52. Comprobación CSRF nativa habilitada con los nombres ya usados por el formulario; sin nuevo código de dominio, dependencias ni migraciones.
- Se reprodujo el cierre por GET con token válido debido a `_logout_main` (ANY). El importador se restringe a POST; las dos rutas efectivas muestran POST y GET/HEAD/OPTIONS/PUT/PATCH/DELETE devuelven 405.
- Workflow HTTP nuevo: **45 comprobaciones**, correcto. Tokens ausentes/inválidos/malformados/de otra sesión/otro propósito rechazan con 403 sin cerrar sesión ni limpiar suscripción; el token original sigue autorizando la salida. Invalida sesión/CSRF/identificador, token previo no sirve tras reentrada, JWT independiente y F05 conserva prioridad para sesiones vencidas.
- PHPUnit **404 pruebas / 1.042 aserciones**, correcto; regresiones autenticación **74**, tiempo real **38** y caducidad **78**, correctas. Datos sintéticos revertidos; sin SMTP/IA externos ni impresión de tokens.
- [Probe posterior](../security/evidence/f06-after-logout.json) devuelve 403 sin token y `logout_missing_csrf_rejected: true`; conserva F01–F04 e identidades. [Evidencia detallada](../security/evidence/f06-logout-csrf.json) y [guía](../security/logout-csrf.md).
- PHPStan, contenedor, 23 YAML y formato del script correctos. No se comprueban navegador real, ataque entre sitios reales ni Hostinger; se usa kernel real con almacenamiento de sesión simulado. La auditoría histórica permanece intacta y la tarea 43 sigue abierta.
- `git diff --check` de la tarea correcto. El cambio local previo de config/reference.php queda fuera de la entrega.
