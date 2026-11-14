# Administración de asesorías por la plataforma

## Objetivo

Permitir al superadmin consultar y editar los datos administrativos de una asesoría y gestionar de forma protegida el correo de su admin, sin acceso a su actividad privada.

## Fuera de alcance

Administración de clientes independientes, cambios de rol o asesoría, documentos, cifras, trimestres, conversaciones y archivos privados; licencias y eliminación de cuentas.

## Criterios de aceptación

- Listado con acceso a una ficha por asesoría y alta existente disponible.
- Consulta y edición de nombre, NIF, teléfono y dirección completa por un superadmin activo, con validación y protección CSRF.
- Mostrar el admin de la asesoría; gestionar correos duplicados y cambios concurrentes sin cambiar su identidad, rol o pertenencia.
- Correo actual vigente hasta confirmar el nuevo con enlace de 24 horas. Confirmación de uso único, reenvío que sustituye el enlace anterior, invalidación de sesiones/JWT y enlaces anteriores y aviso al correo antiguo, conservando identidad y permisos.
- Comprobar expresamente que la administración no concede acceso a documentos, movimientos, trimestres, conversaciones ni descargas, en web y API.
- Pruebas de permisos, validación, conservación de datos y cambios concurrentes; anotar únicamente resultados ejecutados.

## Comprobado

- Ficha accesible desde el listado, edición de nombre y contacto fiscal, validación de campos y CSRF. Listado y casos de uso comprueban superadmin activo. Si no hay un único admin, no se permite cambiar el correo.
- Confirmación al nuevo correo en 24 horas, uso único, reenvío y cancelación del pendiente; correo actual vigente hasta confirmar. Conserva usuario, contraseña, rol, activación y asesoría. Las direcciones ya asignadas se rechazan antes de solicitar y al confirmar.
- Aviso al antiguo después de confirmar. Si falla el transporte, queda pendiente y puede reenviarse; se bloquea una nueva solicitud hasta enviar el aviso. El fallo no revierte una confirmación ya realizada. Ambos adaptadores de correo comprobados contra Mailpit y mensajes ficticios retirados.
- Migración `Version20261114020000`: revisión de autenticación del usuario, versión de asesoría y tabla de cambios de correo. Se bloquea su reversión automática para no restaurar accesos revocados. Las 16 migraciones aplicadas desde cero en MySQL 8.4 temporal y las 13 comprobaciones de regresión de esquema pasan. Migración nueva aplicada en el entorno habitual y diagnóstico sin SQL adicional.
- Sesiones comparan identificador, revisión y estado de la cuenta. JWT incluyen `uid` y `auth_version`, comprobados contra la cuenta actual; los JWT previos a esta actualización requieren iniciar sesión de nuevo. Se rechazan accesos antiguos incluso si el correo anterior vuelve a asignarse o la cuenta vuelve a usarlo. Los enlaces abiertos de activación y recuperación se invalidan al confirmar.
- Concurrencia: bloqueo de ficha y admin, versión optimista y huella de formulario; el formulario fiscal propio también rechaza versiones antiguas. Recuperación y elección de contraseña bloquean el usuario y vuelven a comprobar enlace e identidad, para no restaurar el correo antiguo ni generar enlaces para él después del cambio.
- PHPUnit: 322 pruebas y 792 aserciones correctas. Incluye permisos del caso de uso, admin de plataforma inactivo, conservación de identidad, sesión serializada anterior a esta actualización, JWT de revisión anterior o de correo reasignado y recuperación concurrente.
- Integración administrativa HTTP en MySQL temporal: 70 comprobaciones correctas. Consulta/edición, CSRF, roles ajenos, inexistentes, fiscalidad, formularios antiguos, duplicados antes y después de solicitar, expiración, reenvío, confirmación, accesos previos, enlaces anteriores, fallos SMTP, reintento del aviso, cancelación y bloqueo de actividad privada web/API.
- Regresiones API en la base temporal: autenticación 54, documentos 74, trimestres 60 y comunicación 79 comprobaciones correctas. Cada flujo retiró sus datos y archivos ficticios; no se utilizaron datos fiscales reales.
- PHPStan nivel 6, contenedor y diez plantillas Twig correctos. Documentación de uso en `docs/platform-firms.md`, memoria, README e índice actualizados y tarea 31 marcada como hecha.
- No se ha realizado revisión visual en navegador, entrega por un proveedor externo ni ejecución remota de CI en esta tarea. `config/reference.php` conserva cambios previos y queda fuera del cambio.
- Formato Symfony sin caché de los 39 archivos PHP afectados, tres YAML, contenedor, diez plantillas Twig, enlaces locales y `git diff --check` correctos. Base temporal comprobada sin usuarios, asesorías ni solicitudes de correo tras las pruebas; contenedor y volumen retirados. Los siete servicios habituales siguen activos.
