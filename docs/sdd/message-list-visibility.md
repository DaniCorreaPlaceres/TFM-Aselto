# Visibilidad del listado de mensajes

## Objetivo

Resolver la incidencia comunicada en Hostinger 1.0.0: el texto de una conversación se consulta desde su URL por ambas cuentas, pero no aparece en el listado ni para emisor ni receptor. Rama `feature/message-list-visibility`, desde develop, con copia de trabajo aislada.

## Cambios planteados

- Reproducir la discrepancia entre consulta directa, listado HTTP y tabla JavaScript, en ambos sentidos y con/sin adjuntos.
- Comprobar el comportamiento SQL en MySQL/MariaDB con cuentas y conversaciones ficticias, sin consultar datos privados de producción.
- Corregir únicamente la causa demostrada y añadir una regresión que distinga creación, consulta directa y listado de ambas cuentas.
- Preservar permisos y datos existentes; preparar corrección compatible con nueva versión de parche si procede publicar.

## Fuera de alcance

Reescribir mensajería, ampliar acceso entre asesorías, modificar registros productivos por SQL, usar contraseñas del usuario o afirmar que los adjuntos funcionan sin probarlos. No modificar la etiqueta publicada v1.0.0.

## Criterios de aceptación

- Cada conversación permitida aparece para cliente y equipo correcto tras recargar, y su enlace permite leer el texto.
- Cuenta o asesoría ajena sigue sin consultar ni listar la conversación.
- Regresión reproducida antes y correcta después de la corrección, con resultados reales registrados.

## Causa y corrección

El usuario confirma acceso directo correcto y respuestas JSON del listado con dos filas, tanto en la consulta sencilla como con los parámetros de búsqueda/ordenación. La captura muestra el aviso de error de carga de DataTables, que sustituye la tabla por una vista vacía.

La plantilla compartida tomaba `section` del contexto de la página. Mensajería usa `section=mensajes` para marcar la navegación; ese valor acababa en las URLs de consulta y exportación de la tabla. `RecordTableInput` lo rechaza porque no es un estado documental permitido. El renderizado inicial sí contenía las filas, pero la carga posterior fallaba y vaciaba la tabla. La misma colisión podía afectar a otras pantallas de cliente con una sección de navegación.

Se utiliza `tableSection` exclusivamente para el filtro de la tabla. La bandeja pasa explícitamente su estado; se conservan pendientes, rechazados, validados e histórico personal. No se amplía el listado de filtros válidos del servidor, no se modifican permisos, mensajes o base de datos, ni se cambia JavaScript o sus dependencias.

## Comprobado localmente

- Regresión antes de corregir: el renderizado con navegación mensajes, documentos y movimientos genera criterios inválidos y reproduce HTTP 400. La prueba inicial arroja errores; después de separar contextos, las URLs generadas se aceptan.
- PHPUnit completo sobre la copia aislada: **476 pruebas / 1.391 aserciones**, PHP 8.5.12. Incluye siete casos de plantilla, con consulta/exportación y los cuatro estados de bandeja.
- Workflow de mensajería en MySQL 8.4 desechable: **46 comprobaciones correctas**. Se siguen las URLs realmente renderizadas para cliente, admin y gestor, comprobando visibilidad de una conversación creada con adjunto y su respuesta solo de texto. Se conservan controles de asesoría/cuenta ajena y descarga privada. Fixtures y archivos ficticios retirados; datos fiscales previos conservados.
- PHPStan y 67 plantillas Twig correctos. Formato revisado con PHP-CS-Fixer sobre PHP 8.4.26 para las dos pruebas.
- Comprobación temporal del controlador con DOM simulado y la biblioteca DataTables existente: una respuesta JSON válida se representa correctamente. La herramienta temporal vive en var, no añade dependencias al producto ni sustituye una prueba visual en navegador real.

## Pendiente

Commit local autorizado por el usuario el 26/11/2026. Quedan PR y despliegue del parche mediante el procedimiento protegido; verificación visual real en Hostinger para ambos roles y repetición de la prueba de adjuntos de la tarea 47. No se ha cambiado producción, no se ha modificado v1.0.0 ni se considera completada la prueba manual de mensajería. Docker se arrancó para los ensayos; la copia principal y su documentación en curso se han mantenido separadas. `config/reference.php` generado queda fuera del cambio.
