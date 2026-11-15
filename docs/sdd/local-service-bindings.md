# Publicación local de servicios · tarea 43 / F12

## Objetivo

Restringir a IPv4 loopback los puertos publicados por Docker Compose de desarrollo. Rama `feature/local-service-bindings` desde develop `50b0df9`, tras integrar F11 (PR #57).

## Cambios planteados

- Publicar web 8080, MySQL 3306, Mailpit 8025/1025 y Mercure 9080 únicamente en `127.0.0.1`, conservando números y acceso local.
- Conservar conexiones entre contenedores, credenciales de desarrollo, volúmenes y datos. No publicar PHP-FPM ni servicios auxiliares.
- Comprobar configuración efectiva y enlaces de los contenedores, salud web/base de datos, Mailpit y Mercure, y documentar cómo aplicar el cambio.
- Aclarar que el acceso desde móvil u otro equipo deja de estar disponible con esta configuración. No abrir interfaces automáticamente para una demostración.

## Fuera de alcance

Producción/Hostinger, cambios de firewall o de protección de ramas, acceso remoto/LAN, HTTPS, rotación de credenciales, otros proyectos Docker y puntuación histórica de la auditoría. No afirmar inaccesibilidad desde una segunda máquina sin probarla.

## Criterios de aceptación

- Todos los puertos publicados del Compose efectivo tienen `host_ip=127.0.0.1`; no aparecen enlaces `0.0.0.0` ni `::` para estos contenedores.
- Tras recrear los servicios afectados, se conserva el volumen de MySQL y la aplicación sigue respondiendo a través de localhost y 127.0.0.1. MySQL continúa accesible desde la aplicación por su nombre interno.
- Mailpit y Mercure responden localmente y se conserva el proxy Mercure del mismo origen.
- Documentación y seguimiento reflejan las comprobaciones y sus límites. La prueba de otra máquina y la configuración productiva permanecen pendientes.

## Comprobado

Implementado y comprobado el 15/11/2026 con Docker Engine 29.2.1, Docker Desktop y contenedores Linux.

- `docker compose config --format json`: cinco publicaciones, todas `host_ip=127.0.0.1`. Inspección de los contenedores en ejecución confirma los mismos enlaces, sin `0.0.0.0` ni `::`.
- Servicios afectados recreados sin borrar volúmenes; MySQL conserva `aselto_database_data`. Los siete servicios quedan en ejecución; MySQL, Mailpit y Mercure están sanos.
- Salud por localhost y 127.0.0.1: 200 y base de datos correcta. Home, Swagger y Mailpit: 200. SMTP local: saludo 220 sin correo. Mercure directo y por proxy: 400 esperado sin tema.
- Workflow de trimestre en vivo: **38 comprobaciones** con SSE real. Fixtures sintéticos eliminados y comparación de datos fiscales existentes correcta.
- Validación YAML de Compose y revisión de diferencias correctas. No se modifica código PHP/JS ni se añaden pruebas unitarias para cinco enlaces de puertos; no se ha repetido la batería PHPUnit completa.

[Guía](../security/local-service-bindings.md) y [evidencia](../security/evidence/f12-local-service-bindings.json). Pendientes: prueba desde otra máquina autorizada, firewall/rutas externas, producción/Hostinger y estabilidad SSE prolongada. CFG-05 productivo y puntuación histórica permanecen sin cambios. `config/reference.php` contiene cambios generados previos y queda fuera de esta tarea.
