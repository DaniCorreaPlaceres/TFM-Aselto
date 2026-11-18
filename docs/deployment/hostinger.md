# Preparación de producción en Hostinger

**Actualización 18/11/2026: primera instalación manual de ensayo realizada por el usuario en Cloud Startup.** PHP CLI 8.4.27 y requisitos Composer comprobados por salidas SSH; 19 migraciones y esquema correcto, acceso por correo y visor confirmados. Copia e importación en una base separada, arranque de la aplicación recuperada y comparación de documentos/claves comprobados. Cron configurado y confirmado por el usuario, sin salidas fechadas de cada ejecución automática. El asistente no se conecta por SSH: el usuario ejecuta las instrucciones. No se han cambiado DNS ni implementado CI/CD. El relato, evidencias y pendientes actuales están en [material para el TFM](hostinger-tfm.md) y la [ficha de instalación](../sdd/hostinger-first-install.md).

Las secciones siguientes conservan el procedimiento y el ensayo local previo del 16/11. Sus indicaciones sobre comprobaciones pendientes describen esa preparación; deben leerse junto al estado actualizado enlazado, sin atribuir las cifras del ensayo sintético local a la instalación real.

## 1. Datos del entorno que hay que confirmar

- Confirmado por el usuario: **Cloud Startup**, SSH disponible y dominio `aselto.com` asociado. Pendientes ruta privada de aplicación, usuario de ejecución y acceso efectivo; no registrar contraseñas ni claves en esta documentación.
- Dominio y host canónico, HTTPS y quién termina TLS; existencia de proxy/CDN y direcciones de confianza verificadas.
- PHP web y CLI, versiones y extensiones de ambos; límites efectivos de memoria, tiempo y subidas.
- Motor y versión exactos de base de datos, usuario y permisos de migración. Recoger `SELECT VERSION();`, no asumir MySQL por el nombre comercial del panel.
- Confirmar modo manual productivo: no se requiere hub ni publicador financiero. El cron de seguridad y su transporte se verifican aparte.
- Cron, bloqueo entre ejecuciones, SMTP y remitente autorizado, copias/restauración, espacio e inodos, retención de archivos y registros privados.

No hace falta compartir secretos para responder estas preguntas. Con SSH confirmado se prepararán comprobaciones de lectura sobre rutas y servicios concretos, antes de ejecutar instalación o migraciones.

### Primera recogida de datos, sin cambiar el servidor

En hPanel, anotar la versión PHP seleccionada para aselto.com. Por SSH, ejecutar `php -v` y `php -m`: el PHP de consola puede diferir del PHP de la web. No publicar una página phpinfo ni copiar variables de entorno o archivos privados para obtener esos datos.

En la pestaña SQL de phpMyAdmin de una base del hosting:

```sql
SELECT VERSION();
```

Solo lee el motor y su versión; no aplica migraciones ni modifica datos. Con esa versión se preparará un ensayo separado de MariaDB y se comprobarán las migraciones y los workflows antes de aceptar compatibilidad. No se utilizará la base real del hosting para ejecutar las pruebas sintéticas.

### Actualizaciones manuales aprobadas

El usuario eligió actualizar al recargar o pulsar Actualizar. La adaptación está integrada en PR #63: producción tiene `app.quarter_live_enabled=false`, no genera/publica nuevos avisos, no solicita credenciales ni abre SSE. No instalar hub Mercure ni programar el publicador financiero en Cloud. El modo en vivo queda para desarrollo. Ver [adaptación y pruebas](../manual-quarter-refresh.md).

## 2. Requisitos que sí se conocen del proyecto

### Ejecución y construcción

El proyecto requiere PHP **8.4 o superior**; CI utiliza 8.4 y Docker local 8.5. Se deben verificar versión y extensiones tanto en web como en CLI. `composer check-platform-reqs --no-dev` contrasta las dependencias productivas; además se requiere el controlador `pdo_mysql`. JWT utiliza OpenSSL; archivos y exportación requieren las extensiones declaradas por las dependencias, incluidas las de XML/ZIP. No sustituir esta comprobación por revisar solo el número de PHP del panel.

La construcción usa Composer y Sass/AssetMapper. En un alojamiento sin compiladores se debe construir un paquete fuera del hosting, compatible con el PHP destino, y transferir los recursos ya generados. El soporte de ejecución/instalación de binarios de Web/Cloud no se presupone. [Capacidades oficiales](https://www.hostinger.com/support/which-server-capabilities-are-supported-at-hostinger/).

El paquete productivo debe excluir configuración local, claves locales, tests, herramientas de desarrollo y copias de seguridad públicas. Las dependencias productivas se instalan con `composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader`, sobre `composer.lock`; no ejecutar `composer update` en producción. El paquete se ha ensayado localmente sin dependencias de desarrollo, incluyendo Sass, 34 recursos compilados, caché productiva y acceso web; detalles y límites en la evidencia.

### Base de datos

La referencia comprobada es **MySQL 8.4/InnoDB**, con 19 migraciones hasta `Version20261114050000`. El código utiliza JSON, JSON_TABLE, consultas JSON, bloqueos transaccionales y un outbox de notificaciones. La compatibilidad de MariaDB no se deduce de que acepte conexiones con el controlador MySQL.

Hostinger documenta **MariaDB en Web/Cloud**. El usuario comunica 11.8.9-MariaDB-log. Se ha ensayado MariaDB 11.8.9 comunitaria en un contenedor desechable: 19 migraciones, esquema correcto y los 19 workflows pasan, sin adaptar SQL ni metadatos. Esto no acredita las opciones, privilegios, configuración o recursos de la instancia de Hostinger. No configurar `serverVersion=8.4` para una MariaDB. Un VPS permite plantear MySQL, pero no se asume que exista ya instalado ni se contrata nada en esta tarea. [Motor de Hostinger](https://www.hostinger.com/support/1583226-which-database-management-system-is-used-at-hostinger/), [consulta de versión](https://www.hostinger.com/support/how-to-check-your-database-version-in-phpmyadmin-at-hostinger/).

### Raíz pública y persistencia

Symfony sirve exclusivamente `public/`; código, vendor, configuración, claves y `var/` deben quedar fuera del acceso web. El `public/index.php` actual carga `../vendor/autoload_runtime.php`: copiarlo por separado a otra raíz sin adaptar/verificar su arranque no sirve como procedimiento de despliegue.

Hostinger Web/Cloud documenta una raíz `public_html` que no puede cambiarse libremente. Se propone código en `private/aselto`, fuera de esa raíz, y solo recursos públicos/front controller adaptado en public_html. Esa disposición arrancó en un servidor PHP temporal local; las reglas de LiteSpeed y permisos reales siguen pendientes. No colocar el repositorio completo bajo public_html confiando únicamente en una redirección hacia public. [Raíz de Hostinger](https://www.hostinger.com/support/1583494-what-is-the-path-to-your-website-s-root-home-directory-and-how-to-change-it-in-hostinger/).

Los documentos y justificantes viven bajo `var/storage`, incluidos adjuntos de mensajes en `var/storage/messages`; sus rutas se resuelven desde el directorio del proyecto. El registro de seguridad vive en `var/security`, con directorio 0700 y archivos 0600, y rechaza que ese directorio sea un enlace simbólico. `var/cache` debe poder reconstruirse sin borrar almacenamiento, registros ni los contadores de límites deliberadamente.

La disposición definitiva debe conservar documentos, adjuntos, sesiones, contadores/bloqueos de seguridad, estado de alertas y claves al cambiar de versión. Un esquema de releases no puede reemplazar todo var como si fuera caché. Tampoco se debe compartir caché compilada de Symfony entre versiones incompatibles. Los puntos de montaje o rutas compartidas exactas se decidirán al conocer el hosting, sin introducir enlaces no compatibles con el código.

### Tareas de fondo

En producción manual no hace falta Mercure ni `app:quarter-updates:publish --watch`. Se ha comprobado que este comando termina sin abrir conexiones cuando está desactivado. El paquete conserva la dependencia Symfony, que no implica instalar el hub ni un daemon en el hosting.

Las tareas de seguridad sí requieren retención diaria y comprobación periódica de alertas. El envío queda desactivado hasta verificar SMTP/remitente. La [guía de seguridad](../security/security-event-logging.md) define permisos, retención de 30 días, destinatario y máximo horario. Cron y transporte efectivos todavía no se han comprobado en Hostinger.

## 3. Procedimiento que se concretará sobre el plan

[Pasos para Cloud Startup](cloud-startup-steps.md), [variables de ejemplo sin secretos reales](production.env.example), [front controller adaptado](cloud-public-index.php) y [.htaccess para validar en staging](cloud-public.htaccess). No son una automatización ni acreditan instalación en Hostinger.

Este es el orden de operaciones, **no un script para ejecutar todavía en producción**. Las rutas, usuarios y servicios no se inventan hasta tener los datos anteriores.

1. **Ensayo en un destino separado.** Preparar una base vacía y archivos sintéticos, con el mismo motor, PHP y servidor del destino; verificar compatibilidad y recuperación. No usar credenciales ni base productivas en workflows de pruebas.
2. **Paquete identificable.** Registrar SHA, versión `app:version`, composer.lock y recursos compilados. Preparar dependencias y compilar Sass antes de `asset-map:compile` con entorno prod. La versión `1.0.0-dev` no se presenta como una publicación final; seguir [versionado](../versioning.md).
3. **Configuración privada.** APP_ENV=prod y APP_DEBUG=0, APP_SECRET propio no vacío, DATABASE_URL real, DEFAULT_URI HTTPS, TRUSTED_HOSTS exacto, SMTP real, rutas JWT, claves propias y secreto Mercure. OPENAI_API_KEY opcional y privada; su ausencia conserva el modo de ejemplo, que debe ser una decisión explícita de la puesta en servicio. No copiar usuarios demo, contraseñas Docker ni JWT de desarrollo.
4. **Permisos y exposición.** Probar raíz pública, denegación de .env/config/vendor/var y ejecución solo del front controller. Verificar escrituras con el usuario PHP, límites de 8 MB por archivo y cuerpo suficiente para tres adjuntos/multipart. Revisar cabeceras en HTML, descargas, estáticos y errores, HTTPS y proxy real; no activar confianza en cualquier cabecera reenviada.
5. **Copia coherente previa.** Detener nuevas escrituras/subidas y el publicador, terminar operaciones activas y copiar base, almacenamiento privado, claves y configuración de forma coherente. Almacenar fuera de la raíz pública, restringir acceso, fijar retención y verificar una restauración en un destino separado. Un dump solo de MySQL no contiene los documentos.
6. **Migraciones controladas.** Revisar estado y SQL de las migraciones pendientes, aplicar solo las previstas al destino elegido y validar esquema. Nunca ejecutar `doctrine:schema:update --force` ni cargas demo para preparar producción. El DDL de MySQL/MariaDB puede confirmar parcialmente: no dar por supuesto que una transacción o un down revierten cualquier fallo.
7. **Activación.** Reconstruir caché del entorno con permisos correctos, verificar `app:version`, activar paquete y recursos coherentes, reanudar servicios/cron y retirar la pausa de escrituras solo tras las pruebas. Evitar dos publicaciones simultáneas. La tarea 46 definirá el mecanismo de exclusión y transferencia.
8. **Comprobación posterior.** Salud HTTPS 200 con base correcta, login web/API, rol/asesoría, subida sintética y descarga autorizada, rechazo de acceso ajeno, revisión/cifras, exportación, conversación/adjunto, suscripción privada y actualización manual. Probar envío/consumo de un enlace de cuenta en buzón de prueba autorizado; retirar sus fixtures sin afectar usuarios reales. Comprobar registros privados, retención, cron y alertas con un envío de prueba expresamente autorizado.

## 4. Recuperación y reversión

Si falla antes de migrar o escribir datos nuevos, puede ser viable reactivar el paquete anterior y reconstruir su caché, comprobando compatibilidad con la configuración. Conservar los archivos previos y el SHA del paquete para identificarlo.

Si ya se ejecutaron migraciones, **volver al código anterior no revierte el esquema ni recupera datos**. Evaluar compatibilidad hacia atrás; si no existe, mantener la pausa y recuperar la copia coherente probada de base y archivos, o aplicar una corrección hacia delante revisada. No restaurar una copia antigua sobre escrituras recientes sin una decisión explícita sobre su recuperación/pérdida. Conservar claves necesarias para leer/validar la configuración y documentar cómo renovar accesos si se rotan.

La receta exacta debe ensayarse antes de autorizar el primer despliegue y registrar duración, resultado y límites. Esta tarea todavía no acredita un RPO/RTO ni una copia restaurable del hosting.

## Estado del ensayo local previo (16/11/2026)

Ensayo local del 16/11/2026 sobre `1912def`, sin cambios de SQL/metadatos: MariaDB **11.8.9** con plataforma Doctrine `MariaDB110700Platform`, 19 migraciones y esquema correcto; **19 workflows** correctos, incluida actualización manual, SQL JSON y exportación. Paquete sin dependencias de desarrollo, 34 recursos compilados, caché prod y login/vistas manuales correctos. Restauración de demostración sintética: **13 tablas / 91 filas**, **39 archivos y claves por hash**, **33 originales legibles**. [Evidencia detallada](hostinger-evidence.json).

El ensayo utiliza PHP **8.5.12 local**, no el PHP 8.4 del hosting. La CI existente prueba PHP 8.4 con MySQL; no se presenta como una ejecución combinada de PHP 8.4/MariaDB en Hostinger. La imagen comunitaria MariaDB informa `11.8.9-MariaDB-ubu2404`, mientras el usuario comunica `11.8.9-MariaDB-log`; no se suponen iguales sus opciones ni privilegios. El servidor PHP temporal no valida .htaccess/LiteSpeed, TLS ni proxies del hosting. El helper local HTTP emitió un aviso de deprecación por su variable de cabeceras en PHP 8.5; no procede de la aplicación y no cambió los resultados de las peticiones.

- Revisado en código: PHP mínimo, MySQL 8.4 de referencia, SQL JSON/bloqueos, raíz public, rutas privadas, Mercure/publicador y cron de seguridad.
- Contrastado en documentación oficial: distinción de planes, MariaDB Web/Cloud, restricciones de raíz pública y capacidades de SSH/procesos. SSH se documenta para Premium Web o superior, con alcance restringido al directorio de usuario; no confirma el plan de esta cuenta. [SSH de Hostinger](https://www.hostinger.com/support/1583645-how-to-enable-ssh-access-in-hostinger/).
- Confirmado por el usuario: Cloud Startup, SSH y aselto.com asociado. **Pendiente de inspección/verificación:** versiones y límites reales, ruta/raíz efectiva, disposición segura de archivos, configuración/privilegios reales de MariaDB, SMTP, persistencia, ensayo final en staging de Hostinger y restauración de sus copias reales. No se han ejecutado comandos por SSH contra Hostinger.

Las fuentes de esta preparación se consultaron el 15 y 16/11/2026. La documentación comercial puede cambiar. El estado real posterior del 18/11 se recoge en [hostinger-tfm.md](hostinger-tfm.md), incluida la instalación y recuperación ejecutadas por el usuario. La tarea 45 permanece abierta por los controles restantes allí enumerados; la tarea 46 de CI/CD todavía no se ha implementado.
