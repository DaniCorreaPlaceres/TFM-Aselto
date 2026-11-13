# Instalación local desde cero

Guía de desarrollo para Windows con PowerShell y Docker Desktop usando contenedores Linux. Ejecutar los bloques en orden desde la raíz de una **copia nueva** del repositorio. No requiere PHP, Composer ni MySQL en Windows. Git, Docker Compose e Internet sí son necesarios: se descargan imágenes, dependencias, dos paquetes de Importmap y el compilador Sass.

No es una guía de producción. Las contraseñas y la clave compartida de Mercure de `compose.yaml` son de desarrollo.

## 1. Obtener el proyecto

```powershell
git clone https://github.com/DaniCorreaPlaceres/TFM-Aselto.git
Set-Location TFM-Aselto
git switch develop
docker version
docker compose version
```

Si el repositorio requiere identificación, Git debe tener acceso a GitHub. Utilizar una carpeta distinta de cualquier instalación existente. Puertos necesarios: 8080 (web), 3306 (MySQL), 8025 y 1025 (Mailpit), 9080 (Mercure). Detener el servicio que los ocupa o preparar otra configuración antes de continuar.

## 2. Configuración privada

En una copia nueva, crear `.env.local` con valores propios. Este bloque se detiene si el archivo ya existe: no debe reemplazar la configuración de un entorno preparado.

```powershell
if (Test-Path -LiteralPath .env.local) { throw 'Ya existe .env.local; revisar su configuración sin sobrescribirla.' }
$appSecret = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
$jwtPassphrase = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
[IO.File]::WriteAllText((Join-Path $PWD '.env.local'), "APP_SECRET=$appSecret`nJWT_PASSPHRASE=$jwtPassphrase`nOPENAI_API_KEY=`n")
```

La clave de OpenAI vacía permite probar la aplicación con la propuesta sintética de desarrollo, sin llamadas a IA externa. Para extracción real se configura una clave privada propia y se revisa el modelo indicado en `.env`. La persona sigue revisando y confirmando los datos.

Compose ya proporciona las direcciones internas de MySQL, Mailpit y Mercure. Dentro de los contenedores no se usa `localhost` para conectar con otros servicios. Las variables de Compose tienen prioridad sobre `.env.local`.

## 3. Arrancar los servicios básicos

```powershell
docker compose up -d --build app web mercure
docker compose ps
docker compose logs --tail 30 app
```

Esperar a que MySQL esté `healthy` y `app` permanezca activo. La primera construcción instala las dependencias de `composer.lock` en la imagen; el volumen `app_vendor` las conserva. El arranque comprueba el hash del archivo de dependencias y las actualiza si cambia. No se necesita ejecutar Composer en Windows.

El primer arranque descarga y compila Sass. El script prepara los permisos de `var`, caché, registros y almacenamiento para `www-data`. Los scripts de `docker/php` deben conservar saltos de línea LF, fijados en `.gitattributes`.

## 4. Recursos, claves y base de datos

Ejecutar cada comando solo si el anterior termina correctamente:

```powershell
docker compose exec -T app php bin/console importmap:install
docker compose exec -T --user www-data app php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec -T --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T --user www-data app php bin/console sass:build
docker compose exec -T --user www-data app php bin/console doctrine:migrations:status
```

Importmap descarga los módulos JavaScript a `assets/vendor`: la instalación de Composer del contenedor omite sus scripts y no hace este paso automáticamente. JWT genera `config/jwt/private.pem` y `public.pem`, sin reemplazar claves existentes. No compartir ni versionar la clave privada o `.env.local`; cambiar la contraseña de unas claves existentes no las vuelve a cifrar.

MySQL crea la base vacía con las credenciales de Compose. Las migraciones crean su estructura: al terminar, el estado debe mostrar cero migraciones nuevas. No sustituirlas por `doctrine:schema:update --force`.

## 5. Demostración y servicios auxiliares

```powershell
docker compose exec -T --user www-data app php bin/console app:seed-demo
docker compose up -d --build styles quarter_updates
docker compose ps
```

El comando de demostración solo funciona en desarrollo. Crea datos ficticios de dos asesorías y un cliente independiente. Repetirlo conserva datos, archivos y contraseñas existentes; no es un reinicio. Accesos y recorridos en [demo.md](demo.md). Por ejemplo: `demo-admin-sol@example.test`, contraseña inicial `aselto-demo`.

`styles` recompila al modificar SCSS. `quarter_updates` publica la cola de actualizaciones del trimestre; se arranca después de las migraciones. No se necesita un consumidor genérico de Messenger para estos recorridos.

## 6. Comprobar la instalación

- Abrir [salud](http://localhost:8080/salud), [home](http://localhost:8080) y [Swagger UI](http://localhost:8080/api/docs).
- Entrar con el admin de demostración y consultar su cuenta. Probar también `demo-independiente@example.test` con la misma contraseña inicial.
- Abrir [Mailpit](http://localhost:8025). Registrar una cuenta ficticia nueva desde la web debe producir un correo con el enlace para crear la contraseña. No se envía correo real fuera del entorno.
- Comprobar que la página tiene estilos y que el navegador carga los recursos sin errores. Los PDF de demostración se consultan desde un documento autorizado.

Para verificar JWT sin mostrar ni guardar el token:

```powershell
$auth = Invoke-RestMethod 'http://localhost:8080/api/v1/auth/login' -Method Post -ContentType 'application/json' -Body '{"email":"demo-independiente@example.test","password":"aselto-demo"}'
Invoke-RestMethod 'http://localhost:8080/api/v1/me' -Headers @{Authorization="Bearer $($auth.token)"}
```

La referencia de endpoints está en [api/README.md](api/README.md). Las pruebas automatizadas y sus requisitos se describen en el [README principal](../README.md); no ejecutarlas contra una base con datos importantes sin revisar su aislamiento.

## Uso diario y persistencia

```powershell
docker compose up -d --build
docker compose stop
```

El primer comando vuelve a arrancar un entorno **ya preparado**; no reemplaza los pasos iniciales. `stop` conserva los servicios y sus datos. `docker compose down` retira contenedores y red, conservando los volúmenes si no se añade `--volumes`.

La base de datos vive en el volumen `database_data`; las dependencias y caché en `app_vendor` y `app_cache`. Documentos y adjuntos viven en `var/storage`, dentro de la copia local. Las claves JWT y `.env.local` también están en esa copia. Una copia de la base no contiene sus documentos: todos estos elementos deben considerarse al conservar un entorno. No borrar volúmenes o carpetas para resolver un error sin identificar antes qué datos contienen.

En desarrollo AssetMapper sirve los recursos sin compilación estática. `asset-map:compile` se utiliza para comprobar la generación del paquete, pero deja `public/assets` con recursos congelados: si se ejecuta localmente, retirar después **solo esa carpeta generada** para volver al desarrollo dinámico.

## Problemas conocidos

- **Puerto ocupado:** revisar `docker compose ps` y los otros proyectos. Para dos instalaciones simultáneas hay que usar carpetas, nombre de proyecto (`-p`) y puertos distintos. Cambiar también `DEFAULT_URI`, `MERCURE_PUBLIC_URL`, `resource_identifier` y `cors_origins` al host y puerto públicos elegidos; conservar los puertos internos de MySQL y SMTP. Una copia adicional debe tener sus propias claves y archivos privados.
- **`Illegal option` en `styles.sh`:** comprobar los saltos LF y obtener la versión actualizada del script y `.gitattributes`. Reiniciar `styles` después de corregirlo.
- **Sass no se descarga:** comprobar Internet hacia GitHub y los registros de `app`. Si aparece `Permission denied`, reconstruir con el script de entrada actualizado; no ejecutar todos los comandos Symfony como root para ocultarlo.
- **No se encuentra Stimulus o Turbo:** ejecutar `importmap:install` y comprobar acceso a jsDelivr. Los paquetes se sirven localmente después de descargarse.
- **API no puede firmar tokens:** comprobar las claves JWT y que se conserva la contraseña con la que se generaron.
- **Trimestres sin actualización automática:** revisar `quarter_updates`, Mercure y las URL públicas. La actualización manual sigue disponible.
- **Validación de esquema de Doctrine:** en esta versión `doctrine:schema:validate` valida los mapeos, pero informa diferencias de esquema tras las 14 migraciones. El diagnóstico de solo lectura propone incluso borrar `quarter_update_outbox`, que utiliza SQL directo. También detecta índices y tipos distintos. Queda pendiente revisar esa comparación en una tarea específica; no ejecutar automáticamente el SQL propuesto. Las migraciones y los recorridos HTTP comprobados sí terminaron correctamente.

## Alcance de la verificación

Prueba realizada el 12 de noviembre de 2026 en Windows con Docker Desktop, contenedores Linux y PHP 8.5. Se extrajeron únicamente los archivos versionados de `1c65021`, aplicando las correcciones de arranque de esta tarea. Sin `vendor`, claves, configuración privada ni base de datos de la instalación habitual. Proyecto Compose separado `aselto-install-check`, volúmenes propios y puertos 18080, 13306, 18025, 11025 y 19080. Se aprovecharon imágenes/capas de Docker disponibles; no se probó una máquina sin caché de imágenes.

Comprobado: construcción, descarga de dependencias y Sass, Importmap, claves nuevas, 14 migraciones sin pendientes, demostración y repetición sin reinicio, compilación de 25 recursos, siete servicios activos, salud/home/login/Swagger/OpenAPI/PDF.js por HTTP, acceso web hasta `/cuenta`, recursos CSS y JavaScript con respuesta 200, JWT y consultas de identidad/documentos/trimestre con respuesta 200. Registro ficticio con recepción de un correo en Mailpit y enlace con la URL de la copia aislada.

No se ha comprobado en esta tarea: instalación nativa en Linux/macOS, equipo ARM, extracción con IA real, entrega de correo externo, interacción visual completa, apertura del enlace de activación ni suscripción Mercure de extremo a extremo. El arranque del publicador y del hub no equivale a esa prueba. La diferencia de esquema indicada arriba permanece registrada.
