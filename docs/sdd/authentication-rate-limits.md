# Límites de acceso y recuperación · tarea 43 / F03

## Objetivo

Limitar intentos HTTP de acceso web/API y solicitudes de recuperación antes de verificar contraseñas o generar correo. Usar el componente RateLimiter de Symfony, almacenamiento persistente local y bloqueos para peticiones concurrentes. Rama desde develop después de integrar F02.

## Fuera de alcance

CAPTCHA/MFA, protección de red frente a DDoS, restricciones comerciales, límites de otras operaciones administrativas, despliegue distribuido y otros hallazgos OWASP. Conservar la auditoría histórica.

## Cambios planteados

- Acceso web y API comparten presupuestos: 5 intentos por minuto por correo normalizado + IP y 60 por minuto por IP. Se cuentan también accesos correctos, evitando reiniciar el presupuesto con credenciales conocidas. No hay bloqueo global de una cuenta desde otras IP.
- Recuperación: 3 solicitudes por 15 minutos por correo normalizado y 10 por 15 minutos por IP, después del CSRF y antes de consultar la cuenta. Aplicar igual a correos existentes, inactivos e inexistentes.
- Respuesta HTTP 429 con Retry-After y mensaje genérico en español; API conserva estructura JSON de errores. La espera es temporal, sin bloquear permanentemente una cuenta ni tocar credenciales/tokens existentes al rechazar una petición.
- Claves de contadores mediante HMAC, sin guardar correos ni IP en claro. Caché local compartida por procesos y bloqueos de fichero; documentar persistencia, limpieza y límites en múltiples servidores.

## Criterios de aceptación

- Alternar acceso web/API no evade límites; espacios y mayúsculas del correo comparten contador. Cambiar de correo no evita el límite IP.
- Recuperación limitada no emite correos ni cambia tokens. Cuenta existente/inexistente/inactiva tiene igual respuesta por nivel de límite y estado de CSRF.
- Umbral, Retry-After, aislamiento por IP/identidad y recuperación de ventana verificados con estados temporales de prueba, sin esperas reales ni ataques de carga.
- Recorrido HTTP sintético con correo capturado, sin SMTP/IA reales; almacenamiento de pruebas aislado para no consumir límites del entorno del usuario.
- PHPUnit, análisis estático, configuración, contrato OpenAPI y regresiones relevantes comprobados; anotación de resultados reales y F03 acotado a estas entradas HTTP.

## Comprobado

- Rama desde develop `2b1572a`, tras PR #49. Componentes Symfony RateLimiter y Lock instalados desde lock; almacenamiento/bloqueo en servidor local y presupuestos declarados en rate_limiter.yaml.
- PHPUnit: **370 pruebas / 982 aserciones**, correctas. Umbrales, aislamiento, normalización y expiración con ventanas de prueba envejecidas, sin espera real.
- Workflow HTTP: **45 comprobaciones**, correcto. Alternancia web/API y percent-encoding no evaden cuota; 429 JSON/web con Retry-After; recuperación uniforme activa/inactiva/inexistente, sin notificación ni cambio de enlace al bloquear; CSRF inválido no consume presupuesto de recuperación. Fixtures revertidos e identidades conservadas.
- Concurrencia: seis procesos comparten almacenamiento de prueba en disco con flock; 30 intentos, cinco aceptados y 25 limitados. El padre observa la persistencia. Solo se limpia el namespace aleatorio de prueba.
- Regresión autenticación **74**, enlaces canónicos **17**, correctas. Probe de auditoría conserva F01/F02 y datos existentes; sus contadores se aíslan en memoria, igual que la regresión.
- PHPStan, contenedor, 23 YAML, OpenAPI y Composer validate correctos; Composer audit sin avisos ni abandonos. OpenAPI pasa a 0.5.1 por 429; producto sin cambio de versión.
- PHP-CS-Fixer de los nueve PHP modificados/nuevos y `git diff --check` de los archivos de la tarea, correctos. `config/reference.php` conserva su cambio local previo y queda fuera de la entrega.
- [Evidencias](../security/evidence/f03-rate-limits.json), documentación de entorno/API y [seguimiento](../security/remediation.md) actualizados. Auditoría histórica intacta; producción, defensa de red, otras altas/correos administrativos y persistencia distribuida fuera de alcance. Tarea 43 sigue pendiente para otros hallazgos.
