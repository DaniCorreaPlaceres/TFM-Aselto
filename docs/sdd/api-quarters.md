# API de trimestres

## Objetivo

Consultar cifras, pendientes, estado e histórico y cerrar o reabrir trimestres mediante JWT, reutilizando ShowQuarter y ChangeQuarter.

## Fuera de alcance

Comunicación, nuevos cálculos fiscales, nuevas reglas de cierre, migraciones y cambios de interfaz web.

## Criterios de aceptación

- Cliente consulta su trimestre; admin y gestor consultan clientes actuales de su asesoría. Sin acceso fiscal para superadmin ni otras asesorías.
- Ámbitos e históricos personales conservan los permisos de la web. La asesoría solo consulta el histórico personal.
- Respuestas privadas sin caché, importes enteros en céntimos, impuestos separados, pendientes e instantáneas con autor y fecha.
- Cierre exige versión (cero si todavía no existe registro) y huella SHA-256 de las cifras consultadas; reapertura exige versión. Se recarga la versión persistida tras cada cambio.
- Pendientes y documentos sin fecha bloquean el cierre. Conflictos de versión, huella o estado devuelven 409 sin modificar datos.
- Entrada estricta, errores JSON, OpenAPI y guía de uso coherentes. Integración HTTP con JWT real y datos sintéticos revertidos.

## Comprobado

- Rama `feature/api-quarters`, creada desde `develop` tras integrar Swagger UI.
- Seis rutas para cliente y asesoría: consulta, cierre y reapertura. Reutilizan ShowQuarter y ChangeQuarter sin cambios del dominio ni migraciones.
- PHPUnit: 285 pruebas y 682 aserciones correctas. Incluye entrada JSON inválida, inyección de campos, versión inicial cero y límite del cuerpo.
- Integración HTTP, MySQL y JWT firmado real: 60 comprobaciones correctas. Permisos por papel, aislamiento, ámbito ajeno, cierre vacío, versiones persistidas, huella desactualizada tras cambiar documentos, doble cierre/reapertura, límites del periodo, IVA/IGIC/IPSI separados, pendientes fechados y sin fecha, subida tardía e instantáneas inmutables.
- Histórico personal tras vinculación simulada: asesoría consulta sin gestionar; propietario reabre y vuelve a cerrar. La vinculación se prepara como fixture, no se añade un nuevo flujo de producto.
- Regresión web del trimestre: 43 comprobaciones correctas. Regresión API documental: 74. Los datos sintéticos se revierten; el recorrido nuevo no crea archivos y contrasta que los documentos existentes no cambien.
- PHPStan nivel 6, contenedor, formato Symfony de los cinco archivos PHP y OpenAPI 0.4.0 correctos. Se conservó el formato de las operaciones anteriores del contrato.
- Swagger UI carga OpenAPI 0.4.0 y muestra las seis operaciones y sus esquemas bajo Trimestres. La ejecución autenticada se ha comprobado por HTTP en integración; no manualmente mediante el formulario de Swagger.
- Guía en `docs/api/quarters.md`. Comunicación sigue pendiente en la tarea 24.

## Cómo probar

Con Docker y las claves JWT locales preparados:

`docker compose exec -T --user www-data app php tests/Integration/api-quarters-workflow.php`

Usa datos sintéticos y revierte todo al terminar. Evitar cambios manuales de documentos durante la prueba, que compara los existentes antes y después.

En Swagger UI, obtener un token, autorizar y abrir Trimestres. Consultar año y trimestre, copiar `version` y `fingerprint` al cierre y después usar la versión devuelta para reabrir. Seguir `docs/api/quarters.md` para los permisos y ámbitos.
