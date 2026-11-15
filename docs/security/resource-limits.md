# Límites de recursos · F09

Esta entrega añade presupuestos temporales, paginación en servidor y exportación por bloques. No cambia los planes ni el límite mensual de Gratis. [Ficha](../sdd/resource-limits.md).

## Presupuestos operativos

Las cuentas autenticadas comparten los mismos contadores entre web y API. Cuando tienen asesoría se comprueba también el contador agregado de esa asesoría. Cambiar de interfaz o usar otra cuenta del mismo equipo no crea otro presupuesto de asesoría. Las claves usan HMAC, sin guardar correos en los identificadores.

- Subidas y envío/respuesta de mensajes: **10 operaciones/minuto por cuenta y 120/minuto por asesoría**. Incluye originales, justificantes y mensajes, aunque no lleven adjuntos.
- Lecturas autenticadas: **180/minuto por cuenta y 1.800/minuto por asesoría**. GET y HEAD; no incluye archivos estáticos de nginx ni la renovación de autorización Mercure.
- Otras escrituras autenticadas: **60/minuto por cuenta y 600/minuto por asesoría**.
- Exportaciones: **3 cada 15 minutos por cuenta y 30 por asesoría**; una generación simultánea por asesoría, o por cuenta independiente.

Son ventanas deslizantes de Symfony RateLimiter, caché de archivos y bloqueo flock, configuradas en `config/packages/resource_limits.yaml`. Las peticiones inválidas que llegan al controlador también cuentan: no se permite repetir procesamiento gratis. Un rechazo por presupuesto personal no consume el de asesoría; si se agota el agregado, el intento personal ya contabilizado no se devuelve. Los presupuestos de lectura, escritura, subida y exportación son independientes.

El listener se ejecuta después de la autenticación y antes del controlador y la lectura de archivos por la aplicación. **429** incluye `Retry-After` y no-store. La API devuelve `resource_limit_exceeded`; la web muestra una página en español. No se guarda ningún cambio ni se llama a IA al rechazar. El cuerpo multipart ya habrá sido recibido/procesado por nginx/PHP: esto no es un límite de ancho de banda de red. Los límites de tamaño existentes siguen vigentes.

Login y recuperación conservan sus límites F03. Logout conserva su protección CSRF y no queda bloqueado por estos presupuestos. Tampoco se altera la caducidad de sesión F05 ni se usa una petición automática como actividad humana. Los fragmentos financieros sí consumen presupuesto de lectura, aunque no renueven la inactividad.

## Listados y selectores

Documentos del cliente, bandeja, clientes de la asesoría, clientes independientes, asesorías, equipo, invitaciones, conversaciones y movimientos usan consultas paginadas. La primera respuesta HTML incluye hasta 25 filas, y DataTables solicita cada página al servidor. Búsqueda y filtros se aplican antes del LIMIT; los conteos corresponden al conjunto autorizado. No se recorta una colección completa después de cargarla para construir la página.

Se admiten 1–100 filas, por defecto 25; desplazamiento 0–1.000.000; búsqueda literal de hasta 120 caracteres. Se rechazan tamaños negativos, «todos», valores no numéricos y parámetros malformados. Las columnas y sentidos de ordenación se traducen mediante listas cerradas; el UUID deshace empates. No se ejecutan expresiones regulares ni SQL del solicitante. La paginación normal refleja el estado de cada petición: cambios concurrentes pueden desplazar filas entre páginas.

Las consultas de filas, conteos y resumen de mensajes incluyen el hint MySQL `MAX_EXECUTION_TIME(5000)`. Se ha ejecutado su sintaxis contra MySQL local; no se ha provocado una consulta lenta para medir el umbral. Es un presupuesto de ejecución de SELECT, no un límite de transferencia de red ni de toda la petición HTTP; su efecto en Hostinger debe verificarse.

Las consultas repiten el aislamiento: bandeja exige la asesoría actual del documento y del perfil; clientes exige concordancia de papel y asesoría entre cuenta y perfil; el superadmin solo accede a catálogos administrativos. Equipo sigue siendo exclusivo del admin. Una invitación cuyo correo ya tiene cuenta muestra «Correo ya registrado» y no ofrece reenvío.

Los selectores de destinatario y documento compartido de mensajes tienen búsqueda y navegación de 25 en 25, sin abandonar el formulario ni perder su texto. La opción elegida se conserva al cambiar de página. El servidor vuelve a comprobar el destinatario/documento al enviar. Los contadores de mensajes de navegación/inicio se calculan en SQL, sin cargar conversaciones completas. Inicio muestra hasta cinco documentos pendientes e indica el total con enlace al listado completo.

Los movimientos de un trimestre abierto consultan sus registros incluidos. Los de uno cerrado consultan su **snapshot** mediante JSON_TABLE, no los documentos actuales. Los filtros de tabla no cambian cifras, pendientes ni controles de cierre. El cálculo fiscal conserva todos los registros relevantes; la consulta previa ya restringe por periodo e incluye siempre los documentos sin fecha. No se introducen topes fiscales ni se recorta una instantánea.

## Exportaciones completas

CSV y XLSX se generan en el servidor con todos los resultados filtrados, empezando por la primera fila, aunque el usuario esté viendo otra página. Mantienen filtros, ámbito y ordenación; las columnas de acciones se excluyen. Se leen bloques de 100 sin repetir los conteos en cada bloque, dentro de una transacción de lectura repetible para mantener un conjunto consistente durante la generación.

OpenSpout **4.32.0** escribe XLSX con filas sucesivas; las cadenas se crean explícitamente como StringCell y nunca se infieren fórmulas. Los importes se guardan como números. CSV usa UTF-8 con BOM, `;`, decimales con coma y neutraliza prefijos de fórmula en texto libre. Las fechas de movimientos se exportan en ISO.

La generación síncrona admite hasta **100.000 filas y 30 segundos**. Si se supera el presupuesto devuelve un error explícito para acotar la búsqueda; **no entrega un archivo truncado ni reduce la cuota del plan**. Exportar el desplazamiento mayor de un millón no es un modo de acceder a páginas normales fuera de su límite. Conjuntos que requieran presupuestos mayores necesitarán una tarea asíncrona posterior. El bloqueo de generación se libera antes de transferir la descarga.

El presupuesto temporal de generación se comprueba entre filas; no interrumpe una llamada bloqueada al sistema ni garantiza una duración total HTTP de 30 segundos. Los timeouts del servidor y de la base de datos deben acompañarlo al desplegar.

El archivo terminado y el espacio de trabajo XLSX son privados (0600 y directorio 0700 en Linux). El espacio de trabajo se retira también si falla el productor; una excepción retira la salida incompleta. BinaryFileResponse elimina el archivo final tras enviarlo. Una terminación abrupta del proceso puede dejar temporales: el despliegue debe disponer de limpieza de temporales y espacio suficiente, sin usar un directorio público ni activar X-Sendfile sobre estas descargas.

## API y compatibilidad

El contrato pasa a **0.6.0**, independiente de la versión de producto. Los listados de documentos, bandeja, conversaciones, destinatarios y documentos compartidos reciben `start`, `length`, `q` y devuelven `pagination: {offset, limit, total, filtered}` junto a la colección existente. Los clientes que asumían que una respuesta contenía todos los registros deben recorrer las páginas. Detalles en [API](../api/pagination.md).

Composer se alinea con PHP **8.4 o superior**, mínimo ya definido en la memoria y CI. OpenSpout requiere extensiones DOM, fileinfo, filter, libxml, XMLReader y ZIP; Composer comprueba su disponibilidad. No se añaden migraciones ni se modifica el esquema.

## Prueba manual

1. Entrar con una cuenta de demostración de asesoría y abrir Documentos. Buscar por cliente o importe, cambiar estado, ordenar y variar el tamaño de página. Los conteos deben corresponder al filtro.
2. Descargar CSV/Excel tras filtrar: deben contener todos los resultados filtrados, no solo la página visible. Una cuarta generación en 15 minutos muestra la espera; consultar el listado continúa disponible.
3. En Mensajes → Nuevo mensaje, buscar destinatarios/documentos y usar Más opciones. Cambiar opciones no envía el mensaje ni borra lo escrito.
4. Abrir un trimestre con varias páginas de movimientos. Filtrar la tabla no modifica los totales. Consultar uno cerrado debe seguir mostrando sus cifras y movimientos guardados.

Para agotar presupuestos y probar otra asesoría, usar el workflow sintético; no hacer bucles contra cuentas reales ni enviar archivos al proveedor IA:

```powershell
docker compose exec -T --user www-data app php tests/Integration/resource-limits-workflow.php
docker compose exec -T --user www-data app php vendor/bin/phpunit
```

## Alcance y límites

Estos controles acotan frecuencia, páginas y generación de exportaciones; no acreditan resistencia a DDoS ni una capacidad de carga concreta. Cálculos fiscales completos, instantáneas/históricos y detalle de conversaciones siguen siendo agregados de negocio: no se presentan como operaciones de memoria constante ni se modifican para truncar su contenido. Su coste con volúmenes elevados y el dimensionamiento real de MySQL/Hostinger siguen pendientes de medición. Tampoco se ha verificado almacenamiento de contadores compartido entre varios servidores: caché/flock locales deben sustituirse por un almacén común antes de escalar horizontalmente.

F08 permanece aplazado por decisión del usuario; esta entrega no incorpora análisis antimalware. No se recalcula la puntuación de la auditoría histórica.

## Referencias

- [DataTables: procesamiento en servidor](https://datatables.net/manual/core/server-side) y [FAQ de exportación](https://datatables.net/support/faqs): solo la página solicitada reside en el navegador.
- [OpenSpout 4.x](https://github.com/openspout/openspout/tree/4.x) y [documentación](https://github.com/openspout/openspout/blob/4.x/docs/documentation.md): escritura sucesiva y temporales XLSX. Dependencia fijada en composer.lock. Referencias consultadas el 15/11/2026.
