# Registro privado y alertas de seguridad · F11

## Alcance

El canal Monolog `security_audit` guarda eventos en `var/security`, fuera de `public/`. No tiene visor web ni permisos especiales para el superadmin. Lo consulta operaciones del servidor. No sustituye los historiales fiscales, de revisión o de conversaciones.

Registra autenticación web/API correcta y fallida, rechazos HTTP 401/403, respuestas 429, cierre de sesión y caducidad. Las solicitudes de recuperación tienen el mismo evento exista o no la cuenta, sin identificador de destino. Los casos de uso compartidos registran cambios de contraseña/activación, edición administrativa de asesorías y clientes independientes, solicitud/confirmación/cancelación del cambio de correo del admin, invitaciones y asignación de licencia. Un cambio se registra después de persistirlo; una invitación creada o una solicitud de correo no acredita su entrega. Los errores de formulario no se convierten en cambios exitosos por responder HTTP 200.

El esquema cerrado contiene UUID del evento, fecha UTC, evento/resultado, correlación de servidor y, cuando se conocen, UUID de actor/destino, nombre estático de ruta y método. La correlación se devuelve como `X-Request-ID`; se ignora el valor entrante. Correo e IP se seudonimizan con HMAC-SHA256 y propósitos distintos usando el secreto de aplicación. Son datos seudonimizados, no anónimos. Cambiar el secreto impide correlacionar esos hashes entre periodos. No se guardan direcciones en claro, contraseñas, tokens, identificadores de sesión, cuerpos, rutas con parámetros, documentos ni excepciones.

Un actor desconocido permanece vacío: no se deriva del correo suministrado ni de un JWT rechazado. Se recoge de los eventos nativos de acceso/salida, del control de recursos cuando ya conoce la identidad y de los casos de uso administrativos. El registro no consulta por su cuenta el token de seguridad para activar un firewall diferido. Un rechazo anterior a esos puntos puede quedar sin actor. La caducidad no reconstruye una identidad a partir de una sesión eliminada. Los rechazos de negocio que la aplicación expresa como 404 conservan ese comportamiento; este canal no clasifica todos los 404 como ataques.

## Almacenamiento y mantenimiento

En Linux: directorio `0700`, archivos diarios JSONL `0600`, propietario del proceso PHP. Docker utiliza el volumen `app_security`; se requiere reconstruir la imagen para actualizar el script de arranque:

```powershell
docker compose up -d --build app
docker compose exec -T --user www-data app php bin/console app:security events
docker compose exec -T --user www-data app php bin/console app:security alerts
docker compose exec -T --user www-data app php bin/console app:security retention
```

`events` muestra los últimos 50 eventos retenidos; `alerts` muestra cantidades agregadas sin enviar nada. Estos comandos son para una terminal de operaciones con acceso autorizado, no una pantalla para clientes.

Retención acordada: día UTC actual y 29 anteriores. `retention` retira únicamente archivos propios `security-AAAA-MM-DD.jsonl` anteriores al corte; conserva otros archivos, incluido el cursor de correo. Hay que programarlo diariamente. La caducidad física depende de ejecutar ese mantenimiento y no purga copias de seguridad ni otros logs. El lector excluye días fuera de retención y futuros. Rechaza enlaces simbólicos y entradas malformadas; el cursor identifica día y posición del archivo, no la hora del evento.

La escritura usa bloqueo exclusivo, límite de 2.048 bytes por entrada y 16 MiB por archivo diario. El conjunto de 30 días ocupa como máximo aproximadamente 480 MiB, aparte de metadatos/estado. El lector fija el tamaño al abrir cada archivo y no mantiene el bloqueo al entregar entradas. No hay cadena criptográfica ni garantía de inmutabilidad: quien administre el servidor puede modificar los archivos.

Si falla el canal, la operación de negocio continúa y se emite solo `Aselto: private security audit unavailable.` al diagnóstico del servidor. Los comandos devuelven error ante falta de permisos/capacidad o lectura inválida; no ocultan el fallo como un resultado vacío. Operaciones debe vigilar también la salida y el estado del cron: un almacenamiento averiado no puede garantizar su propia alerta por correo.

El canal queda excluido de los handlers generales de Monolog. Esta tarea no sanea todas las trazas existentes de Symfony, profiler, nginx, SMTP o proveedor; su configuración productiva se revisará por separado. Los registros privados y su estado deben persistir fuera del directorio de cada nueva versión al desplegar.

## Alertas agrupadas

Umbrales iniciales por ventanas UTC fijas de 15 minutos, globales para la plataforma: 10 fallos/rechazos de autenticación, 3 bloqueos de autenticación, 5 accesos denegados o 20 bloqueos de recursos. Se agregan las cantidades de las ventanas que superan su umbral. No equivalen a detectar todo abuso ni a un IDS completo. La primera ejecución puede resumir los eventos de los 30 días retenidos; después solo los posteriores al último resumen entregado.

El correo incluye categorías y cantidades, sin identificadores, hashes ni documentos. Destinatario acordado: `webmaster@aselto.com`. Máximo un intento cada 3.600 segundos, también si falla SMTP. El intento se persiste antes de enviar; el cursor solo avanza tras éxito. Si no hay umbrales superados, no hay envío ni avance del cursor, para conservar eventos que aún puedan sumar dentro de su ventana. Tras un fallo, se reintenta cuando se cumpla la hora. Un bloqueo sin espera coordina procesos. Un estado inválido impide enviar.

El transporte se invoca directamente y no se delega en Messenger. Una aceptación SMTP seguida de caída antes de guardar el cursor puede producir un duplicado en un intento posterior; no se promete entrega exactamente una vez. La exclusión y el estado deben ser compartidos por todos los procesos que programen envíos. El backend local `flock` no coordina servidores distintos. No ejecutar cron simultáneo en varios nodos sin un backend y almacenamiento compartidos compatibles; con bloqueos remotos sin caducidad, revisar también su recuperación operativa.

En local están **desactivadas**. Configurar al desplegar, sin versionar credenciales SMTP:

```dotenv
SECURITY_ALERT_EMAIL_ENABLED=1
SECURITY_ALERT_RECIPIENT=webmaster@aselto.com
SECURITY_ALERT_FROM=no-responder@aselto.com
```

El remitente es una propuesta de configuración: verificar dominio, buzón autorizado, SPF/DKIM y `MAILER_DSN` antes de activarlo. No se ha comprobado que ese remitente ni el destinatario reciban correo real. La dirección del destinatario procede de la decisión del usuario.

Ejemplo de cron, adaptando ruta y usuario PHP del hosting; **no instalado ni comprobado en Hostinger**:

```cron
10 2 * * * cd /ruta/aselto && APP_ENV=prod APP_DEBUG=0 php bin/console app:security retention --no-debug
*/15 * * * * cd /ruta/aselto && APP_ENV=prod APP_DEBUG=0 php bin/console app:security alerts --send --no-debug
```

El comando puede ejecutarse cada 15 minutos: el estado impide más de un intento por hora. Vigilar fallos del cron sin enviar su salida a destinatarios no autorizados. Ejecutarlo con el mismo propietario que PHP; no crear archivos de root que PHP no pueda mantener. Los estados `disabled`, `empty`, `waiting` y `busy` no envían correo. Para producción, permisos, persistencia, cron, transporte y capacidad real siguen pendientes de verificación.

## Pruebas

Pruebas unitarias: esquema cerrado, HMAC, secretos/CRLF, permisos Linux, retención, archivos ajenos, enlaces simbólicos, capacidad, corrupción, cursor, lectura durante escritura y umbrales. Transporte simulado para frecuencia, exclusión, error y reintento. Workflow HTTP con cuentas sintéticas y rollback, notifiers de cuenta/correo simulados y directorio temporal privado; no envía correo real.

```powershell
docker compose exec -T --user www-data app php bin/phpunit tests/Shared/SecurityAuditTest.php
docker compose exec -T --user www-data app php tests/Integration/security-event-logging-workflow.php
```

La evidencia de ejecución y sus límites se recoge en la [ficha](../sdd/security-event-logging.md). Se conserva la puntuación de la auditoría original.

Referencia de diseño: [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html). Los 30 días y la frecuencia horaria son decisiones del proyecto, no plazos legales atribuidos a OWASP.
