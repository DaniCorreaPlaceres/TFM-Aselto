# Límites de recursos · tarea 43 / F09

## Objetivo

Acotar la frecuencia de operaciones y el tamaño de los listados web/API sin cambiar las cuotas comerciales, perder registros, recortar cifras fiscales ni permitir acceso cruzado entre clientes o asesorías. Rama `feature/resource-limits`, desde develop tras integrar F07 mediante PR #54.

## Cambios planteados

- Presupuesto temporal compartido entre web y API para las subidas de originales, justificantes y adjuntos. Identificar al actor autenticado y aplicar también un presupuesto de asesoría para evitar multiplicar el consumo usando varias cuentas. Rechazar antes de leer/procesar los archivos, escribir registros o llamar a IA; responder 429 con Retry-After y un mensaje sencillo.
- Separar estos presupuestos operativos de la cuota mensual aprobada: el rechazo temporal no crea documentos ni consume cuota mensual. Mantener los tamaños y tipos de archivo ya acordados.
- Paginar en base de datos los listados expuestos, con tamaño máximo validado, orden estable y filtros autorizados. No paginar después de cargar todos los registros en memoria. Mantener comprobaciones de acceso antes de consultar.
- Conservar búsqueda, filtros y ordenación sobre el conjunto autorizado. Mostrar totales y navegación sin ocultar silenciosamente resultados.
- Exportar todos los resultados filtrados en el servidor por bloques, aprobado por el usuario. Incorporar y comprobar OpenSpout para XLSX, conservando CSV. No sustituir la exportación existente por la página visible.
- Separar paginación de presentación y cálculos del trimestre: los importes, pendientes, cierres e históricos deben seguir siendo completos. Revisar consultas auxiliares de inicio, selectores y mensajería para no mantener cargas ilimitadas por otra vía.
- Documentar parámetros, respuestas y compatibilidad API, y los límites que sigan pendientes. F08 queda aplazado por decisión del usuario; no incorporar escáner antimalware.

## Fuera de alcance

Cambiar precios/cuotas comerciales, análisis antimalware, límites específicos del proveedor IA (F10), despliegue Hostinger, pruebas de carga contra terceros, retención o eliminación de documentos. No cambiar la puntuación histórica de la auditoría.

## Criterios de aceptación

- Web/API comparten el presupuesto correspondiente: alternarlas no permite eludirlo. Clientes y asesorías distintas no se bloquean entre sí; no hay contadores controlados por parámetros del solicitante.
- Un rechazo 429 conserva documentos, archivos y consumo mensual. Se comprueba agotamiento, recuperación de ventana y permisos mediante fixtures sintéticos y sin llamadas externas.
- El tamaño, desplazamiento, búsqueda y ordenación se validan y parametrizan; no hay consultas ilimitadas para construir una página ni datos de otros titulares en resultados o conteos.
- Los conjuntos mayores que una página se pueden recorrer sin duplicados por el orden estable; filtros y totales son coherentes. Límites negativos, desmesurados y no numéricos tienen un tratamiento explícito.
- Exportación conforme a la decisión aprobada, con permisos, protección de fórmulas y consumo acotado documentados. Mantener CSV y XLSX.
- Trimestres, cierres y pendientes conservan cifras completas, independientes de los filtros de tabla y del tamaño de página.
- Documentación y contrato API actualizados; regresiones relevantes, análisis estático, formato y comprobación real de interfaz. Solo anotar como comprobado lo ejecutado.

## Comprobado

- PR #54 confirmado como integrado; develop actualizado a `17d2dc7`.
- Presupuestos compartidos por cuenta/asesoría entre web y API, con Symfony RateLimiter, caché de disco y flock. Lecturas, escrituras, subidas/mensajes y exportaciones independientes; rechazo 429 anterior al controlador.
- Listados principales paginados en SQL, búsqueda/filtros/conteos autorizados, UUID para desempatar y hint de ejecución de SELECT de 5 segundos. Selector de destinatarios/documentos con búsqueda por páginas, sin salir del formulario. Inicio limita la vista previa a cinco pendientes y conserva su total; contadores de mensajes agregados en SQL.
- Exportación completa filtrada en bloques de 100, lectura repetible, bloqueo de generación y presupuestos explícitos de 100.000 filas/30 segundos comprobados entre filas. CSV neutraliza fórmulas y XLSX usa celdas explícitas de texto/número. Salida 0600, espacio de trabajo 0700, limpieza tras transferencia/error. Dependencia OpenSpout 4.32.0; Composer alineado con PHP 8.4 ya fijado en memoria/CI. Sin migraciones.
- Movimientos abiertos consultados por fecha/estado/ámbito; cerrados paginados desde snapshot con JSON_TABLE. El cálculo completo se conserva y restringe su consulta al periodo y documentos sin fecha. Históricos y agregados de negocio no se presentan como operaciones de memoria constante.
- Workflow propio **56 comprobaciones** con 131 documentos: páginas sin solapamiento, filtros/conteos, exportación completa, permisos cruzados, exclusión del superadmin, 429 web/API, cuota documental conservada, selectores y movimientos de cierre independientes de cambios posteriores. Fixtures revertidos, sin SMTP/IA ni archivos originales escritos.
- PHPUnit **413 pruebas / 1.111 aserciones**, JavaScript **39 pruebas**, PHPStan, contenedor, formato, **64 Twig**, **24 YAML** y OpenAPI correctos. Sass/AssetMapper compilan **34 recursos**; salidas generadas sin versionar. Composer valida y la instalación no informa de avisos de vulnerabilidad.
- Regresiones secuenciales correctas: autenticación **74**, documentos API **108**, comunicación API **79**, trimestres API **60**, trimestres **43**, actualización real **38**, subida documental **43**, asesorías **70**, independientes **55**, invitaciones **118**, sesión **78**, logout **45** y cabeceras **149**.
- Navegador integrado: bandeja, búsqueda por importe y filtro combinado; XLSX descargado y comprobado con dos filas (cabecera y resultado filtrado); selección de destinatario y búsqueda de documentos sin perder el asunto ni el texto y sin enviar mensajes. Sin errores/avisos de consola en las vistas inspeccionadas. [Captura de la bandeja](../security/evidence/f09-inbox.png). La automatización de descarga sufrió una demora prolongada; no se interpreta como prueba de tiempo de respuesta del servidor. CSV/XLSX completos y fórmulas se verifican además en servidor.
- [Funcionamiento y límites](../security/resource-limits.md). Hostinger, carga sostenida, múltiples nodos, latencias máximas y dimensionamiento de agregados fiscales/históricos siguen pendientes. F08 aplazado y tarea 43 abierta. Auditoría histórica y puntuación intactas.
