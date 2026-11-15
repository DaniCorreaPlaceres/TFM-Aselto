# Documentación de Aselto

Toda la documentación del proyecto vive en esta carpeta, en la raíz del repositorio. El código no guarda especificaciones sueltas en otros sitios. Las reglas de trabajo están en [AGENTS.md](../AGENTS.md), en la raíz, para que se apliquen en cada tarea.

## Mapa

- [memoria.md](memoria.md). Punto de partida: producto, stack, herramientas, arquitectura y especificaciones.
- [api/README.md](api/README.md). Acceso y uso de la API v1.
- [demo.md](demo.md). Carga local de demostración, accesos ficticios y recorridos para el TFM.
- [invitations.md](invitations.md). Invitaciones del superadmin, estados, reenvío y registro del destinatario.
- [platform-firms.md](platform-firms.md). Administración de asesorías y cambio confirmado del correo de su admin.
- [platform-independent-clients.md](platform-independent-clients.md). Fichas administrativas de clientes sin asesoría y límites de acceso.
- [licenses.md](licenses.md). Planes, activación manual, cuotas, migración y pruebas reproducibles.
- [tax-exempt-records.md](tax-exempt-records.md). Exención de impuesto indirecto, retención, API, históricos y comprobación manual.
- [security/README.md](security/README.md). Auditoría OWASP: controles, puntuación, evidencias reproducibles y mejoras propuestas.
- [security/password-list.md](security/password-list.md). Política de selección de contraseñas, procedencia y licencia de la lista local y actualización verificable.
- [security/session-expiration.md](security/session-expiration.md). Inactividad, duración máxima de sesión, actualizaciones automáticas y reentrada.
- [security/logout-csrf.md](security/logout-csrf.md). Cierre de sesión por POST, validación CSRF y conservación de sesión ante intentos inválidos.
- [security/browser-security-headers.md](security/browser-security-headers.md). CSP, nonce, protección de marcos, referencias y permisos; alcance de nginx y compatibilidad de interfaz.
- [security/resource-limits.md](security/resource-limits.md). Presupuestos temporales, listados en servidor, exportaciones completas y límites operativos.
- [security/ai-resource-limits.md](security/ai-resource-limits.md). Límites de tamaño, tiempo, tokens y concurrencia de IA, cancelación y revisión manual.
- [security/security-event-logging.md](security/security-event-logging.md). Canal privado, retención de 30 días y resúmenes de seguridad por correo.
- [security/local-service-bindings.md](security/local-service-bindings.md). Puertos de desarrollo restringidos a IPv4 loopback, aplicación del cambio y verificaciones locales.
- [api/pagination.md](api/pagination.md). Paginación y respuestas 429 del contrato 0.6.0.
- [public-content-batch.md](public-content-batch.md). Entregas y evidencias del lote de comercialización y contenido público (PR #39–44).
- [local-installation.md](local-installation.md). Instalación desde cero con Docker, comprobaciones y problemas conocidos.
- [Tareas.md](Tareas.md). Lista de tareas del proyecto, sin el detalle de cada una.
- [producto/Aselto_Documento_introductorio.docx](producto/Aselto_Documento_introductorio.docx). Texto de presentación del proyecto.
- [diseno/reglas-visuales.md](diseno/reglas-visuales.md). Reglas de interfaz. Los bocetos están en [diseno/bocetos](diseno/bocetos).
- [diseno/platform-design.md](diseno/platform-design.md). Componentes de la interfaz, listados, visor y compilación Sass.
- [marca](marca). Logotipo oficial e icono. No se redibujan.
- [sdd](sdd/README.md). Una ficha por tarea, escrita antes de implementar.

## Cómo se añade un documento

El producto, el stack y la arquitectura se actualizan en la memoria. Una tarea concreta no se documenta ahí: se abre `sdd/<tarea>.md`. Las imágenes de marca y los bocetos nuevos entran en `marca` o en `diseno`.
