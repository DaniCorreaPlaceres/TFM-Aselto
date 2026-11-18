# Rendimiento de Docker en local

## Objetivo

Reducir el tiempo de respuesta en Docker Desktop sobre Windows, manteniendo el código editable y el entorno de desarrollo de Symfony.

## Fuera de alcance

Cambios de negocio, configuración de producción, base de datos y documentos almacenados en `var/storage`.

## Criterios de aceptación

- `vendor` y `var/cache` usan volúmenes internos de Docker.
- Las dependencias se instalan desde `composer.lock` y se actualizan al cambiar este archivo.
- PHP puede escribir en la caché sin cambiar permisos de todo el proyecto.
- OPcache sigue activo y detecta cambios del código en desarrollo sin el aviso de extensión duplicada.
- `/salud`, `/` y `/entrar` responden correctamente y se comparan tres peticiones por ruta con los tiempos anteriores.
- No se eliminan volúmenes de MySQL ni documentos subidos.

## Medición inicial

Tres peticiones consecutivas sin sesión, con el entorno ya arrancado:

- `/salud`: 4,422 / 3,583 / 3,366 segundos.
- `/`: 5,823 / 4,459 / 4,495 segundos.
- `/entrar`: 6,157 / 4,642 / 4,713 segundos.

## Comprobado

- Imagen construida correctamente desde `composer.lock`, con dependencias de desarrollo y sin ejecutar scripts de aplicación durante la construcción.
- Configuración Compose válida y sintaxis del script de entrada válida.
- Contenedor PHP recreado con los volúmenes `aselto_app_vendor` y `aselto_app_cache`. Se conserva el montaje del código actual, sin modificar el trabajo del trimestre.
- `cache:warmup --env=dev` ejecutado correctamente como `www-data`.
- Invalidación del marcador de dependencias y ejecución de la entrada: Composer verifica el lock, regenera el autoload y actualiza el marcador correctamente. No se ha simulado un cambio real de versiones del lock.
- Reinicio de PHP correcto, sin el aviso de carga de OPcache. OPcache activo en FPM, con validación de fechas y `revalidate_freq=0`.
- Las doce peticiones HTTP responden 200. Primera petición a `/salud` con caché nueva: 4,930 segundos; primera portada: 1,574 segundos. Tres peticiones posteriores por ruta:
  - `/salud`: 0,601 / 0,594 / 0,551 segundos.
  - `/`: 0,967 / 0,998 / 0,941 segundos.
  - `/entrar`: 0,959 / 0,953 / 0,962 segundos.
- Reducción aproximada de las medianas en caliente: 83 % en salud, 78 % en portada y 80 % en acceso. La primera petición tras generar o invalidar caché sigue siendo más lenta.
- No se han eliminado volúmenes ni modificado MySQL o los documentos. No se ha ejecutado la suite de negocio; esta tarea cambia el entorno.

## Uso mientras sigue abierto el trimestre

La rama de esta tarea vive en `var/worktrees/docker-local-performance`, creada desde `develop`. El contenedor activo usa la imagen optimizada y el código del directorio principal. Para volver a aplicar esta configuración desde la raíz sin cambiar de rama:

```powershell
docker build -t aselto-app var/worktrees/docker-local-performance
docker compose --project-directory . -f var/worktrees/docker-local-performance/compose.yaml -p aselto up -d --no-build --no-deps app
```

Hasta integrar esta rama, `docker compose up` desde la raíz usa todavía la configuración anterior y puede quitar los montajes optimizados. Tras integrar en `develop`, vuelve a bastar `docker compose up --build`.

Los cambios de dependencias se instalan en el volumen del contenedor; el `vendor` del anfitrión no se actualiza automáticamente. No se debe usar `down -v` para actualizar el entorno: eliminaría también el volumen de la base de datos.
