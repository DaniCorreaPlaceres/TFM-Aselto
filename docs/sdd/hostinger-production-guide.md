# Preparación de producción en Hostinger · tarea 45

## Objetivo

Preparar instrucciones verificables de publicación y recuperación de Aselto, adaptadas a las capacidades reales del plan de Hostinger. Rama `feature/hostinger-production-guide` desde develop `d2331b5`, tras PR #62.

Reanudada desde develop `1912def` tras integrar PR #63. Destino comunicado: Cloud Startup, SSH, aselto.com, PHP 8.4 y MariaDB 11.8.9-MariaDB-log. Modo manual productivo aprobado e integrado; no se requiere hub. Primero se ensayan migraciones, esquema y workflows en MariaDB 11.8.9 desechable, sin tocar la base local habitual ni Hostinger. Cualquier incompatibilidad se documenta antes de adaptar código; no se ocultará una diferencia de esquema.

## Cambios planteados

- Inventariar requisitos del código y contrastar capacidades con fuentes oficiales y los datos del plan aportados por el usuario. Separar confirmado, simulado localmente y pendiente en hosting.
- Definir raíz pública, configuración privada, permisos, base compatible, SMTP, JWT, recursos, límites de subida, HTTPS y persistencia. No trasladar credenciales de Docker a producción.
- Resolver el alojamiento de Mercure y el trabajador de actualizaciones según el plan. No asumir Docker, root ni procesos persistentes en alojamiento compartido.
- Documentar preparación del paquete, comprobaciones, copia coherente de base/archivos/claves, migraciones, caché, activación y salud; distinguir reversión del código y recuperación de datos.
- Verificar localmente los pasos reproducibles sin tocar hosting, DNS ni enviar correo/IA reales. La conexión GitHub–Hostinger se implementa después, en tarea 46.

## Fuera de alcance

Contratar servicios, cambiar plan/dominio/DNS, desplegar o migrar datos reales, publicar etiquetas/releases, configurar CI/CD o asumir capacidades no comprobadas. No modificar la arquitectura de la memoria para ocultar una incompatibilidad del hosting.

## Criterios de aceptación

- Requisitos y capacidad del plan expresamente contrastados; cualquier dato no disponible sigue pendiente y condiciona el procedimiento.
- Instrucciones de instalación/operación/recuperación con pasos, condiciones previas y comprobaciones observables; sin secretos ni datos fiscales reales.
- Verificación local documentada sin atribuirle validez de despliegue real. No se marca tarea 45 totalmente comprobada mientras falten plan/capacidades o pruebas requeridas en hosting.
- No se inicia la automatización de tarea 46 ni se despliega producción durante la preparación.

## Comprobado

Inventario de requisitos y procedimiento preliminar redactados en [deployment/hostinger.md](../deployment/hostinger.md), sin modificar código ni ejecutar acciones en el hosting.

Revisados PHP, motor/consultas SQL, arranque público, rutas privadas, configuración, Mercure y el trabajador. Fuentes oficiales de Hostinger consultadas el 15/11/2026: Web/Cloud usa MariaDB y tiene restricciones de raíz pública/servicios; no se presupone compatibilidad con MySQL 8.4 ni un hub permanente. La guía registra condiciones previas, publicación, copia coherente y recuperación con sus límites.

El usuario confirma **Cloud Startup, SSH disponible, aselto.com asociado, PHP 8.4 y MariaDB 11.8.9-MariaDB-log**. La actualización manual ya se integra en PR #63. No se ha conectado al hosting ni se ha modificado su configuración.

Comprobado localmente el 16/11/2026:

- MariaDB 11.8.9 comunitaria en tmpfs, sin puertos publicados y con datos exclusivamente sintéticos: **19 migraciones**, esquema Doctrine correcto y plataforma MariaDB110700Platform. No hacen falta cambios SQL/metadatos.
- Los **19 workflows** pasan, con tablas de negocio vacías entre ellos; incluidos 45 controles del trimestre manual, 56 de recursos/exportación, 79 de comunicación API y el resto registrado en la [evidencia](../deployment/hostinger-evidence.json).
- Paquete en otra copia: Composer no-dev elimina 44 paquetes; requisitos locales correctos, JWT sintéticos, Sass, **34 recursos**, caché prod y versión 1.0.0-dev. PHPUnit no está disponible en ese paquete. PHP de ensayo **8.5.12**; no equivale al 8.4 del hosting.
- Kernel prod sin dev: salud/home/Swagger 200, API anónima 401, login sintético 302, Inicio/trimestre 200 en modo manual y suscripción 404.
- Código privado separado de public_html y front controller adaptado: arranque/health/CSS 200 y rutas .env/vendor/clave/almacenamiento 404. Servidor PHP local detenido tras probarlo. No valida LiteSpeed/.htaccess ni TLS de Hostinger.
- Dump de demostración sintética importado en otra base: **13 tablas y 91 filas** coinciden; **39 archivos/claves** restaurados por hash y **33 originales** legibles con el almacén de la aplicación. Sin usuarios/datos reales ni correo/IA externos.
- Se añade guía por pasos y ejemplos de variables, front controller y .htaccess para validar en staging. PHP del ejemplo pasa lint, PHPStan de aplicación correcto y formato PHP 8.4 sobre 408 archivos sin diferencias. Sin cambios de código de negocio ni migraciones.

**Pendiente en hosting:** PHP CLI efectivo/extensiones, rutas/raíz/permisos reales, LiteSpeed/rewrite/cabeceras, HTTPS/proxy/cookies, opciones/privilegios de MariaDB, SMTP, cron, capacidad y restauración en staging equivalente. La tarea 45 sigue abierta por esos controles y la tarea 46 no se inicia. config/reference.php queda fuera. El stash previo se conserva como respaldo mientras se cierra esta entrega.
