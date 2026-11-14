# Invitaciones de la plataforma

## Objetivo

El superadmin invita por correo a una asesoría (destinatario admin) o a un cliente independiente autónomo/empresa. Solo indica tipo y correo; el destinatario completa nombre, datos fiscales y contraseña al aceptar.

## Fuera de alcance

Cambiar roles o vinculación de cuentas existentes, administración ampliada de datos, licencias, invitaciones de equipos y vinculación cliente-asesoría.

## Criterios de aceptación

- Acceso exclusivo del superadmin a envío, listado de estados y reenvío; sin acceso adicional a actividad privada.
- Correo con enlace de uso único y caducidad de 24 horas, token almacenado únicamente como hash y no mostrado en listados.
- Reenvío de invitaciones no aceptadas, incluso caducadas, invalidando el enlace anterior; se impiden altas con correos ya registrados, sin cambiar cuentas existentes.
- Formulario del destinatario con correo y tipo fijados por la invitación, datos fiscales completos y contraseña con las reglas existentes.
- Alta y consumo atómicos, protegidos ante aceptación o reenvío concurrentes y duplicados de correo.
- Pruebas de permisos, expiración, reenvío, uso único, validación y conservación de cuentas previas. Migraciones y esquema coherentes.

## Comprobado

- Rama `feature/platform-invitations`, desde `develop` tras el PR #34. Decisiones acordadas incorporadas a la memoria; guía de uso en `docs/invitations.md`, README e índice actualizados y tarea 30 marcada como hecha.
- Nueva tabla `platform_invitation`, metadatos e índices únicos de correo/token. Migración aditiva: no modifica cuentas ni tablas previas; su reversión impide descartar invitaciones existentes. Los enlaces antiguos de activación y equipo se conservan en sus flujos.
- El superadmin dispone de envío, listado de estados y reenvío. El destinatario completa su registro con correo/tipo fijados por el enlace. Creación de usuario activado, asesoría o perfil independiente y consumo del enlace en una transacción con bloqueo pesimista. SMTP después de guardar: fallo visible, reenvío disponible y sin reactivar enlaces anteriores.
- Fechas guardadas y restauradas en UTC, con presentación en horario de Canarias. Prueba de hidratación con PHP en Europe/Madrid para conservar las 24 horas. Tokens SHA-256, sin token/hash en listado; CSRF en operaciones y respuestas públicas de aceptación sin caché ni referente.
- PHPUnit: 308 pruebas y 743 aserciones correctas. Incluye uso único, caducidad, normalización, reenvío y cambios entre inspección y bloqueo (reenviado, aceptado o caducado).
- Integración HTTP en MySQL 8.4 temporal separado: 100 comprobaciones correctas. Tres destinos, permisos de invitado/admin/gestor/cliente y caso de uso, CSRF, unicidad, hash, estados, caducidad, reenvío, bloqueo real con una segunda conexión, validación fiscal/contraseña, rollback de perfil inválido, parámetros inyectados, cuentas existentes, recuperación tras fallo SMTP y cuenta creada por otra vía. Registros ficticios propios retirados al terminar.
- Las 15 migraciones desde cero y las 13 comprobaciones de regresión del esquema pasan en la base temporal. Migración nueva aplicada también al entorno habitual: solo creación de la tabla; validación del esquema correcta.
- Adaptador real de correo verificado contra Mailpit con destinatario ficticio: recepción, URL local, tipo de cuenta y plazo de 24 horas correctos. Mensaje de prueba retirado. El correo de aceptación no se ha probado con un proveedor externo.
- PHPStan nivel 6, formato Symfony sin caché de los 17 archivos PHP nuevos, diez plantillas Twig, YAML y contenedor Symfony correctos. No se ha realizado una revisión visual en navegador ni envío de correo externo en esta tarea. La ejecución remota de CI queda pendiente del PR.
- `config/reference.php` conserva sus cambios previos y queda fuera de la tarea.
- Base temporal comprobada sin usuarios ni invitaciones tras la limpieza; contenedor y volumen retirados. Enlaces locales y `git diff --check` correctos; los siete servicios habituales siguen activos.
