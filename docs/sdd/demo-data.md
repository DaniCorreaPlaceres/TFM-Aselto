# Datos de demostración

## Objetivo

Crear dos asesorías ficticias con equipos, clientes y recorridos de documentos, trimestres y comunicación. Añadir un cliente independiente y acceso de superadmin para demostrar los permisos.

## Fuera de alcance

Datos reales, envío de correos, extracción por IA, reinicio de la base, cambios de producto y carga en producción.

## Criterios de aceptación

- Comando exclusivo de desarrollo con identificadores deterministas y correos example.test propios, sin modificar los accesos existentes.
- Primera carga transaccional; repetición no duplica ni restablece datos, contraseñas o archivos. Una colisión o carga parcial se rechaza sin sobrescribir.
- Dos asesorías, admin y gestor en cada una, clientes autónomos y sociedades, IVA/IGIC/IPSI y un cliente sin asesoría.
- Documentos PDF ficticios descargables, confirmados/validados, pendientes, rechazados, con petición de justificante y sin fecha; recargo e IRPF opcionales representados.
- Trimestre 2026 Q3 cerrado con instantánea coherente y Q4 abierto con pendientes. Conversaciones y solicitudes pendientes/resueltas con lectura individual y adjuntos privados.
- Archivos nuevos con creación exclusiva; fallo de base revierte datos y elimina solo archivos creados por esa ejecución.
- Guía de usuarios, contraseña local y recorridos reproducibles. Integración verifica carga, repetición, colisiones, permisos y conservación de datos ajenos.

## Comprobado

- Rama `feature/demo-data`, creada desde develop tras integrar la API de comunicación.
- Comando `app:seed-demo`, exclusivo de desarrollo. Usa identificadores UUID deterministas, correos example.test propios, bloqueo de carga por base de datos y transacción. No envía correos ni llama a IA.
- Datos creados: dos asesorías, diez cuentas activadas, cinco clientes, 33 documentos con originales PDF, cinco cierres de 2026 Q3 y doce conversaciones con cuatro adjuntos PDF. Q4 abierto con pendientes, rechazo, petición de justificante y documento sin fecha. IVA, IGIC, IPSI manual, recargo y retención representados.
- Integración en MySQL: 86 comprobaciones correctas, con identificadores y almacenamiento aislados. Verifica prohibición en producción, colisión de correo ajeno, carga parcial, archivo preexistente, rollback y retirada de archivos nuevos, primera carga, cifras, ámbitos, solicitudes, impuestos, repetición y conservación de ediciones y contraseña.
- La integración puede repetirse incluso con la demostración cargada: revierte sus fixtures y compara usuarios, asesorías, clientes, documentos, trimestres y conversaciones existentes, sin cambios. Archivos temporales retirados.
- PHPUnit existente: 295 pruebas y 692 aserciones correctas. PHPStan nivel 6, contenedor y OpenAPI correctos. Formato Symfony verificado sin caché en copias LF de los cuatro archivos PHP, ninguna corrección pendiente.
- Comando ejecutado en Docker local: primera carga correcta y segunda ejecución sin cambios. Después se repitió la integración con los datos locales ya presentes, correcta.
- HTTP real a través de Nginx: los diez accesos autentican con JWT; los cinco clientes suman 33 documentos y consultan sus cierres Q3 con bases 1000/200 euros. Descarga PDF autorizada devuelve 200 y tipo PDF; consulta cruzada de otro cliente devuelve 404. No se imprimieron tokens.
- Guía en `docs/demo.md`, enlazada desde el índice. Tarea 25 marcada como hecha. No se ha realizado una revisión visual de cada PDF ni un recorrido manual completo por las pantallas.

## Cómo probar

Carga: `docker compose exec -T --user www-data app php bin/console app:seed-demo`.

Integración aislada: `docker compose exec -T --user www-data app php tests/Integration/demo-data-workflow.php`.

Evitar ediciones manuales durante la prueba, que compara los datos existentes. Accesos y recorridos en `docs/demo.md`.
