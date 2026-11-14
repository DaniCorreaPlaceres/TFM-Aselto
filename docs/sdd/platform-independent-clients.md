# Administración de clientes independientes

## Objetivo

Permitir al superadmin listar, dar de alta, consultar y editar nombre y contacto fiscal de clientes sin asesoría. Mantener la cuenta, el tipo de cliente y su actividad privada fuera de la edición administrativa.

## Fuera de alcance

Cambio de correo, contraseña, rol, tipo de cliente, vinculación a asesorías, licencias y acceso a documentos, movimientos, trimestres, conversaciones o archivos privados.

## Criterios de aceptación

- El superadmin activo solo lista y abre clientes con perfil y cuenta de tipo cliente sin asesoría; los casos de uso verifican estos permisos.
- Ficha administrativa con nombre, tipo y correo consultables y edición de nombre, NIF, teléfono y dirección completa, con validación y CSRF.
- Mantener alta directa e invitaciones para autónomos y empresas independientes.
- Versionado y bloqueo para impedir sobrescrituras de formularios antiguos o edición después de una vinculación a una asesoría. Proteger también los formularios fiscales existentes.
- Comprobar aislamiento de datos de otras asesorías y denegación de actividad privada por web, API y descargas, conservando los registros existentes.
- Migración aditiva, esquema coherente y registro únicamente de pruebas ejecutadas.

## Comprobado

- Rama `feature/platform-independent-clients`, desde `develop` tras el PR #36. Ficha administrativa propia con nombre y contacto fiscal editables; correo, cuenta, tipo y vinculación sin cambios. Listado limitado por actor activo y comprobación de coherencia entre cuenta y perfil.
- Alta directa e invitaciones de ambos tipos conservadas. En el alta directa se valida el nombre antes de crear la cuenta para devolver el formulario sin dejar registros parciales. Las respuestas de validación usan 422.
- Perfil con versión optimista. Guardado administrativo bajo bloqueo del perfil y la cuenta, con nueva comprobación del ámbito antes de guardar; formularios fiscales existentes también verifican versión y permisos dentro de la transacción. La ruta fiscal antigua redirige al superadmin a la nueva ficha y no permite editar por POST desde el flujo anterior.
- Migración aditiva `Version20261114030000`, sin modificar datos previos. Las 17 migraciones desde cero y las 13 comprobaciones de regresión del esquema pasan en MySQL 8.4 temporal. Columna aplicada también al entorno local, con validación del esquema correcta.
- PHPUnit: 331 pruebas y 803 aserciones correctas. Incluye roles ajenos, superadmin inactivo, perfiles vinculados o inconsistentes, propietario con rol incorrecto, versión antigua y vinculación entre lectura y adquisición del bloqueo.
- Integración HTTP en base temporal: 55 comprobaciones correctas. Listado de autónomos/empresas, cuentas pendientes, exclusión de vinculados e inconsistentes, permisos, CSRF, edición y validación, parámetros inyectados, versiones antiguas del superadmin y del propio cliente, alta directa y vinculación posterior a cargar el formulario.
- Se comprobó conservación exacta de la cuenta, registro documental, bytes de archivo privado, instantánea de trimestre e historial de conversación. Denegadas consultas y descargas privadas por web y API con identificadores existentes de los registros ficticios.
- Regresiones en la base temporal: identidad 54, documentos 74, trimestres 60, comunicación 79 e invitaciones 100 comprobaciones correctas. Cada flujo retiró sus registros y archivos propios; no se usaron datos reales ni extracción externa.
- PHPStan nivel 6, formato Symfony de los 20 archivos PHP afectados, contenedor Symfony, quince plantillas Twig y configuración de seguridad correctos. Guía `docs/platform-independent-clients.md`, README, índice y tarea 32 actualizados.
- No se ha realizado revisión visual en navegador, entrega de correo externo ni CI remoto en esta tarea. El alta directa se comprueba con un notificador de prueba. `config/reference.php` conserva cambios previos y queda fuera de la tarea.
- Diagnóstico final sin cambios de esquema, enlaces locales y `git diff --check` correctos. Base temporal sin usuarios, clientes, documentos, trimestres ni conversaciones al terminar; contenedor y volumen retirados. Los siete servicios habituales siguen activos.
