# Actualización en vivo del trimestre

## Objetivo

Actualizar cifras, pendientes, cierres e histórico en Inicio y en las vistas trimestrales abiertas del cliente y de la asesoría, usando Mercure y conservando los formularios que se estén editando.

## Fuera de alcance

Chat en vivo, correo, API, cambios de asesoría, rediseño general y modificación de las reglas de cálculo o cierre del trimestre.

## Cambios previstos

- Notificaciones privadas por usuario, sin importes, documentos ni datos fiscales en el evento. El navegador solicita de nuevo un fragmento de la vista; el servidor vuelve a comprobar sus permisos y usa los mismos casos de uso.
- Solo usuarios activos cliente, admin y gestor reciben autorización para su tema exacto. No se aceptan temas elegidos por el navegador. Credenciales de suscripción de corta duración, renovadas comprobando la sesión.
- Mercure se sirve por una ruta del mismo origen en Nginx para evitar problemas de dominio entre localhost y 127.0.0.1. Las actualizaciones fiscales siempre son privadas.
- Las escrituras de documentos y cierres registran una notificación pendiente en la misma transacción. Se publica tras confirmar; un proceso local reintenta las pendientes si el hub falla. La caída del hub no deshace un registro ni obliga a repetir una confirmación.
- Solo se sustituyen fragmentos de resumen. El selector del periodo permanece fuera del fragmento; los formularios documentales y de mensajes no se reemplazan. Si hay una edición dentro del fragmento, se pospone la actualización y se informa.

## Criterios de aceptación

- Confirmación, validación, corrección, subida tardía y peticiones de justificante actualizan cifras o pendientes de las vistas abiertas. Cambiar una fecha actualiza tanto el periodo de origen como el de destino al volver a consultar las vistas.
- Cerrar y reabrir actualiza el estado y el histórico. Un trimestre cerrado sigue mostrando su instantánea; las subidas posteriores aparecen solo entre los pendientes actuales.
- Un evento solo indica que hay cambios. Cada usuario recibe exclusivamente las notificaciones que le corresponden; otra asesoría, otro cliente, superadmin y usuarios sin sesión no obtienen autorización para temas ajenos.
- El servidor vuelve a comprobar acceso al recargar cada fragmento. Si se pierde el permiso o la sesión, se detiene la conexión y se retira el contenido financiero de esa vista.
- Las transacciones fallidas no publican. La publicación no ocurre antes del commit; las pendientes se reintentan sin perder cambios concurrentes. No se generan avisos fiscales por lectura o escritura de mensajes.
- Reconexión y renovación recuperan el estado actual sin duplicar movimientos ni inventar ceros. Si falla la conexión se conservan las últimas cifras y se explica cómo actualizar manualmente.
- El selector del trimestre y los datos que se estén escribiendo se conservan. Las actualizaciones no recargan toda la página ni envían formularios automáticamente.
- Pruebas de permisos, entrega privada real del hub, persistencia y reintento, rollback, regresión de trimestre y mensajería y funcionamiento del navegador con dos sesiones o vistas.

## Referencia técnica

[Documentación oficial de Symfony sobre Mercure](https://symfony.com/doc/current/mercure.html): actualizaciones privadas y suscripciones autenticadas mediante cookie. [Matchers del protocolo actual](https://mercure.rocks/docs/concepts/topics-and-matchers): suscripción exacta mediante `match`.

## Comprobado

- Rama `feature/live-quarter`, creada desde `develop`.
- PHPUnit: **246 pruebas y 633 aserciones** correctas. Pruebas del navegador simulado: **9**, sobre peticiones de fragmentos, desconexión, pérdida de permiso y sesión, conservación de ediciones, respuestas tardías y páginas ocultas.
- Hub real Mercure 1.1.6, MySQL y kernel HTTP: **36 comprobaciones** correctas. Incluyen rollback sin aviso, publicación posterior al commit, caída simulada y reintento, revisión concurrente que sigue pendiente, destinatarios autorizados, evento privado sin cifras, cookies de un minuto y tema elegido por el servidor, aislamiento real con dos credenciales de suscripción, fragmentos protegidos, confirmación, validación, cierre y reapertura y retirada de cookie al salir.
- La entrega privada se probó pasando por Nginx con el hub real, no con un sustituto del transporte. El tema exacto usa `match`, requerido por el protocolo 1.0 del hub instalado. La imagen se fija al digest de la versión comprobada.
- Regresión trimestral: **43 comprobaciones** correctas. Regresión de mensajería: **37**, correctas. Las pruebas retiran sus datos y comprueban que documentos y cierres existentes no cambian.
- Revisión visual de escritorio en dos vistas abiertas: Inicio e Ingresos y gastos pasan de 0 a 123 € al confirmar un registro ficticio, el pendiente desaparece y el año escrito en el selector se conserva. Cierre y reapertura actualizan ambas vistas y el histórico sin recarga. La cuenta y sus datos ficticios se eliminaron al terminar.
- PHPStan nivel 6, contenedor y OpenAPI correctos; 42 plantillas Twig válidas. Formato Symfony comprobado en las copias LF de los 13 archivos PHP de la tarea y `git diff --check` correcto.
- Migración local aplicada; proceso `quarter_updates` activo y Nginx recargado. Las comprobaciones de JavaScript quedan incluidas en el trabajo PHPUnit del PR; todavía no se han ejecutado en GitHub para esta rama.

## Cómo probar

1. Abrir Inicio y el trimestre del mismo cliente en dos pestañas. En otra sesión, confirmar un registro personal o validar uno desde su asesoría. Las cifras y los pendientes cambian sin recargar.
2. Escribir otro año en el selector sin consultar: una actualización no borra lo escrito. Cerrar o reabrir desde otra vista actualiza el estado y el historial.
3. Subir un documento de un trimestre cerrado: las cifras guardadas permanecen congeladas y solo cambian los pendientes actuales.
4. Si el hub no está disponible, se conservan las últimas cifras y se puede usar **Actualizar cifras**. Al recuperarlo se reintentan los avisos pendientes y las vistas consultan el estado actual.

La integración controlada usa datos sintéticos confirmados temporalmente para probar conexiones reales. Detener primero el consumidor evita que publique los avisos usados para simular una caída:

`docker compose stop quarter_updates`

`docker compose exec -T --user www-data app php tests/Integration/live-quarter-workflow.php`

`docker compose start quarter_updates`

Las pruebas de JavaScript se ejecutan con `node --test tests/Frontend/live-quarter.test.mjs`. Para publicar pendientes una sola vez se puede usar `docker compose exec -T --user www-data app php bin/console app:quarter-updates:publish`.

## Límites

Los avisos no contienen cifras; son señales para consultar de nuevo. Pueden agruparse o llegar repetidos sin duplicar registros. La renovación cada 45 segundos permite recuperar el estado aunque se haya perdido un evento. La revisión visual específica en móvil y una prueba de carga quedan pendientes. El proceso de reintentos debe mantenerse activo en un despliegue.
