# Cifras actualizadas manualmente para Cloud Startup

## Objetivo

Adaptar la primera publicación a la decisión del usuario: actualizar al cargar la página o pulsar Actualizar, sin necesitar hub Mercure ni publicador permanente. Rama `feature/manual-quarter-refresh` desde develop `d2331b5`.

## Cambios planteados

- Modo manual por defecto en producción, conservando el modo en vivo existente como opción explícita para desarrollo y pruebas.
- El navegador no solicita autorización, abre SSE ni programa renovaciones automáticas en modo manual. Conserva protección de formularios editados, errores y permisos al actualizar el fragmento.
- Sin generar ni publicar notificaciones nuevas cuando está desactivado; cerrar la ruta de autorización. No borrar histórico/outbox existente ni cambiar cálculos, cierres o API fiscal.
- Verificar ambos modos con pruebas frontend, comprobaciones del backend sin hub y regresiones existentes.
- Registrar PHP web 8.3 y CLI 8.1.34 como datos del usuario: ambos inferiores al requisito 8.4. Compatibilidad MariaDB pendiente de versión exacta. No desplegar ni rebajar PHP/dependencias.

## Fuera de alcance

Desplegar, contratar servicios, cambiar el hosting, migrar a MariaDB sin verificar su versión, retirar historial/esquema y automatizar CI/CD.

## Criterios de aceptación

- Modo manual productivo no depende de Mercure/publicador; cargar y actualizar consulta cifras con los mismos permisos.
- No hay tráfico SSE, renovación o actualización automática del navegador en ese modo.
- Formularios sucios se conservan; permisos/sesión revocados eliminan el contenido al actualizar; un fallo conserva las últimas cifras con aviso.
- Modo en vivo existente disponible para pruebas; solo se documentan comprobaciones realizadas. Publicación pendiente de PHP compatible y MariaDB real.

## Comprobado

Implementado en la rama indicada. [Guía y límites](../manual-quarter-refresh.md).

- PHPUnit **452 pruebas / 1.295 aserciones**; las tres pruebas nuevas comprueban ausencia de I/O, credenciales y bucle de publicación cuando está desactivado.
- Frontend del trimestre **14 pruebas** correctas, incluidas ausencia de temporizadores/tráfico automático, petición explícita con actividad manual y revocación de permisos.
- Workflow trimestral configurado manualmente: **45 comprobaciones** correctas. El override de Twig en ese kernel de pruebas debe acompañar al parámetro, ya que el global se resuelve al compilar configuración. El parámetro y Twig reales de producción se compilan en false mediante when@prod.
- Workflow de desarrollo en vivo: **38 comprobaciones** correctas, con SSE real y limpieza de fixtures; datos existentes conservados.
- Caché productiva local reconstruida y publicador ejecutado con `--watch`, MySQL y hub inaccesibles: salida correcta inmediata, sin bucle. Parámetro prod false verificado.
- PHPStan y contenedor correctos; **64 Twig / 24 YAML** válidos. Formato PHP 8.4 sobre copia LF: **407 archivos**, sin diferencias.

No desplegado ni revisado visualmente en navegador. El código de cálculos, permisos y cierres se conserva. Sin migración ni borrado del outbox/histórico. config/reference.php queda fuera.

El usuario actualiza los datos del destino: PHP 8.4 y MariaDB **11.8.9-MariaDB-log**. Es información aportada, pendiente de comprobar PHP CLI efectivo y compatibilidad de MariaDB en ensayo separado. La tarea 45 permanece abierta; el borrador previo de la guía de Hostinger está conservado aparte y se retomará tras integrar esta adaptación. No se ha modificado el servidor.
