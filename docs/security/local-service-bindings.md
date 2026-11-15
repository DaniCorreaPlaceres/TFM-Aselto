# Servicios de desarrollo limitados al equipo local · F12

El Compose de desarrollo publica los cinco puertos únicamente en `127.0.0.1`: web 8080, MySQL 3306, Mailpit 8025 y SMTP 1025, Mercure 9080. Se conservan sus números para los accesos y herramientas locales existentes. No se publican PHP-FPM, el compilador de estilos ni el trabajador de actualizaciones.

La aplicación sigue conectando con `database:3306`, `mailpit:1025` y `mercure:80` dentro de la red Docker. Las suscripciones web conservan el proxy `/.well-known/mercure` del mismo origen. No cambian credenciales, migraciones, esquema, volúmenes ni documentos.

## Aplicar a un entorno preparado

Desde la raíz del proyecto:

```powershell
docker compose config --quiet
docker compose up -d --no-build web database mailpit mercure
docker compose ps
```

Compose recrea los servicios afectados y conserva los volúmenes. Puede interrumpir brevemente las conexiones existentes a MySQL, SMTP y Mercure; esperar a que MySQL esté sano y recargar la web. No usar `down --volumes`: el cambio de puertos no requiere borrar datos ni volver a cargar la demostración.

Para una instalación nueva, seguir la [guía de instalación local](../local-installation.md), que también construye la aplicación y prepara sus dependencias.

## Accesos y alcance

Desde el propio equipo: [web](http://localhost:8080), [salud](http://localhost:8080/salud), [Swagger UI](http://localhost:8080/api/docs) y [Mailpit](http://localhost:8025). También se puede usar `127.0.0.1`. Se enlaza solo IPv4: no hay publicación en `[::1]`; si el sistema resuelve localhost exclusivamente a IPv6, usar la dirección IPv4 explícita.

El Compose ya no prepara acceso desde el móvil u otro equipo por la IP de red del ordenador. Una demostración remota requiere un entorno específico y sus condiciones de acceso; esta tarea no abre interfaces, túneles ni reglas de firewall.

Esto limita los enlaces publicados por este proyecto. No acredita la configuración de otros proyectos, rutas directas a contenedores, proxies adicionales, el firewall del equipo ni Hostinger. La prueba desde una segunda máquina autorizada sigue pendiente. La documentación oficial de [publicación de puertos de Docker](https://docs.docker.com/engine/network/port-publishing/) describe el enlace explícito a loopback. La verificación local se hizo con Docker Engine **29.2.1**; no se extrapola a versiones antiguas ni a redes con configuración distinta.

## Verificación local

El Compose efectivo y `docker inspect` de los contenedores de este proyecto muestran los cinco enlaces en `127.0.0.1`, sin publicaciones `0.0.0.0` ni `::`. MySQL conserva el volumen `aselto_database_data` tras recrearse.

- Salud por localhost y 127.0.0.1: HTTP 200, aplicación/base de datos correctas.
- Home, Swagger y Mailpit: HTTP 200.
- SMTP local: saludo 220, sin enviar correo.
- Mercure directo y a través del proxy web: HTTP 400 por faltar el parámetro de tema, respuesta esperada del hub y sin abrir una suscripción permanente.
- Los siete servicios quedan en ejecución; MySQL, Mailpit y Mercure están sanos.
- Compose válido y validación YAML correcta.
- Trimestre en vivo: **38 comprobaciones** con entrega SSE real a través de Mercure. Fixtures sintéticos eliminados y comparación de los datos fiscales existentes correcta.

Estos accesos se probaron realmente tras recrear los contenedores. No se prueba entrega real de correo ni estabilidad SSE prolongada/reconexiones de red. Evidencias adicionales en la [ficha](../sdd/local-service-bindings.md) y el [registro de ejecución](evidence/f12-local-service-bindings.json). La puntuación histórica de la auditoría no cambia; CFG-05 de producción sigue sin comprobar.
