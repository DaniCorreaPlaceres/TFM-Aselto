# Origen confiable de enlaces de cuenta · tarea 43 / F02

## Objetivo

Generar los enlaces de activación, recuperación, invitación y confirmación de correo desde DEFAULT_URI, sin depender del Host de la petición. Rechazar hosts no autorizados en HTTP y documentar el origen de despliegue. Rama desde develop después de integrar F01.

## Fuera de alcance

Otros hallazgos OWASP, configuración real de Hostinger, cambio de caducidad o formato de tokens, redirecciones comerciales y cambios de permisos. Conservar la línea base de auditoría.

## Criterios de aceptación

- Los cuatro correos sensibles usan esquema, host y puerto del origen configurado aunque el contexto HTTP tenga otro host/esquema/puerto.
- No se muta el contexto compartido del router. Los tokens siguen escapados por el generador de rutas y no se imprimen secretos.
- Origen sin credenciales, consulta ni fragmento; HTTPS obligatorio fuera del loopback local. Configuración inválida falla explícitamente.
- Peticiones con Host no autorizado reciben 400; localhost/127.0.0.1 y el dominio canónico autorizado siguen funcionando según entorno. No confiar en cabeceras de proxy arbitrarias.
- Pruebas de correos con Mailer en memoria, HTTP y regresión de autenticación/invitaciones/cambio de correo; sin SMTP ni IA reales.
- Actualizar configuración y documentación local/productiva, registrar únicamente comprobaciones ejecutadas y dejar el resto de tarea 43 pendiente.

## Comprobado

- Rama desde develop `8f1cefa`, tras integrar F01 (PR #48). Origen único DEFAULT_URI y lista explícita TRUSTED_HOSTS, sin nuevas dependencias ni migraciones.
- Los cuatro notifiers utilizan una copia del generador compilado con contexto independiente; no se cambia el contexto del router compartido. HTTPS fuera de loopback; configuración insegura rechazada.
- PHPUnit completo: **366 pruebas / 891 aserciones**, correcto. Workflow nuevo: **17 comprobaciones**, correcto; correos en memoria con rutas reales, puerto/prefijo hostiles, host ajeno 400 y cabeceras de proxy arbitrarias ignoradas.
- Regresión de autenticación **74**, invitaciones **100**, asesorías/cambio de correo **70**, correctas. Fixtures retirados/revertidos; sin correo ni IA externos.
- HTTP a través de nginx local: localhost y 127.0.0.1 conservan 200; host ajeno y subdominio engañoso reciben 400. [Evidencia](../security/evidence/f02-http-hosts.json).
- [Probe actualizado](../security/evidence/f02-after-links.json): host canónico en enlace pese al contexto alterado, JWT anterior tras reset 401 e identidades conservadas por rollback.
- PHPStan, contenedor, 22 YAML y formato de los siete PHP modificados/nuevos correctos. Documentación de entorno y [seguimiento](../security/remediation.md) actualizados; auditoría histórica intacta. Hostinger/dominio real no comprobados; tarea 43 y F03 pendientes.
