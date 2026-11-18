# CI/CD GitHub–Hostinger · tarea 46

## Objetivo

Automatizar la construcción y publicación del commit integrado en main una vez superadas las comprobaciones. Destino Cloud Startup ya ensayado, con código privado en private/aselto y raíz public_html. Rama feature/hostinger-cicd desde develop.

## Decisiones y prerrequisitos

- El usuario acepta cerrar la preparación de Hostinger (tarea 45) con las evidencias existentes y trasladar los controles restantes a la revisión final (47). La copia externa permanece aplazada, no realizada.
- Validación en PR; propuesta adoptada para esta implementación: publicar después del merge en main y de las comprobaciones satisfactorias de ese commit. No desplegar al abrir el PR ni desde develop.
- El usuario conserva el manejo del SSH y los secretos de GitHub. No se solicitan contraseñas/claves privadas en la conversación ni se modifican protecciones de ramas.
- Preparar código, pruebas y guía antes de habilitar una conexión real. Versión 1.0.0-dev conservada; no publicar etiqueta/release 1.0.0 en esta tarea.

## Cambios planteados

- A petición expresa del usuario, elaborar un capítulo explicativo para incorporar al TFM: finalidad de cada componente/configuración, decisiones, secuencia, evidencias y límites. Separarlo del manual operativo, enlazando fuentes oficiales y archivos del proyecto; no incluir credenciales ni dar por ejecutado el primer CD.

- Workflow de construcción productiva sin secretos y despliegue de main tras CI correcto, con permisos mínimos y exclusión de ejecuciones simultáneas.
- Paquete identificado por SHA/checksum, dependencias sin desarrollo y recursos compilados. Transferencia privada con clave dedicada y host key verificada, sin claves de aplicación ni datos locales.
- Instalador remoto con bloqueo propio, validación de rutas/paquete, pausa HTTP, copia previa coherente, preservación de .env.local/JWT/var, migraciones revisables, caché y comprobaciones posteriores. Sin schema:update, seeds, down automático ni suposición de publicación atómica.
- Documentar permisos, secretos/variables, preparación SSH, primera ejecución, recuperación y explicación para la defensa. No afirmar despliegue probado sin salida del hosting.
- Probar construcción e instalador en destinos sintéticos aislados, incluyendo casos de fallo y preservación de archivos privados.

## Fuera de alcance

Cambios DNS, contratación, acceso SSH directo del asistente, cambio de protecciones, cobros, copia externa aplazada, vinculación cliente-asesoría (49), publicación final 1.0.0 y auditoría completa de los pendientes trasladados a 47.

## Criterios de aceptación

- Un PR o develop no despliegan; el commit de main debe haber superado CI y ser el objetivo autorizado.
- Fallar antes de mantenimiento si faltan configuración, herramientas, integridad o permisos. Los errores tras cambios de esquema mantienen mantenimiento y requieren recuperación explícita, sin revertir datos automáticamente.
- Copia, datos persistentes, configuración y claves se conservan. Se restauran permisos 0644 del .htaccess público, evitando la incidencia ya observada.
- Solo usar datos sintéticos en pruebas locales; resultados reales, configuraciones pendientes y limitaciones se separan en la ficha y la guía.

## Comprobado

Preparación en curso. Sin secretos configurados ni despliegue automático ejecutado.

- Workflow reutilizable llamado tras los seis jobs existentes: paquete productivo en PR/develop/main; publicación solo en push main, con variable de activación y entorno production. Protecciones del remoto intactas. actionlint 1.7.12 correcto; repositorio público y rama predeterminada develop comprobados por consulta de GitHub.
- Paquete sintético construido sin dev, 7 659 archivos, extraído con validación de manifiesto. Ocho comprobaciones de archivo válidas: lectura/extracción, hash/SHA, traversal, archivo privado y symlink. La prueba conserva un nombre legítimo con puntos de Bootstrap, sin permitir segmentos ../.
- PHPUnit 461 tests/1330 aserciones y PHPStan correctos; formato PHP 8.4 aplicado. Cuatro pruebas del guard cubren peticiones activas, bloqueo exclusivo, mantenimiento/cron, migraciones y desarrollo. Sin acceso a Hostinger para ejecutar estos ensayos.
- Ensayo completo local: dominio fixture.aselto.test en Docker, PHP 8.5.12, MariaDB 11.8.9 efímera, proxy HTTPS y CA sintéticos. Instalador termina con copia, migraciones ya aplicadas, esquema correcto, caché y checks HTTP. Conserva por hash .env.local, dos claves JWT y archivo canario; conserva un usuario sintético. .htaccess público 0644; credenciales temporales de cliente SQL retiradas.
- Fallo simulado con paquete de YAML inválido tras comenzar sustitución: marca de mantenimiento y .htaccess de pausa conservados; no se ejecuta down. Recuperación explícita del YAML desde el paquete anterior y reconstrucción de caché devuelve salud OK. No se ha probado un fallo real con DDL parcialmente confirmado. El proxy local no interpreta reglas de LiteSpeed: esto sigue pendiente del primer despliegue real.
- Guía github-hostinger.md incluye entorno production limitado a main, secretos, clave dedicada/known_hosts verificado, preparación del guard, OPcache/cuota/retención, activación, primer run y recuperación. Sin configuración remota de secretos, permisos o despliegue.
- Comprobación final: 461 tests/1330 aserciones, ocho comprobaciones de paquete y PHPStan correctos. Bootstrap aselto-deployment-guard.zip con dos clases y manifiesto comparados con fuente; SHA-256 3466b43cb1cbdb1995afd286efbb86d9049f85d8b32361a3eabe86f4d361439b. Pendientes subida/instalación y prueba real del guard. Contenedores y servidor PHP del ensayo sintético retirados; los archivos de prueba no contienen datos reales. El entorno local habitual y el hosting no se sustituyen con ese ensayo.

## Incidencia durante la preparación

El usuario conecta Git nativo de Hostinger con main y public_html; confirma que solo guardó la conexión, sin desplegar ni habilitar automatización. La consulta HTTP observa portada 403 y salud/acceso 404. El listado SSH posterior muestra public_html vacío salvo .git; private/aselto permanece presente. No se atribuye sin más evidencia el vaciado a una acción concreta. Recuperar exclusivamente los archivos públicos desde la copia privada y el bootstrap/htaccess verificados, sin migraciones, sin reemplazar .env.local/JWT/var y conservando .git para diagnosticar. La integración Git nativa debe permanecer inactiva para evitar conflicto con el CI/CD por SSH.

Herramientas del hosting confirmadas por salida SSH: rsync, flock, tar, curl y mariadb-dump bajo /usr/bin; proc_open disponible en PHP CLI 8.4. Conexión indicada: 89.116.35.1:65002, usuario ya conocido. No se han recibido contraseñas ni claves.

Recuperación pública: el usuario comunica salud 200 tras reponer públicos desde private/aselto/public, bootstrap del ZIP original verificado y .htaccess del backup con 0644. Comprobación HTTP posterior independiente: portada/acceso/salud 200, API sin autenticar 401, .git/HEAD 403 y módulo mjs 200 application/javascript. La integración Git nativa queda indicada como inactiva; no se ha eliminado su metadata ni ejecutado despliegue desde ella.

Guard real instalado: el usuario confirma checksum del ZIP, verificaciones de ambos archivos y cache:clear correctos, seguido de salud 200. Prueba temporal recibida por salida SSH: /salud devuelve HTTP/2 503 el 18/11/2026 a las 18:53:04 GMT, X-Aselto-Maintenance: 1, no-store/private y Retry-After: 60. Tras retirar automáticamente el marcador, curl devuelve 200. Se acredita la pausa/reapertura real, no todavía el drenaje de una petición larga ni despliegue automático con datos.

Clave dedicada: el usuario confirma creación local de Ed25519 y autorización de su pública con restrict en authorized_keys, conservando entradas previas. La prueba desde PowerShell con IdentitiesOnly/BatchMode y esa clave contra el destino/puerto acordados devuelve ASELTO_SSH_OK. La clave privada no se comparte en la conversación. Queda guardar y verificar secretos/known_hosts del entorno GitHub; esta prueba humana no acredita aún una conexión desde Actions.

Configuración GitHub posterior verificada por API de lectura: entorno production con política custom_branch_policies y única regla de tipo branch main; existen los nombres de los dos secretos SSH y las tres variables requeridas. No se leen valores de secretos ni se modifican protecciones. El usuario introduce los secretos directamente. Se crea la variable no secreta del repositorio HOSTINGER_DEPLOY_ENABLED=false para mantener desactivada la publicación hasta revisar la promoción. Falta probar desde Actions los valores efectivos de clave/known_hosts y la transferencia, además del primer despliegue real.

Material explicativo solicitado por el usuario: deployment/configuracion-despliegue-tfm.md desarrolla finalidad y relación de PHP/Composer/MariaDB, públicos/privados, entorno, SMTP/JWT/IA, recursos y límites, seis checks CI, artefactos/hashes, SSH y known_hosts, entorno production, variables/secretos/interruptor, bloqueos, copias/migraciones/caché, cron, herramientas y límites. Incluye diagramas, introducción reutilizable y referencias. Enlazado desde índices y guías; no se ha creado PPTX, DOCX ni dado por ejecutado el CD.
