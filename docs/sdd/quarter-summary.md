# Trimestre: cifras, cierre e histórico

## Objetivo

Calcular las cifras de cada cliente por trimestre natural, congelarlas al cerrar y conservar el histórico de cierres y reaperturas. El cliente sin asesoría usa documentos confirmados para control personal; con asesoría se usan registros validados.

## Decisiones confirmadas

- Un pendiente con fecha del trimestre bloquea su cierre. También lo bloquea cualquier documento sin fecha dentro de ese ámbito del cliente.
- Admin y gestor cierran y reabren los periodos de su asesoría. El cliente sin asesoría cierra sus periodos personales y conserva el permiso para reabrir sus cierres personales.
- Los cierres personales anteriores se conservan y se distinguen al vincularse a una asesoría; esta puede consultarlos sin convertirlos en validados.

## Fuera de alcance

Modelos o presentación de impuestos, cálculo de deducibilidad o cuota de IRPF, bancos, varias bases por documento, vinculación de cuentas y cambios de asesoría, mensajería y actualización por Mercure (tareas siguientes).

## Criterios de aceptación

- La fecha del documento determina el trimestre natural. Se pueden elegir año y trimestre y consultar el histórico. La comparación usa fechas fiscales, sin convertirlas entre zonas horarias.
- Sin asesoría se incluyen los confirmados personales; con asesoría, solo los validados de esa asesoría. Borradores, rechazados, enviados sin validar y solicitudes pendientes se muestran aparte. Los documentos sin fecha no se asignan a un trimestre por su fecha de subida.
- Ingresos y gastos suman bases; resultado = ingresos menos gastos. IVA, IGIC e IPSI muestran cuotas repercutidas, soportadas y saldo por separado. El recargo de gastos y las retenciones de ingresos se muestran separados y no se descuentan del saldo de IVA. No se presenta una liquidación fiscal.
- Inicio e Ingresos y gastos muestran cifras reales, con una etiqueta clara de control personal o validado. La asesoría abre el trimestre desde la ficha de su cliente.
- Cada lectura y cambio verifica actor, papel y cliente. El cliente solo ve lo suyo; admin y gestor solo los clientes actuales de su asesoría. El superadmin no obtiene acceso fiscal a través de estas rutas. Una asesoría no consulta cierres de otra. Los históricos personales consultables por la asesoría son de solo lectura para ella.
- Cerrar exige que no haya pendientes del periodo ni documentos sin fecha. También comprueba que los datos y la versión coincidan con los mostrados al usuario. Puede cerrarse un periodo sin actividad si no tiene pendientes.
- El cierre guarda una instantánea independiente de los documentos, con sus importes, identificadores y detalle. El cierre personal y el de asesoría tienen ámbitos separados. El histórico conserva autor, fecha e instantánea de cada cierre y reapertura.
- Reabrir permite recalcular; no borra el cierre anterior. Requiere los permisos acordados y protege frente a formularios desactualizados. Un cierre personal existente puede volver a cerrarse por su propietario después de reabrirlo, aunque esté vinculado después.
- No se confirma ni valida un registro en un trimestre cerrado. Tampoco se cambian datos fechados de un registro dentro de un periodo cerrado ni se mueve desde él a otro trimestre sin reabrirlo. Se comprueban fecha original y destino y el ámbito correspondiente.
- Se permite subir archivos sin afectar a cifras congeladas. Una propuesta tardía fechada en un periodo cerrado no permite guardar correcciones ni confirmarse hasta reabrir. Los nuevos pendientes se avisan aparte sin cambiar el cierre.
- El cliente sin asesoría puede iniciar una corrección de su registro confirmado si el trimestre está abierto: pasa a borrador y debe confirmarlo de nuevo. El cliente no edita registros validados por una asesoría.
- Cierres y mutaciones de documentos se serializan por cliente dentro de una transacción para evitar carreras entre validación, edición, subida y cierre. El dominio no depende de Doctrine ni HTTP.
- La migración añade las tablas financieras sin alterar los documentos existentes. Se prueban totales, límites de trimestre, permisos, pendientes, congelación, reapertura, cambios de fecha, corrección personal, persistencia, concurrencia y recorrido web.

## Comprobado

- Rama: `feature/quarter-summary`, creada desde `develop`.
- PHPUnit en Docker: **221 pruebas y 568 aserciones**, todas correctas. Incluyen límites de los cuatro trimestres, fecha fiscal sin conversión horaria, importes cero, impuestos separados, pendientes, huella de los datos, versiones, permisos e histórico personal conservado tras vinculación simulada.
- Integración en MySQL y kernel Symfony: **43 comprobaciones**, correctas. Dos conexiones independientes verifican que una operación espera el bloqueo del cliente y continúa cuando se libera. Se prueban confirmación personal, validación de asesoría, documentos sin fecha, cierre con pendientes, registros tardíos, bloqueo de fecha original y destino, corrección personal, versiones obsoletas y conservación de instantáneas tras recargar Doctrine.
- Peticiones HTTP autenticadas: vistas de cliente y asesoría, aislamiento entre asesorías, denegación al superadmin, trimestre inválido, rechazo de CSRF incorrecto y formularios válidos de cierre, reapertura, corrección y confirmación. Las cifras de la página se contrastan con las guardadas.
- Los datos ficticios de integración se deshacen en una transacción. Se comparó el contenido de los documentos existentes antes y después: sin cambios.
- Migración `Version20261109010000` aplicada en la base local: una consulta crea `financial_quarter`. El mapeo Doctrine es válido; no se ha ejecutado una actualización general del esquema.
- PHPStan nivel 6 sin errores, contenedor válido, **34 plantillas Twig** válidas y OpenAPI válido.
- Formato Symfony comprobado en los 51 archivos PHP de la tarea mediante copias con finales LF; ninguna corrección pendiente. `git diff --check` correcto.
- Revisión visual en navegador de escritorio: acceso desde Clientes al trimestre de la asesoría y presentación del estado sin movimientos. Los recorridos con importes, cierres e historial se verifican por HTTP; la revisión visual específica en móvil queda pendiente.

## Cómo probar

1. Entrar como cliente y abrir **Ingresos y gastos**. Elegir año y trimestre. Sin asesoría cuentan los confirmados personales; con asesoría, solo los validados.
2. Añadir un borrador fechado en ese trimestre, o uno sin fecha: aparece entre los pendientes y bloquea el cierre. Un pendiente fechado en otro trimestre no lo bloquea.
3. Resolver los pendientes y cerrar. Intentar corregir o confirmar un registro del periodo cerrado: se pide reabrir. Una subida posterior se muestra aparte y no cambia las cifras congeladas.
4. Reabrir, corregir un registro personal confirmado, confirmarlo de nuevo y cerrar otra vez. El historial conserva los importes de cada versión y quién cerró o reabrió.
5. Como admin o gestor, abrir **Clientes → Ver trimestre**. Ambos pueden gestionar el periodo de su asesoría. El cliente vinculado lo consulta sin cerrarlo ni reabrirlo.

La prueba de integración se repite sobre la base local migrada, con al menos un cliente de demostración existente, mediante:

`docker compose exec -T --user www-data app php tests/Integration/quarter-workflow.php`

Usa datos sintéticos propios y revierte sus cambios. No se debe ejecutar mientras se modifican documentos manualmente en esa misma base, ya que contrasta su contenido antes y después.

## Límites

La consulta del histórico personal tras una vinculación se prueba simulando la relación: el flujo de invitación y aceptación sigue fuera de esta tarea. Los importes no se actualizan por Mercure todavía y no se genera ninguna declaración fiscal.
