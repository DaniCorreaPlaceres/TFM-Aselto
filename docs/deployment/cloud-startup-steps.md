# Pasos de publicación en Cloud Startup

Preparación de la tarea 45. **No ejecutados en la cuenta de Hostinger.** Aplicar primero en un sitio/base de ensayo separados, con datos sintéticos. No copiar las credenciales, claves ni la base de Docker. [Requisitos y recuperación](hostinger.md).

## 1. Confirmar PHP y las rutas

El PHP web comunicado es 8.4. El PHP SSH comunicado inicialmente era 8.1.34: hay que volver a comprobarlo y localizar un binario CLI 8.4+ antes de Composer, migraciones o caché. El PHP del dominio puede diferir del usado por Composer/SSH. [Explicación oficial](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/).

Ejemplo Bash por SSH, sustituyendo las rutas por las verificadas, no por una ruta supuesta:

```bash
PHP_BIN='/ruta/verificada/al/php-compatible'
"$PHP_BIN" -v
"$PHP_BIN" -r 'if (PHP_VERSION_ID < 80400 || !extension_loaded("pdo_mysql")) { fwrite(STDERR, "Se requiere PHP 8.4+ y pdo_mysql\n"); exit(1); }'
"$PHP_BIN" -m
```

Comprobar además los requisitos productivos de composer.lock con ese mismo binario. Si Composer es un script o PHAR, invocarlo con PHP_BIN; no usar un wrapper que ejecute el PHP 8.1 anterior. No crear una página phpinfo pública. Consultar extensiones/versión web en hPanel y verificar las opciones efectivas de la web en staging.

Confirmar en hPanel la raíz real de aselto.com. Disposición propuesta dentro del directorio del dominio, no dentro de public_html:

```text
dominio/
  private/aselto/        código, vendor, .env.local, config/jwt y var
  private/backups/       copias restringidas, fuera de la web
  public_html/           SOLO contenido de public/ y front controller adaptado
```

Es una disposición de rutas de ejemplo; el identificador `/home/u...` se obtiene de la cuenta. No reemplazar un sitio existente ni enlaces previos sin revisar primero su contenido y una copia de seguridad. Usar un directorio privado **estable** conserva var entre actualizaciones. No se plantea cambiar public_html ni convertir `var/security` en un enlace simbólico.

## 2. Construir fuera del hosting

Sobre una copia limpia del commit aprobado, con PHP/extensiones compatibles:

```bash
export APP_ENV=prod APP_DEBUG=0
composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
composer check-platform-reqs --no-dev
php bin/console importmap:install --env=prod --no-debug
php bin/console sass:build --env=prod --no-debug
php bin/console asset-map:compile --env=prod --no-debug
```

El builder necesita conectividad para dependencias, módulos Importmap y compilador Sass. Los secretos del hosting no se utilizan para construir recursos. Preparar configuración sintética de build si se necesita compilar contenedor; no transferirla como configuración final. No copiar caché compilada, archivos de prueba ni claves generadas para el ensayo. En el destino se reconstruye la caché con las rutas/configuración finales.

Preparar un paquete con src, config necesario, migrations, templates, translations, bin/console, vendor productivo, composer.json/lock, symfony.lock y public con sus recursos generados. Mantener los archivos fuente de recursos que requiera la configuración de AssetMapper. Excluir `.git`, `.github`, tests, docs, herramientas de análisis, archivos locales, dumps y todo var preexistente. El archivo `.env` base puede formar parte del código; la configuración privada real lo sustituye mediante el entorno o `.env.local`.

Registrar SHA, versión (`app:version`) y hash del paquete. La versión actual es 1.0.0-dev; no publicar 1.0.0 ni crear su etiqueta sin el procedimiento aprobado de [versionado](../versioning.md).

## 3. Instalar la primera vez en staging

1. Transferir el código productivo a `private/aselto`. Crear la base de ensayo y su usuario, con los permisos necesarios para las migraciones. Confirmar `SELECT VERSION();` y el motor InnoDB.
2. Crear `.env.local` privado desde la [lista de variables de ejemplo](production.env.example), sustituyendo todos los marcadores sin registrar secretos en Git o en el historial de comandos. El ejemplo no se puede utilizar tal cual. Usar `serverVersion=11.8.9-MariaDB` para la versión comunicada, no 8.4. Codificar los caracteres reservados de usuario/contraseña en el DSN.
3. Para staging, cambiar DEFAULT_URI y TRUSTED_HOSTS al host HTTPS de staging. Crear APP_SECRET y JWT_PASSPHRASE propios. Generar claves con `"$PHP_BIN" bin/console lexik:jwt:generate-keypair --env=prod --no-debug` **solo si no existen**. No reemplazar claves en actualizaciones. Restringir lectura de claves/configuración a la cuenta que ejecuta PHP.
4. Crear var/cache, var/log y var/storage con permisos mínimos para el usuario PHP; var/security debe ser 0700 y sus archivos 0600. No usar 0777. Confirmar permisos efectivos con PHP/SSH y que otro sitio de la cuenta no tiene acceso web a esas rutas. Persistir documentos, adjuntos, sesiones, límites/bloqueos y estado de alertas entre versiones.
5. Copiar solo el contenido público a public_html. Colocar como index.php el [front controller adaptado](cloud-public-index.php), que carga `../private/aselto/vendor/autoload_runtime.php`; ajustar esa única ruta si se elige otro directorio. No copiar el index estándar sin adaptar su ruta. Mantener la copia privada del paquete coherente con sus recursos públicos.
6. Preparar `.htaccess` desde el [ejemplo](cloud-public.htaccess) y comprobar en staging que LiteSpeed acepta Options, reglas de rewrite, FilesMatch y headers. El ejemplo **no se ha ejecutado en LiteSpeed**; el ensayo local usa servidor PHP y no valida .htaccess. No sustituir la configuración existente del hosting a ciegas.
7. Ajustar PHP web/servidor para admitir archivos de 8 MB y tres adjuntos multipart dentro del límite de cuerpo, sin eliminar los límites de aplicación. La referencia Docker es upload_max_filesize=16M y post_max_size=32M; se deben contrastar con los límites de Cloud, memoria y tiempo. No activar caché de página/CDN sobre áreas autenticadas o enlaces privados.
8. Revisar migraciones y aplicar sobre la base vacía de staging:

```bash
cd /ruta/real/dominio/private/aselto
"$PHP_BIN" bin/console doctrine:migrations:status --env=prod --no-debug
"$PHP_BIN" bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction
"$PHP_BIN" bin/console doctrine:schema:validate --env=prod --no-debug
"$PHP_BIN" bin/console cache:clear --env=prod --no-debug
"$PHP_BIN" bin/console app:version --env=prod --no-debug
```

No ejecutar `schema:update --force` ni seeds demo en producción. No habilitar tráfico de usuarios durante una instalación parcial. Reconstruir caché después de colocar el paquete en sus rutas definitivas, incluso si ya se calentó durante el build.

## 4. Cron, correo y activación

El modo manual productivo no requiere Mercure ni cron de notificaciones financieras. Para seguridad, crear tareas personalizadas con el PHP_BIN y ruta privada verificados, en UTC: retención diaria (`app:security retention`) y alertas (`app:security alerts --send`, por ejemplo cada 15 minutos). El estado limita los intentos a uno por hora. No publicar un endpoint HTTP para ejecutar cron. [Configuración de cron en Hostinger](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

Usar SMTP autorizado y remitente verificado para los enlaces de cuenta. Mantener SECURITY_ALERT_EMAIL_ENABLED=0 hasta comprobar transporte, remitente, destinatario y estado privado; después activarlo y autorizar un envío de ensayo. OPENAI_API_KEY vacía significa modo de ejemplo, no extracción real: decidirlo explícitamente antes de activar altas. Una clave real requiere su configuración privada y una prueba autorizada, sin usar documentos fiscales reales.

Comprobar en staging: HTTPS, redirección a host canónico, salud, login web/API, cliente y asesoría, actualización manual, subidas/descargas y acceso ajeno rechazado, exportación CSV/XLSX, mensajes/adjuntos y enlaces de cuenta. Confirmar que no se abren SSE ni hay intentos al hub. Probar CSP y cabeceras de estáticos/errores y comprobar que no se pueden descargar .env, config, vendor, var o backups. Revisar cookies seguras y ausencia de caché compartida en contenido privado.

No habilitar HSTS con subdominios o preload sin verificar todos sus requisitos. No confiar en cualquier proxy para obtener HTTPS o IP; identificar el proxy real si existe y comprobar cómo entrega las cabeceras.

## 5. Actualizaciones y recuperación

Antes de actualizar: modo mantenimiento efectivo en servidor, pausa de nuevas escrituras y cron, y espera a operaciones activas. Realizar una copia **coherente** de base, archivos, claves y configuración en la zona privada, con permisos restringidos. Revisar retención y espacio antes de copiar. Conservar el paquete anterior y su SHA.

El dump se hace con la herramienta del motor y permisos disponibles. Si el usuario no tiene privilegios para rutinas/eventos que no utiliza esta aplicación, adaptar las opciones y verificar el resultado; no concluir que hay copia correcta solo por existir un archivo. El ensayo local de esta tarea usa mariadb-dump en un contenedor controlado; no acredita permisos de hPanel.

Validar restauración en una **base separada** y almacenamiento separado: importar dump, copiar almacenamiento/claves/configuración y comprobar filas, archivos y descargas. No sobrescribir producción para probar una copia. La evidencia local incluye 13 tablas/91 filas comparadas y 39 archivos/claves verificados por hash, con 33 originales legibles tras restauración.

Transferir la actualización sin borrar var, claves, .env.local ni backups. Al cambiar el código fijo, el mantenimiento debe cubrir también la copia de recursos públicos y migración: esta disposición no es una publicación atómica entre dos carpetas. Aplicar solo migraciones revisadas, reconstruir caché y repetir salud/versionado y recorridos antes de reabrir. La exclusión automatizada y el mecanismo exacto de transferencia corresponden a tarea 46.

Un fallo anterior a cambios de esquema/datos puede permitir reponer el paquete anterior y su caché, tras revisar compatibilidad. Con migraciones o nuevas escrituras, restaurar código **no** revierte datos: elegir corrección hacia delante o recuperación coherente bajo mantenimiento, evaluando explícitamente las escrituras posteriores a la copia. No hacer down automático ni prometer RPO/RTO sin ensayo y medición.
