# Vinculación de clientes existentes · tarea 49

## Objetivo

Permitir que un autónomo o empresa ya registrado sin asesoría se vincule a una mediante invitación y aceptación expresa, conservando cuenta, documentos e histórico. Rama feature/existing-client-firm-invitations desde develop.

## Decisiones existentes

- La asesoría invita y el cliente acepta antes de compartir documentos e histórico; no hay asignación unilateral ni cambio de rol.
- Conservar los cierres personales y distinguirlos de los cierres/validaciones de asesoría.
- Los confirmados de periodos abiertos pasan a revisión; distinguir cifras pendientes/validadas sin borrar confirmación original.
- Reapertura personal por el cliente y de asesoría por admin/gestor, con autor y fecha.
- Solicitud actual del usuario: cerrar tarea 43 en Tareas por decisión, conservando hallazgos aplazados/no comprobados, e iniciar 49.

## Cambios planteados

- Invitación específica de vinculación, separada de las altas nuevas. Token aleatorio almacenado como hash, expiración de 24 horas, reenvío que invalida el anterior y aceptación por la cuenta destinataria autenticada.
- Comprobar pertenencia y permisos en casos de uso web/API; no compartir datos antes de aceptar ni permitir trasladar un cliente de otra asesoría.
- Coordinar aceptación con cambios de cliente, documentos y trimestre para evitar escrituras cruzadas. Conservar identidad, datos, archivos, confirmaciones e instantáneas personales.
- Invalidar accesos con permisos anteriores tras vincular y resolver invitaciones concurrentes sin doble asignación.
- Interfaz en Clientes/Mi asesoría y correo con remitente configurable/origen canónico; migración aditiva y pruebas sintéticas de permisos, expiración, concurrencia e históricos.

## Fuera de alcance

Desvinculación y traslado entre asesorías, cambios de rol, cobros, presentación fiscal, migración/seed en producción y cierre de todos los hallazgos OWASP por el cambio de estado de la tarea 43.

## Criterios de aceptación

- No se concede acceso documental antes de aceptar; solo la cuenta invitada puede aceptar un enlace válido y no usado.
- Una aceptación no cambia roles ni borra datos, archivos o cierres personales; periodos abiertos quedan pendientes de revisión profesional.
- Asesoría ajena, superadmin y otros clientes no pueden invitar/aceptar/consultar fuera de su permiso.
- Los enlaces reenviados, caducados, usados o de un cliente ya vinculado se rechazan con seguridad.
- Web y API reutilizan casos de uso; esquema y contrato se validan. La migración real queda pendiente hasta publicación autorizada.

## Permiso aprobado

Admin y gestor de la asesoría pueden enviar y reenviar la invitación. El cliente destinatario inicia sesión y acepta expresamente. Este permiso no amplía el alta de cuentas nuevas ni permite asignar unilateralmente una asesoría.

## Comprobado

- Implementado en `feature/existing-client-firm-invitations`, desde develop. Envío/reenvío en Clientes, explicación en Mi asesoría y aceptación explícita por web/API mediante el mismo caso de uso.
- Token aleatorio de 64 caracteres, solo hash persistido, 24 horas, reenvío serializado por cliente; unicidad cliente/asesoría. Una primera aceptación impide aceptar otra vinculación. El listado muestra las últimas 100 invitaciones y distingue aceptada, pendiente, caducada y no disponible.
- Aceptación bajo bloqueo de cliente y cuenta; vuelve a leer permisos, destinatario e invitación. Conserva rol, contraseña, contacto y registros. Incrementa authVersion para invalidar sesión/JWT anteriores.
- Los documentos de periodos personales cerrados conservan ámbito y datos; la bandeja los muestra en Histórico personal, de consulta. Los restantes cambian de ámbito manteniendo estado, datos, archivos e historial: enviados pendientes de validación y borradores privados hasta enviarlos.
- Reabrir el histórico sigue reservado al propietario, con historial. Corregir después un registro personal lo incorpora como borrador al circuito actual de asesoría, comprobando también su cierre de destino.
- Migración aditiva `Version20261118010000`: 20 migraciones aplicadas desde cero en MySQL 8.4 desechable y una migración incremental en Docker habitual. Esquema sincronizado en ambos. No se han ejecutado migraciones productivas.
- PHPUnit: **469 pruebas / 1.363 aserciones**, PHP 8.5.12 local. PHPStan, contenedor, 67 plantillas Twig y OpenAPI correctos. PHP-CS-Fixer con PHP 8.4.26: cero cambios pendientes en los 22 archivos PHP de la tarea. `config/reference.php` conserva su modificación previa y queda fuera.
- **20 workflows de seguridad correctos**, secuenciales en `aselto_link_security_ci`, con comprobación de tablas vacías antes/después, correo capturado o nulo y sin llamadas externas de IA. El nuevo workflow aporta **40 comprobaciones**: envío admin/gestor, CSRF, API, rechazo anterior a aceptación, reenvío, cuenta equivocada, acceso posterior y aislamiento ajeno, conservación de instantáneas/documentos, pendientes excluidos de cifras validadas, sesiones/JWT revocados, reapertura personal, corrección y aceptación web.
- Bloqueo SQL comprobado con una segunda conexión MySQL que agota su espera al competir por el mismo cliente. La prueba unitaria simula además una vinculación intervenida después de la primera consulta y comprueba su rechazo tras el bloqueo. No se presenta como ensayo de carga.
- Correo probado con transporte capturado: remitente configurado, origen canónico y explicación de consentimiento/caducidad. No se ha enviado correo real desde este desarrollo.

## Pendiente y límites

Revisión visual manual en navegador/móvil y correo SMTP real de este flujo; despliegue/migración y recorrido real en Hostinger; ensayo específico de la nueva tabla en MariaDB 11.8; carga con grandes históricos y carreras reales de dos aceptaciones HTTP simultáneas. Los archivos se conservan por referencia; este workflow no ensaya la recuperación binaria de justificantes/adjuntos productivos. La tarea 47 conserva esas comprobaciones finales. Commit local autorizado por el usuario el 19/11/2026. PR y publicación pendientes.

Guía funcional y explicación para el TFM en [client-firm-linking.md](../client-firm-linking.md). La tarea 43 se marca cerrada por decisión expresa del usuario, conservando sus hallazgos aplazados y verificaciones pendientes; no implica una auditoría sin incidencias.
