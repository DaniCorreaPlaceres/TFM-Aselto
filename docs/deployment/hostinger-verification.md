# Verificaciones adicionales de Hostinger

Complementa la [primera instalación](hostinger-tfm.md) y su [ficha de evidencias](../sdd/hostinger-verification-evidence.md). Tarea 45, sin automatización de despliegue.

Actualización: el usuario acepta cerrar la preparación 45 con estas evidencias y trasladar los controles restantes a 47. No se marcan como realizados; copia externa aplazada. Se inicia por separado la [preparación del CI/CD](github-hostinger.md).

## API sin autenticar

Consultas GET reales desde el equipo del asistente: `/salud` devuelve 200; `/api/v1/me`, `/api/v1/documents`, `/api/v1/advisory/documents` y `/api/v1/conversations` devuelven 401. Las cuatro respuestas API incluyen `Cache-Control: no-store, private`. Esto acredita rechazo de acceso anónimo, no autorización con JWT ni aislamiento por rol en API. Los recorridos web anteriores no sustituyen estos últimos controles.

## Lectura desde la recuperación privada

[verify-restored-files.php](verify-restored-files.php) es un helper CLI independiente, no una ruta pública ni un comando de modificación. Subirlo únicamente a `private/incoming`. Ejecutarlo con PHP 8.4, la ruta de la aplicación recuperada y el nombre explícito de su base. El nombre destino debe terminar en `_restore` y la ruta pertenecer a una carpeta `restore-check-*`.

Antes de consultar registros exige entorno prod, debug=0, transporte `null://null`, IA sin clave y alertas desactivadas. Comprueba el destino configurado y conectado e inicia una transacción READ ONLY que termina con rollback. Usa los lectores de archivos de la aplicación para originales, justificantes y adjuntos de mensajes referenciados por la base; solo imprime fecha UTC, indicadores y contadores. Cero originales o cualquier fallo de lectura hacen terminar con código 1. No prueba renderizado de PDF ni autorización HTTP y no compara exhaustivamente el contenido de filas con la base original.

Ejemplo para la copia ya creada, sustituyendo los parámetros por los verificados en la cuenta:

```bash
/opt/alt/php84/usr/bin/php /ruta/dominio/private/incoming/verify-restored-files.php /ruta/dominio/private/restore-check-FECHA/aselto NOMBRE_BASE_restore
```

No editar la configuración de la aplicación original para pasar esta prueba. Si el helper rechaza el entorno, revisar solo la copia y no quitar las comprobaciones. No usar `phpinfo`, volcar `.env.local` ni publicar el informe junto con credenciales.

### Prueba local reproducible

El ensayo se ejecuta únicamente con la MariaDB desechable indicada, sin puertos públicos, no con la base habitual de Docker. No reutilizar su nombre para un contenedor con datos propios. Requiere que ese nombre de contenedor no exista previamente y que la red local `aselto_default` esté disponible.

```powershell
docker run -d --name aselto-restore-files-probe --network aselto_default --tmpfs /var/lib/mysql:rw,size=256m -e MARIADB_ROOT_PASSWORD=synthetic-root -e MARIADB_DATABASE=aselto_files_probe_restore -e MARIADB_USER=probe -e MARIADB_PASSWORD=synthetic-probe mariadb:11.8.9
docker compose exec -T app php tests/Integration/restored-files-workflow.php
```

El workflow comprueba que la base esté vacía, crea dos tablas y tres archivos sintéticos, comprueba lectura de original/justificante/adjunto, base incorrecta, correo activo, IA activa, alertas activas y archivo ausente; verifica que el registro sigue existiendo. Al terminar satisfactoriamente retira sus dos tablas. Ocho comprobaciones correctas con PHP 8.5.12 local y MariaDB 11.8.9. Se analizaron ambos PHP con PHPStan y se aplicó el formato PHP 8.4. No se llama a SMTP ni IA. El contenedor se retira tras comprobar que es el creado para ese ensayo; no eliminar un contenedor de otra tarea.

## Evidencias de cron

En esta ronda, el usuario confirma en hPanel `empty` para alertas de Aselto y `Archivos retirados: 0` para retención. La captura de `empty` no identifica el comando y muestra otras tareas en el fondo: su atribución a Aselto depende de la confirmación expresa del usuario. No se dispone de fecha/hora exacta de esas ejecuciones ni códigos de salida. Esto acredita la salida automática comunicada, sin probar un resumen enviado ni borrado real de registros antiguos.

En hPanel conservar salida y fecha de la tarea diaria de retención y la tarea de alertas, omitiendo datos privados. Una salida manual o una confirmación general no prueba por sí sola la ejecución automática. `empty` indica ausencia de alertas nuevas, no entrega de un correo. No producir intentos abusivos para forzar un resumen durante la demostración.

## Estado

Prueba autenticada desde Swagger: el usuario comunica 200 en POST `/api/v1/auth/login` y GET `/api/v1/me` con su cuenta de autónomo de ensayo. Contraseña y token permanecen en su navegador. No se capturó cuerpo de identidad ni se da por probado el acceso documental propio/ajeno con este resultado.

Prueba posterior de autorización: los primeros 200 de detalle/archivo se obtuvieron con un token cuyo firm_id no era nulo, por lo que no se interpretan como acceso cruzado del autónomo independiente. Repetida la autorización con la cuenta independiente y firm_id=null, el usuario confirma 404 tanto en detalle como en archivo de un documento del otro cliente vinculado. Se acredita esa denegación; siguen sin confirmación específica listado y lectura propia con el token corregido, y aislamiento API entre dos asesorías.

Contraste propio posterior: con el token independiente corregido y un documento propio, el usuario confirma 200 tanto en detalle como en archivo. Esto completa el contraste de lectura permitida/denegada para ese actor y esos documentos; no acredita por extensión la matriz API de todos los roles. El listado con el token corregido no recibe una confirmación de código independiente.

API anónima comprobada; helper probado localmente y ejecutado por el usuario en Hostinger. Informe UTC 2026-11-18T17:38:44+00:00: base coincidente, transacción de solo lectura, 1 original leído y 0 fallos. La copia no contiene justificantes ni adjuntos de mensajes: esos tipos no se dan por probados en el hosting. Salidas automáticas de ambos cron confirmadas por el usuario, sin fecha/hora exacta capturada. Quedan controles de API autenticada y los demás límites documentados. Copia externa aplazada expresamente por el usuario. La tarea 45 no se marca completa con estas evidencias parciales.
