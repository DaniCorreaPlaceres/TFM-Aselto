# Despliegue de Aselto: memoria de la primera instalación y material para el TFM

Fecha de recopilación: **18/11/2026**. Tarea 45, rama `feature/hostinger-first-install`. Documento preparado a partir de las salidas SSH compartidas por el usuario, sus confirmaciones funcionales, las pruebas locales y las consultas HTTP públicas realizadas durante el trabajo. No contiene contraseñas, tokens, claves ni datos documentales.

## 1. Qué se ha conseguido

Se ha instalado y probado Aselto en Hostinger Cloud Startup, con acceso HTTPS, conexión a MariaDB, migraciones, recursos compilados, alta por correo y visor PDF. Se ha creado una copia privada y se ha ensayado su recuperación en otra base y otro directorio del mismo hosting. Se han configurado tareas programadas de seguridad.

Es una **primera instalación de ensayo en el dominio de destino**, inicialmente vacío, con versión **1.0.0-dev**. No equivale a la publicación final 1.0.0 ni a un entorno de staging con subdominio propio. La copia de recuperación está fuera de la raíz web y no se ha publicado. El CI/CD de despliegue todavía no está implementado: corresponde a la tarea 46.

## 2. Entorno y decisiones técnicas

- Hosting: Cloud Startup con SSH; dominio `aselto.com` asociado.
- PHP web 8.4 comunicado por el usuario. PHP CLI **8.4.27**, comprobado mediante salida SSH. Se utiliza `/opt/alt/php84/usr/bin/php` explícitamente porque el PHP predeterminado de consola era inicialmente 8.1.34.
- Composer **2.9.8**, invocado con PHP 8.4. Los requisitos productivos de Composer pasan en el servidor, incluida la extensión sodium.
- Motor comunicado por phpMyAdmin: **11.8.9-MariaDB-log**. La aplicación conecta, aplica sus 19 migraciones y valida el esquema. MySQL 8.4 continúa siendo la referencia de desarrollo/CI; la compatibilidad MariaDB se ensayó localmente antes de la instalación real.
- Código, dependencias, configuración, documentos y claves en `private/aselto`; únicamente recursos públicos y front controller en `public_html`.
- No se presupone Docker ni servicios persistentes en el hosting. Docker se utiliza localmente para construir y probar el paquete.
- Las cifras se actualizan al recargar o pulsar Actualizar en producción. Por decisión del usuario no se instala un hub Mercure; desarrollo conserva su modo en vivo.
- Correo SMTP seguro de Hostinger por puerto 465, remitente configurable mediante `MAILER_FROM`. Las credenciales se introducen privadamente en `.env.local`.
- La IA permanece **en modo de ejemplo**, sin clave real. La instalación no acredita extracción real ni consumo de OpenAI; una persona sigue revisando y confirmando los datos.

La raíz pública no se cambia para apuntar al repositorio completo. Se adapta el front controller a la aplicación privada. Esto permite respetar el alojamiento disponible y conservar la separación entre archivos servidos y datos internos.

## 3. Secuencia realizada

### Preparación y transferencia

Se partió del código integrado `87adb42`. En una copia aislada se instalaron dependencias sin desarrollo, se compiló Sass y se generaron los recursos de AssetMapper. Se excluyeron secretos locales, claves, datos, caché y herramientas de desarrollo. Se conservaron directorios vacíos requeridos por Doctrine.

Paquete: `aselto-first-install-87adb42.zip`, 8 943 entradas. SHA-256:

```text
b7f222ac94f9a2e9153bfa5ca048d8748038d6ea7630de859244809e4ee93970
```

El usuario lo subió a una carpeta privada y verificó la coincidencia del hash por SSH. Se extrajo primero la aplicación privada. La página inicial `default.php` de Hostinger se conservó en una copia privada antes de publicar Aselto.

### Configuración y base de datos

Se creó `.env.local` con permisos 0600, secretos aleatorios propios y conexión a una base nueva. Las contraseñas se codificaron para la URL sin incorporarlas a Git ni al chat. Primero se comprobó versión y estado de migraciones; después se aplicaron **19 migraciones y 26 consultas**, hasta `Version20261114050000`. Doctrine confirmó mapping correcto y esquema sincronizado.

Se generaron claves JWT propias sin sobrescribir claves existentes y se reconstruyó la caché en `prod`, con `debug=false`. No se cargaron seeds de demostración en el hosting.

### Activación y recorridos

Se instalaron los archivos públicos y el `.htaccess` adaptado. El usuario confirmó inicio y salud correctos, bloqueo de las rutas privadas propuestas y redirección HTTP a HTTPS. No se conservó una captura técnica de los códigos de cada ruta privada.

Tras configurar SMTP y corregir el remitente, la prueba directa terminó con código 0 y el usuario confirmó recepción en la bandeja de entrada. Después recibió un enlace de cuenta, creó su contraseña y entró. Comunicó resultado correcto de las pruebas funcionales propuestas, salvo el visor, corregido posteriormente. No se presenta esa confirmación general como una batería completa de pruebas por rol, API o aislamiento entre asesorías en producción.

## 4. Incidencias y cómo se resolvieron

### PHP web y PHP de consola diferentes

La versión seleccionada en hPanel no garantizaba la usada por SSH. Se identificó un binario PHP 8.4 y se utilizó su ruta completa para Composer, consola y cron. Sodium no aparecía inicialmente; tras habilitarla se confirmó sin cargarla manualmente por comando.

**Aprendizaje:** verificar el entorno que ejecuta realmente cada proceso y no limitarse a la versión del panel.

### El alta decía que se había enviado un correo, pero no llegaba

Al principio el transporte era `null://null`, elegido para descartar envíos durante la preparación. Además, los notificadores llevaban un remitente fijo de desarrollo terminado en `.test`.

Se incorporó `MAILER_FROM` a los correos de activación, recuperación, invitaciones y cambio de dirección. La corrección se distribuyó mediante un parche mínimo de cuatro archivos, con copia previa, hashes y reconstrucción de caché. El parche no incluía credenciales ni modificaba la base o las claves.

Se separaron dos comprobaciones: aceptación SMTP mediante `mailer:test` y recepción real por el destinatario. Finalmente se comprobó el recorrido completo del enlace de cuenta.

**Aprendizaje:** el mensaje genérico de la interfaz no demuestra la entrega y protege frente a la enumeración de cuentas. Transporte, remitente, destinatario y enlace se verifican por separado.

### El PDF quedaba en «Preparando vista previa»

Los archivos PDF.js y su worker respondían correctamente. Sin embargo, dos módulos `.mjs` del arranque de la interfaz se servían con `Content-Type: text/plain`. Su carga forma parte del registro de controladores y el navegador exige un tipo JavaScript válido.

Se añadieron al `.htaccess` las asociaciones `.js/.mjs → application/javascript` y `.wasm → application/wasm`. Una segunda consulta HTTP confirmó ambos módulos con 200 y `application/javascript`; el usuario confirmó el visor correcto tras recargar. Se mantuvieron CSP y `nosniff`.

**Aprendizaje:** un recurso puede responder 200 y aun así resultar inutilizable para el navegador. Hay que comprobar también sus cabeceras. La configuración de LiteSpeed requiere pruebas reales; el servidor PHP local no valida `.htaccess`.

### Avisos de Doctrine

La migración mostró un aviso por commits implícitos de DDL y la validación otro por `Table::setPrimaryKey()` obsoleto en `QuarterUpdateSchema`. Las migraciones y el esquema terminaron correctamente. Ambos avisos siguen registrados para corregirlos; no se ocultaron ni se consideran solucionados.

La generación JWT también mostró una línea aislada «bug», cuyo origen no se ha identificado. El comando terminó correctamente, pero no se atribuye ese texto a una causa no demostrada.

## 5. Protección de archivos y recuperación

Las comprobaciones SSH mostraron 0700 en carpetas privadas principales, registros de seguridad y claves; `.env.local` y las claves PEM tenían 0600. Se restringió también el almacenamiento documental a directorios 0700 y archivos 0600. Estos permisos no aíslan procesos que compartan el mismo usuario Unix.

Para la copia se activó mantenimiento mediante HTTP 503, comprobado con curl. El dump se hizo con `mariadb-dump`, contraseña solicitada interactivamente, `--single-transaction`, `--quick`, `--skip-lock-tables`, `--no-tablespaces` y `--hex-blob`. No se introdujo la contraseña en el comando.

La copia `first-install-20261118T021427Z` incluye SQL, aplicación privada con documentos/configuración/claves y `.htaccess` público. Se excluyeron caché y log reconstruibles; se generó un manifiesto SHA-256 y se restringieron los archivos a 0600. Se repuso la configuración pública para reabrir la web.

Evidencias de recuperación:

1. Los tres archivos del manifiesto verificaron OK.
2. Se comprobó que la base destino separada estaba vacía antes de importar.
3. La importación terminó con código 0; el destino mostró **13 tablas y 19 migraciones**.
4. La aplicación se extrajo en una carpeta privada nueva, conservando el original.
5. La configuración de la copia se cambió al usuario/base de restauración y se verificó el correo `null://null` antes de arrancarla. Se indicó mantener IA y alertas desactivadas.
6. La aplicación recuperada devolvió `1.0.0-dev`, 19 migraciones ejecutadas/0 pendientes y esquema sincronizado contra la base de ensayo.
7. `tar --diff` comparó almacenamiento y claves con el archivo de copia: sin diferencias, código 0.

Esto acredita importación, arranque, esquema y coincidencia de los archivos comparados. **No acredita** comparación exhaustiva de todas las filas, descarga HTTP desde la copia recuperada, copias externas ni un RPO/RTO medido. La copia sigue en el mismo alojamiento: no cubre por sí sola la pérdida de esa cuenta.

En el ensayo local anterior se compararon 13 tablas/91 filas, 39 archivos y claves y 33 originales legibles. Son datos sintéticos de otro ensayo: **no deben atribuirse a la prueba real del hosting**. [Evidencias locales del 16/11](hostinger-evidence.json).

## 6. Operación y tareas programadas

Se probaron manualmente en la aplicación original:

- `app:security retention`: «Archivos retirados: 0». Aplica la conservación acordada de 30 días.
- `app:security alerts`: `[]`, sin envío de correo.

Se indicó activar resúmenes con remitente autorizado y destinatario `webmaster@aselto.com`. El usuario confirmó creadas las dos tareas de tipo Personalizado en hPanel, con el binario PHP 8.4 y ruta privada de consola:

- Retención diaria: `15 3 * * *`, a las 03:15 UTC.
- Consulta/envío de alertas: `*/15 * * * *`; la aplicación limita los resúmenes a uno por hora si hay nuevas alertas.

Después comunicó «Todo OK». Se conserva como **confirmación funcional del usuario**, sin inventar salida, fecha o código de cada ejecución automática. Faltan evidencias fechadas de ambos cron y un resumen real tras superar un umbral de alertas; el correo de cuenta sí tiene entrega confirmada. No hace falta producir intentos abusivos contra la web para una demostración.

## 7. Guion oral orientativo, unos tres minutos

> Para llevar Aselto al hosting no copié directamente el entorno Docker. Primero comprobé las capacidades reales de Cloud Startup y la diferencia entre PHP web y consola. Preparé el código con dependencias productivas y recursos compilados y verifiqué su integridad al transferirlo.
>
> Separé la raíz pública del código, los documentos, las credenciales y las claves. Configuré producción sin depuración, apliqué las 19 migraciones previstas y validé el esquema. Adapté la actualización de cifras al alojamiento disponible para no depender de un proceso Mercure permanente.
>
> La instalación real permitió detectar dos problemas que las pruebas locales no cubrían completamente. El correo necesitaba un remitente configurable y una prueba de entrega real. El visor PDF no arrancaba porque LiteSpeed servía algunos módulos como texto. Corregí los tipos MIME manteniendo las cabeceras de seguridad.
>
> También ensayé la recuperación. Con la web en mantenimiento hice una copia de base, aplicación, documentos, configuración y claves. Verifiqué sus hashes y recuperé la base y la aplicación en destinos separados, sin sustituir el servicio original. La copia arrancó con el esquema correcto y los documentos y claves comparados coincidieron con el archivo.
>
> Finalmente configuré mantenimiento periódico de los registros de seguridad. Distingo lo comprobado por comandos, lo confirmado por el usuario y los controles pendientes. Esta es una instalación manual de ensayo; la siguiente tarea es automatizar el procedimiento mediante CI/CD, con validación del PR y despliegue de main tras el merge aprobado.

## 8. Material para las diapositivas y la demostración

Propuesta de cuatro diapositivas para incorporar a la presentación general, todavía pendiente de los requisitos del máster:

1. **Destino y arquitectura de despliegue:** navegador HTTPS → public_html/front controller → aplicación privada → MariaDB/almacenamiento; SMTP y cron como servicios auxiliares. Contrastar Docker local con hosting, sin presentarlos como el mismo entorno.
2. **Instalación verificable:** paquete identificado, hash, requisitos PHP, 19 migraciones, esquema correcto, claves privadas y caché.
3. **Diagnóstico en producción:** correo y MIME del visor, con evidencia antes/después y causa concreta.
4. **Operación y recuperación:** mantenimiento 503, copia coherente, base/directorio separados, archivos comparados, cron y límites pendientes.

Para la demo: mostrar home HTTPS, acceso con cuenta de ensayo, un PDF sintético con el visor, salida de versión/esquema y evidencias de recuperación. No ejecutar migraciones ni restauraciones sobre el servicio activo durante la defensa. Mantener una captura alternativa por si falla la conexión.

Capturas que conviene conservar con datos ficticios y sin secretos:

- PHP CLI/requisitos Composer; migraciones y validación de esquema.
- Salud e inicio, correo de ensayo con el enlace/token oculto y visor con un documento sintético.
- Cabecera MIME corregida; permisos sin contenido de archivos privados.
- Hashes OK, destino de restauración, 13 tablas/19 migraciones y comparación con código 0.
- Listado de cron y salidas fechadas cuando estén disponibles.

Las capturas no se han generado automáticamente en esta recopilación. No reutilizar documentos privados o mensajes con tokens como material de la presentación.

### Permiso del .htaccess después de recuperar una copia

La revisión posterior encontró portada 200, pero rutas internas 404. El backup se había restringido a 0600 y `cp -p` trasladó ese permiso al `.htaccess` público. El usuario confirmó 0600 mediante stat; tras chmod 644, salud volvió a 200. La comprobación HTTP posterior confirmó `/entrar` 200, `/salud` 200 y `/api/v1/me` sin autenticar 401. La cookie anónima de acceso tiene Secure, HttpOnly y SameSite=Lax; el acceso y la API incorporan no-store/private.

Se corrigió el procedimiento para recuperar el archivo público con permiso explícito 0644, manteniendo su copia privada a 0600. **Aprendizaje:** conservar permisos no siempre es correcto al pasar de un backup privado a un archivo público. Una portada visible no demuestra que funcionen las reglas de enrutamiento. Esta incidencia está resuelta; la tarea completa conserva los pendientes siguientes.

## 9. Pendientes y siguiente entrega

Comprobaciones adicionales tras el PR 65: las consultas API sin autenticar a identidad, documentos, documentos de asesoría y conversaciones responden 401 con no-store/private. El helper privado de lectura se ejecuta en Hostinger el 18/11 a las 17:38:44 UTC y confirma base de recuperación correcta, transacción de solo lectura, un original leído y cero fallos. No hay justificantes ni adjuntos en esa copia, por lo que no se acredita su lectura real. [Guía y alcance](hostinger-verification.md).

El usuario confirma después las salidas automáticas en hPanel: alertas `empty` y retención `Archivos retirados: 0`. No se capturan fecha/hora exacta ni código de salida de cada ejecución. La captura de alertas muestra solo el resultado y su pertenencia a Aselto se confirma expresamente en la conversación. Para la defensa, distinguir estas salidas del envío de un resumen con alertas reales, que no se ha probado.

La API autenticada responde 200 en login e identidad. En el ensayo documental se detectó que se estaba usando un token de cuenta vinculada, no el del autónomo independiente. Tras corregir la autorización y confirmar firm_id=null, el usuario obtiene 404 tanto en detalle como en archivo del documento ajeno. Esta comprobación ilustra por qué se debe verificar la identidad del token antes de interpretar una prueba de permisos; no se atribuyen los primeros 200 a una fuga demostrada.

El contraste final con un documento propio del autónomo independiente devuelve 200 en detalle y archivo, según confirma el usuario. Para la defensa puede describirse el recorrido API de acceso, lectura propia permitida y lectura ajena rechazada, respetando el alcance de estas pruebas y sin presentar una auditoría completa de todos los endpoints.

Alta inicial del administrador: el hosting solo tenía la cuenta de autónomo del ensayo. Se prepara un comando privado `app:superadmin:create` que reutiliza el alta y el enlace de activación, sin importar usuarios demo ni promover la cuenta cliente. Requiere correo nuevo y confirmación interactiva; rechaza correos existentes y no muestra credenciales. Destinatario indicado por el usuario: admin@aselto.com. El código se verifica localmente; creación, recepción y acceso reales siguen pendientes. El comando debe instalarse y reconstruir caché antes de invocarlo, porque el contenedor de producción no descubre servicios nuevos sobre una caché antigua.

Actualización de límites PHP: la grabación de hPanel muestra `uploadMaxFilesize=256M` y `postMaxSize=256M`; el usuario comunica memoria 512M, ejecución 300 s y entrada 240 s. Indica que el panel no permite reducir los tamaños por debajo de 256M, por lo que se conservan. La aplicación mantiene 8 MB por archivo, pero su validación no equivale a un límite de recepción del servidor/proxy. El usuario confirma subida pequeña, detalle y visor correctos con esta configuración. Pendientes prueba de rechazo al superar 8 MB y comprobación de límites efectivos; no presentar el panel como prueba directa del runtime web.

En la prueba posterior de archivo mayor de 8 MB, el usuario comunica el aviso «El archivo supera los 8 MB. Elige uno más pequeño.». Se verifica el rechazo visible en la interfaz; no se inspeccionó la petición ni se comprobó el estado posterior de base/archivos para atribuirlo a la validación del servidor. El usuario decide saltar la descarga de una copia externa, que permanece pendiente.

La tarea 45 permanece abierta: completar controles de límites PHP web, cabeceras/cookies/proxy y caché, recorridos por rol/API/aislamiento, lectura desde la recuperación aislada, evidencias del cron, política/copia externa y capacidad operativa. Se deben cerrar o declarar explícitamente sus límites antes de la entrega final.

La tarea 46 preparará CI/CD con los pasos ya ensayados: validaciones en el PR, construcción sin secretos, transferencia, exclusión de despliegues simultáneos, preservación de datos/configuración, mantenimiento, copia, migraciones revisadas, caché, salud/versionado y recuperación. Producción tras el merge aprobado a main es la propuesta documentada; todavía no existe ese workflow de publicación.

La tarea 48 incluye la presentación y el vídeo completos. Este documento aporta el bloque de despliegue y su guion, sin marcar como finalizada toda la presentación ni la versión 1.0.0.

Pruebas posteriores: superadmin creado desde consola y activado, e invitación/alta/acceso de una asesoría confirmados por el usuario. Al intentar incorporar al autónomo existente, el formulario rechaza el correo ya registrado. La revisión confirma que solo implementa altas nuevas; la vinculación mediante aceptación estaba acordada, pero no implementada. Se incorpora como tarea 49, sin tratarla como fallo de SMTP o corregirla mediante una asignación directa en SQL. Esta limitación debe constar en la defensa hasta implementar y verificar ese flujo.

El usuario confirma después el recorrido completo propuesto para un cliente nuevo de la asesoría: creación, correo de activación, acceso, datos de contacto en Mi asesoría, subida y envío de PDF de ensayo y aparición en Bandeja de su asesoría. Se registra como confirmación funcional, sin atribuirle pruebas de validación posterior ni aislamiento entre dos asesorías que no se han ejecutado en este recorrido.

En una comprobación posterior, el usuario confirma validación por la asesoría, estado validado e importes en el trimestre del cliente y conservación del acceso al original. Esta evidencia se añade al recorrido documental; siguen pendientes los controles de acceso ajeno y otros estados no recorridos.

El usuario confirma además la denegación de acceso del superadmin al detalle y archivo de un documento ajeno en la prueba guiada. No se capturan códigos HTTP individuales. Se acredita ese límite funcional por confirmación del usuario, sin extenderlo a todos los roles, asesorías o endpoints API.

Al repetir ese intento con la cuenta del autónomo independiente original, el usuario confirma ambos accesos rechazados. La evidencia sigue siendo funcional declarada y no sustituye la prueba cruzada entre dos asesorías diferentes.

La prueba cruzada se realiza a continuación: el usuario confirma segunda asesoría de ensayo activada, sus listas de clientes y bandeja vacías y rechazo del detalle/archivo del documento de la primera asesoría. Con esto el recorrido web de este documento queda comprobado funcionalmente para propietario y asesoría autorizada, frente a superadmin, cliente independiente ajeno y segunda asesoría. No se han probado por ello todas las operaciones, estados ni endpoints API.

Referencias internas: [ficha y evidencias de la tarea](../sdd/hostinger-first-install.md), [guía de Hostinger](hostinger.md), [pasos operativos](cloud-startup-steps.md), [seguridad y alertas](../security/security-event-logging.md), [versionado](../versioning.md) y [lista de tareas](../Tareas.md).
