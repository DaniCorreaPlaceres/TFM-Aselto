# Actualización manual de las cifras

Por decisión del usuario, la primera publicación en **Hostinger Cloud Startup** actualiza las cifras al cargar la página o pulsar **Actualizar cifras**. No requiere un hub Mercure ni un publicador permanente. La IA, la revisión, los cálculos y los cierres mantienen su funcionamiento.

## Comportamiento

En producción, `app.quarter_live_enabled` es `false` en `config/services.yaml`. Twig entrega ese modo al controlador de Inicio y de las vistas trimestrales de cliente/asesoría. El navegador no solicita credenciales de suscripción, no crea EventSource, no programa renovaciones y no refresca solo por volver a una pestaña. El mensaje explica que las cifras corresponden al momento de consulta.

Actualizar solicita el fragmento financiero del periodo actual con sesión y permisos vigentes, sin caché, y cuenta como actividad manual de la sesión. No cambia filtros, no confirma documentos ni reabre un trimestre. Los periodos cerrados siguen mostrando su instantánea. Si hay datos editados, no los sobrescribe; si la petición falla, conserva las últimas cifras con aviso. Si la sesión o el permiso han cambiado, retira el contenido privado al actualizar y pide volver a entrar. El servidor comprueba los permisos en cada consulta, como antes.

Con el modo en vivo desactivado:

- La ruta de suscripción no emite credenciales y responde 404 al llegar al controlador.
- El outbox no añade nuevas notificaciones y el publicador no consulta ni contacta con el hub.
- `app:quarter-updates:publish`, incluso con `--watch`, termina sin iniciar un bucle.
- No se borran notificaciones anteriores ni su tabla. No hay migración ni cambio de datos históricos.

El entorno Docker de desarrollo conserva el modo en vivo (`true`) para demostrar y probar esa opción ya implementada. Esto no es el modo elegido para Cloud. El cambio productivo se aplica mediante configuración compilada: reconstruir la caché de producción al instalar la versión. No arrancar hub, proceso `--watch` ni cron de publicación financiera en Cloud para esta modalidad. Las tareas de seguridad son independientes y conservan su cron pendiente de verificar.

Las dependencias/configuración Symfony de Mercure permanecen instaladas para soportar el modo de desarrollo. No se exige un servidor Mercure disponible en el modo manual; esto se comprueba con una URL local inaccesible. No se está retirando el paquete ni cambiando silenciosamente permisos de suscripción para terceros.

## Comprobaciones

- PHPUnit: **452 pruebas / 1.295 aserciones**. Casos nuevos prueban ausencia de acceso al hub/base desde outbox/publicador desactivados, salida inmediata de `--watch` y ausencia de credenciales de suscripción.
- Frontend del trimestre: **14 pruebas**; incluyen modo manual sin peticiones automáticas ni temporizador, actualización explícita con actividad de sesión y retirada de cifras tras revocación. Los casos previos de formulario editado, error, respuesta tardía y sesión se conservan.
- Workflow trimestral en modo manual: **45 comprobaciones**, con HTML del modo correcto, permisos, cierres/reaperturas y preservación de datos tras rollback.
- Workflow en vivo de desarrollo: **38 comprobaciones** con SSE real; fixtures retirados y datos fiscales existentes conservados.
- En modo producción local se reconstruyó caché y se ejecutó el publicador con `--watch`, MySQL inaccesible y hub inaccesible: terminó correctamente indicando que no se inicia. El parámetro productivo se verificó en `false`.
- PHPStan, contenedor, **64 Twig** y **24 YAML** correctos. El formato PHP 8.4 se registra en la ficha al comprobarlo.

El workflow manual simula esa configuración en su kernel de pruebas; el de SSE conserva el entorno en vivo. No se ha desplegado en Hostinger ni verificado visualmente desde el hosting.

## Continuación de la preparación de Hostinger

Datos aportados por el usuario: Cloud Startup, SSH disponible, aselto.com asociado, PHP actualizado a 8.4 y base `11.8.9-MariaDB-log`. Antes de publicar hay que confirmar el PHP CLI efectivo y ensayar migraciones, esquema, consultas JSON y workflows sobre esa MariaDB en un destino separado. No se rebajan dependencias para PHP 8.3/8.1 ni se da por compatible MariaDB por usar el controlador MySQL.

El borrador previo de Hostinger está conservado aparte mientras se integra esta adaptación. La tarea 45 permanece abierta; la compatibilidad MariaDB y la preparación del paquete/restauración se retoman después. No se ha contratado un hub externo ni se ha cambiado el hosting.
