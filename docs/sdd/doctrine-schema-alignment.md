# Coherencia del esquema Doctrine

## Objetivo

Resolver las diferencias entre el esquema creado por las migraciones y los metadatos de Doctrine, conservando datos, índices útiles y la cola de actualizaciones.

## Fuera de alcance

Nuevas funcionalidades, cambios de permisos, modificación de datos del entorno habitual y regeneración destructiva del esquema.

## Criterios de aceptación

- Identificar las diferencias de tipos, índices y tablas gestionadas mediante SQL directo.
- Mantener intactas las migraciones ya publicadas y los datos existentes; cualquier migración nueva debe justificarse y probarse.
- Validar los mapeos y el esquema tras migrar una base aislada desde cero; el diagnóstico de actualización no debe proponer cambios.
- Comprobar que los registros y las notificaciones pendientes se conservan y que la cola continúa funcionando.
- Documentar las comprobaciones ejecutadas y actualizar la incidencia de la guía de instalación.

## Comprobado

## Solución

- Los metadatos no describían `CHAR` en identificadores y hashes, nombres de índices únicos, índices de búsqueda, valores por defecto de contacto y activación ni los comentarios de fechas de las primeras migraciones. Se completan esos metadatos conservando el esquema ya publicado.
- `QuarterUpdateSchema` participa en `postGenerateSchema` para describir la tabla SQL de la cola: clave compuesta, revisiones BIGINT y sus valores por defecto. No se filtra la tabla ni se cambia la escritura atómica mediante DBAL.
- No hay migraciones nuevas, modificaciones de las existentes ni cambios de datos necesarios para aplicar la corrección. No se ejecutó SQL de actualización de esquema.
- Nueva prueba `tests/Integration/schema-workflow.php` y quinto trabajo de CI con MySQL 8.4, migraciones desde cero y validación. La prueba usa el ciclo de consola oficial, que excluye la tabla interna de versiones, pero no las tablas de negocio. Sus registros ficticios se revierten.

## Resultados

- Base habitual: `doctrine:schema:validate` correcto y `doctrine:schema:update --dump-sql` sin cambios; solo consultas de diagnóstico.
- MySQL 8.4 temporal separado, sin puertos publicados: 14 migraciones desde cero y validación correctas. Regresión de esquema: 13 comprobaciones, incluida detección de una diferencia estructural simulada en la cola, preservación exacta de notificaciones pendientes, recarga de identidad, importes y fecha inmutable.
- Flujo de tiempo real contra la base temporal y hub local: 36 comprobaciones correctas, con limpieza de datos ficticios y conservación de datos existentes.
- PHPUnit: 298 pruebas y 712 aserciones correctas. PHPStan nivel 6 sin errores. Formato Symfony de los siete archivos PHP modificados/nuevos comprobado sin caché en LF.
- Actualizados README, guía de instalación y lista de tareas. El trabajo nuevo de GitHub Actions se ha reproducido localmente con MySQL; su ejecución remota queda pendiente del PR.
- `config/reference.php` conserva cambios previos ajenos a esta tarea.
- YAML del flujo y contenedor Symfony válidos; diagnóstico final de la base temporal sin cambios de esquema y `git diff --check` correcto. Retirados el MySQL temporal y su volumen; los siete servicios habituales siguen activos.
