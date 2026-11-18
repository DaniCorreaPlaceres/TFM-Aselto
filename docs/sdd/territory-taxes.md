# Impuestos por territorio

## Objetivo

El documento toma el impuesto del territorio de la provincia del cliente al subirlo. La persona revisa los datos, guarda un borrador o confirma. La cuota y el total se calculan en la aplicación.

## Fuera de alcance

Recargo de equivalencia, retenciones de IRPF, determinación fiscal de operaciones entre territorios, validación de la asesoría, trimestre y API. No se modifican los permisos ni se da por validado un documento al confirmarlo.

## Criterios de aceptación

- Península y Baleares usan IVA con tipos 0, 4, 10 y 21. Las Palmas y Santa Cruz de Tenerife usan IGIC con tipos 0, 3, 7, 9,5, 15 y 20. Ceuta y Melilla usan IPSI.
- El IPSI permite escribir manualmente el porcentaje del documento, entre 0 y 100 con hasta dos decimales. Este límite es de entrada de datos, no un catálogo de tipos fiscales.
- Si falta la provincia, se pide completar el perfil antes de guardar el archivo. No se presupone un territorio.
- El impuesto queda guardado en el documento. Cambiar la provincia del perfil no cambia documentos anteriores. Los documentos existentes se conservan como IVA, con sus importes y estado.
- El servidor valida el porcentaje según el impuesto guardado; el formulario no puede cambiar ese impuesto. Se rechazan porcentajes negativos, texto, notación exponencial y precisión superior a dos decimales.
- Cuota y total se calculan con aritmética entera y redondeo al céntimo. El cálculo de la pantalla coincide con el del servidor; el cero es un porcentaje válido.
- La extracción recibe el impuesto correspondiente y solo propone tipos admitidos para IVA o IGIC. El IPSI queda vacío para entrada manual. Los importes de cuota y total del proveedor se ignoran.
- El adaptador de ejemplo muestra un tipo del impuesto correspondiente, salvo IPSI, cuyo porcentaje queda vacío. Un fallo de lectura conserva el impuesto y permite escribir los datos.
- La revisión y el documento confirmado muestran IVA, IGIC o IPSI según corresponda, y mantienen los textos de ejemplo y revisión humana.
- Las pruebas comprueban selección de territorio, tipos y redondeo, extracción, guardado y confirmación, acceso del propietario, persistencia y conservación de documentos existentes.

## Comprobado

- PHPUnit en Docker: 106 pruebas y 285 aserciones correctas. Incluye todos los tipos de IVA e IGIC, porcentaje manual de IPSI, redondeo, entradas inválidas, selección por provincia, guardado, confirmación y aislamiento del propietario.
- PHPStan nivel 6: sin errores. Twig: plantilla de revisión con sintaxis válida. OpenAPI: válido.
- PHP-CS-Fixer: sin diferencias en los 38 archivos PHP del módulo, sus pruebas y la migración, sobre una copia con finales LF como en el checkout de Linux.
- Cálculo del controlador de pantalla ejecutado con Node: seis escenarios de importes y doce porcentajes inválidos correctos.
- Migración Version20261107140000 aplicada en MySQL local. Se compararon los ocho documentos anteriores antes y después: conservan todos sus datos, estado e importes; el IVA se convierte de porcentaje entero a centésimas de porcentaje.
- Guardado y recuperación reales en MySQL para IVA, IGIC e IPSI: impuesto, porcentaje decimal, cuota, total y estado correctos. Los registros temporales se revirtieron al terminar.
- Peticiones GET y POST al núcleo HTTP de Symfony con sesiones de prueba: revisión y confirmación correctas para IVA, IGIC e IPSI; la lista incluye 9,5 % y el IPSI tiene campo manual. Se revirtieron las cuentas y documentos de prueba.

## Limitaciones y pendientes

- No se ha ejecutado una lectura real con el proveedor de IA ni una revisión visual en navegador. El adaptador está probado con respuestas simuladas.
- La consulta de /salud a través de localhost:8080 agotó 40 segundos. La prueba del formulario se hizo contra el núcleo HTTP, sin pasar por Nginx.
- Doctrine valida los mapeos, pero la validación completa del esquema señala diferencias en identificadores, índices y fechas anteriores. El SQL propuesto no pide cambios para las nuevas columnas tax y tax_rate. No se ha aplicado esa actualización general del esquema.
- Se observó que la fecha de creación en UTC se recupera con la zona predeterminada Europe/Madrid. El campo no se modifica en esta tarea; la comprobación específica de impuestos en MySQL usó fechas con la zona predeterminada y no acredita la conservación de instantes UTC.
- El aviso de carga de OPcache y la diferencia entre el PHP mínimo de Composer y el acordado en la memoria siguen pendientes.
