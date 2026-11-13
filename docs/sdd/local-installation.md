# Instalación local reproducible

## Objetivo

Documentar y verificar el arranque desde una copia limpia del repositorio, sin depender de archivos privados, claves, dependencias ni datos de la instalación habitual.

## Fuera de alcance

Producción, Hostinger, CI/CD de publicación, nuevas funciones y cambios en los datos o claves del entorno actual.

## Criterios de aceptación

- Copia de archivos versionados, proyecto Compose, volúmenes y puertos de prueba propios.
- Documentar configuración privada local, secretos de desarrollo, claves JWT, dependencias, recursos, migraciones y servicios en orden reproducible.
- Verificar salud, acceso web/API, demostración, correo y recursos; identificar lo no comprobado.
- Registrar problemas reales encontrados y su solución, sin dar por verificadas otras plataformas.
- No sobrescribir claves ni borrar volúmenes o archivos de otro entorno. Retirar únicamente recursos temporales identificados de la prueba.

## Comprobado

- Guía `docs/local-installation.md`, enlazada desde README e índice. Tarea 29 marcada como realizada con límites explícitos.
- Copia de archivos versionados de `1c65021` bajo `var/install-check/source`, sin configuración privada, claves ni dependencias del entorno habitual. Proyecto `aselto-install-check`, tres volúmenes y puertos propios; configuración privada generada para la prueba.
- Detectados y corregidos permisos del primer arranque: `www-data` necesita escribir en `var` para descargar Sass y en las carpetas de registros y almacenamiento. Los scripts PHP/Docker quedan fijados a LF; se normalizó `styles.sh`, cuyo arranque fallaba con CRLF.
- Construcción, dependencias, descarga y compilación Sass, Importmap, generación JWT y 14 migraciones correctas, cero migraciones pendientes. Demostración creada y repetida conservando sus datos. Compilación AssetMapper: 25 recursos. Siete servicios activos tras preparar el esquema; sintaxis de ambos scripts shell correcta.
- HTTP: salud, home, login, Swagger, OpenAPI y módulo PDF.js con 200. Login de admin hasta `/cuenta`, recursos enlazados CSS/JS con 200. JWT del cliente independiente y consultas de identidad, documentos y trimestre con 200. Registro ficticio, un correo recibido en Mailpit y enlace con la URL aislada.
- `doctrine:schema:validate`: mapeos correctos, esquema no sincronizado según la comparación ORM. Diagnóstico de solo lectura detecta índices/tipos y propone eliminar `quarter_update_outbox`, gestionada con SQL directo. No se ejecutó ese SQL. Revisión específica pendiente; no se declara validación de esquema correcta.
- Verificado en Windows/Docker Desktop con contenedores Linux/PHP 8.5, aprovechando capas disponibles. No verificado Linux/macOS nativos, ARM, IA real, SMTP externo, activación del enlace, interacción visual completa ni Mercure de extremo a extremo.
- CSS compilado servido con 200 y enlaces locales de la documentación correctos. Retirados los contenedores, red, tres volúmenes y carpeta de la prueba; los siete servicios del entorno habitual siguen activos. `config/reference.php` se mantiene fuera de esta tarea.
