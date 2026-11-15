# Trazabilidad de seguridad · tarea 43 / F11

## Objetivo

Registrar eventos de autenticación, recuperación y administración con resultado y correlación, sin guardar credenciales, enlaces privados ni contenido fiscal. Conservarlos 30 días y proporcionar consulta local y resúmenes por correo. Rama `feature/security-event-logging` desde develop `0510c74`, tras integrar F10 (PR #56).

## Decisiones del usuario

- Retención de 30 días, fuera del directorio público y acceso solo de operaciones del servidor.
- Alertas a `webmaster@aselto.com`, como máximo un resumen por hora cuando existan nuevas alertas.
- No enviar correos reales durante las pruebas; el envío local queda desactivado por defecto y requiere configuración explícita al desplegar.

## Cambios planteados

- Puerto de auditoría independiente de HTTP/Doctrine/Monolog para los cambios de cuenta y administración; adaptador con canal Monolog privado y esquema cerrado de eventos.
- Correlación generada por servidor, actor/objeto mediante UUID cuando se conocen, evento, resultado y fecha UTC. Correo/IP solo mediante HMAC con separación de propósito. Sin URL completa, tokens, sesiones, cuerpos, nombres, documentos ni excepciones del proveedor.
- Registrar éxitos/fallos de login web/API, acceso rechazado, límites, logout y caducidad; recuperación sin revelar existencia de cuentas; cambio de contraseña, confirmación de correo y cambios administrativos tras su ejecución.
- Almacenamiento JSONL privado, escritura bloqueada y acotada, directorio 0700 y archivos 0600 en Linux. Volumen Docker separado para preservar permisos en Windows. No exponer visor web ni conceder acceso al superadmin de producto.
- Retención por días naturales UTC (día actual y 29 anteriores), con comando de mantenimiento y verificación de permisos. No borrar documentos ni otros registros del proyecto. Detectar/lograr diagnosticar errores de almacenamiento sin filtrar datos ni convertir un fallo de logging en una operación de negocio aplicada a medias.
- Consulta local de avisos agregados. Umbrales iniciales documentados para fallos de autenticación, bloqueos y accesos denegados por ventanas de 15 minutos, sin afirmar un IDS completo.
- Resumen por correo sin identificadores ni documentos, máximo un intento por hora; bloqueo entre procesos, cursor persistente y conservación de pendientes si falla el transporte. Envío explícito por comando/cron, sin cola automática ni correo por cada intento. Mantener configuración y pruebas del transporte separadas del envío real.

## Fuera de alcance

Despliegue real en Hostinger, correos reales de prueba, SIEM externo, vigilancia personal, visor web de logs, auditoría completa de todas las trazas antiguas/profiler/nginx, pruebas de carga y recalcular la puntuación histórica. No alterar permisos fiscales ni aplicar otras tareas de la auditoría.

## Criterios de aceptación

- Web y API generan eventos correlacionados con esquema cerrado; los cambios exitosos se distinguen de intentos y rechazos. No se infiere éxito solo del estado HTTP de un formulario.
- Pruebas con secretos señuelo, CR/LF y enlaces sensibles no dejan esos valores en el canal privado ni en el resumen por correo.
- Verificar permisos, límites de escritura, consulta/retención y que no se borran archivos ajenos. Ninguna ruta pública entrega el registro.
- Las alertas agrupan solo datos permitidos, respetan la frecuencia y el bloqueo entre procesos; un error de envío conserva pendientes. Sin envío cuando están desactivadas o no hay nuevas alertas.
- Regresiones relevantes de identidad, administración y límites; estilo PHP 8.4, análisis estático, configuración y documentación con pruebas realmente ejecutadas.

## Comprobado

Implementado en la rama indicada y comprobado en Docker local el 15/11/2026. [Guía operativa](../security/security-event-logging.md) y [evidencia](../security/evidence/f11-security-event-logging.json).

- PHPUnit completo: **449 pruebas / 1.286 aserciones**; 11 pruebas nuevas de auditoría incluidas. Permisos, límites, retención, archivos ajenos, enlace simbólico, corrupción, cursor, HMAC, secretos señuelo/CRLF, fallo de logging, umbrales, exclusión, frecuencia y reintento con transporte simulado.
- Workflow nuevo: **29 comprobaciones HTTP/aplicación**, con rollback y notifiers simulados. Login web/API, correlación, rechazo, recuperación uniforme, cambio de contraseña, edición administrativa, solicitud/confirmación de correo, límites y ausencia de los secretos de prueba.
- Regresiones: autenticación API **74**, límites de autenticación **45**, caducidad **78**, recursos **56**, asesorías **70**, clientes independientes **55**, invitaciones **118**. Logout **47**, en dos ejecuciones finales consecutivas, incluidos eventos nativos de salida y caducidad.
- PHPStan, contenedor y **24 YAML** correctos. Formato comprobado con PHP 8.4 sobre copia LF; no se cambia la configuración de estilo.
- Imagen reconstruida y arranque comprobado: volumen privado con propietario `www-data`, directorio `0700` y archivos `0600`. Consulta y retención por comando correctas; envío explícitamente desactivado devuelve `disabled`, sin transporte real.

Incidencias observadas y tratadas: la imagen antigua no inicializaba el nuevo volumen; se reconstruyó y repitió la autenticación tras corregir permisos. Logout presentaba fallos intermitentes: una comparación sin los nuevos observadores reprodujo caducidad HTTP 303. Se controla el reloj de ese workflow, conservando una caducidad explícita al final y sin cambiar la política productiva. Se retiró además la lectura anticipada del token en el observador nuevo para no activar la sesión por el logging. PHP 8.4 detectó diferencias de formato corregidas antes del cierre.

No comprobado: SMTP real, recepción del correo, remitente autorizado, cron/almacenamiento/capacidad de Hostinger, varios nodos y carga. No se envió correo real, no se modificaron datos fiscales ni migraciones y no se recalculó la puntuación histórica. `config/reference.php` tenía cambios generados previos y queda fuera de la tarea.
