# Vincular una cuenta existente a una asesoría

## Finalidad y recorrido

Un autónomo o empresa que utiliza Aselto sin asesoría conserva su cuenta y puede empezar a trabajar con un equipo profesional. El alta de una cuenta nueva y la vinculación son procesos distintos: no se crea otro usuario ni se cambia su rol o contraseña.

1. El admin o gestor entra en **Clientes → Vincular un cliente que ya tiene cuenta** e indica el correo de su cuenta independiente activada.
2. Aselto guarda la invitación y envía un enlace. No concede acceso documental todavía. El enlace caduca en 24 horas; reenviarlo al mismo destinatario invalida el anterior.
3. El cliente abre el enlace e inicia sesión con la cuenta destinataria. La pantalla identifica la asesoría y explica qué información compartirá.
4. Pulsa **Aceptar y compartir con esta asesoría**. Abrir el enlace o consultar la pantalla no aceptan por sí solos.
5. Una transacción vincula cuenta y perfil, registra la aceptación y cambia el ámbito de los documentos de periodos abiertos. Se invalidan sesiones y JWT anteriores; el cliente vuelve a iniciar sesión.

Un enlace reenviado, caducado o usado no permite aceptar. Tampoco puede aceptarlo otra cuenta, ni utilizarse para trasladar a alguien que ya tenga asesoría. Las invitaciones pendientes de otras asesorías quedan inutilizables cuando acepta una primera vinculación.

## Histórico y permisos

Los documentos de trimestres personales cerrados y sus instantáneas permanecen intactos. La asesoría puede consultarlos en **Documentos → Histórico personal**, sin acciones de validación. No se convierten en cifras validadas profesionalmente.

Los documentos de periodos abiertos, incluidos borradores y documentos sin fecha, pasan al ámbito de la asesoría. Conservan datos, archivos e historial. Los confirmados mantienen el estado enviado, pendiente de validación. Los borradores siguen siendo privados del cliente hasta enviarlos.

El cliente puede reabrir sus cierres personales con el registro habitual de autor y fecha. Si después corrige un registro histórico, este entra como borrador en el circuito de su asesoría actual y debe confirmarse de nuevo. Se comprueba también que el trimestre de destino no esté cerrado. La asesoría no puede reabrir cierres personales. Admin y gestor mantienen sus permisos sobre los cierres de su propia asesoría.

Los archivos conservan su ubicación privada; la consulta pasa por permisos del servidor. No se crean copias públicas, no se duplican registros ni se consume otra unidad de cuota documental por vincular.

## Implementación para el TFM

`POST /api/v1/firm-link-invitations` recibe `email`; permite enviar o reenviar al admin o gestor autenticado por JWT. `POST /api/v1/firm-link-invitations/accept` recibe `token` desde la cuenta destinataria. La respuesta indica `login_required: true`. Ambos reutilizan el mismo caso de uso que la web; contrato en `openapi/openapi.yaml`.

La migración `Version20261118010000` añade la tabla de invitaciones, con hash del token y unicidad por cliente/asesoría. No modifica documentos existentes al desplegar; la pertenencia solo cambia al aceptar.

La aceptación usa el bloqueo de cliente compartido con documentos y trimestres, más el bloqueo de cuenta para conservar cambios concurrentes de contraseña o correo. Después de adquirirlos comprueba de nuevo destinatario, invitación y pertenencia. El token en claro se envía por correo; no se guarda en la tabla ni en los eventos de seguridad.

## Prueba manual

En Docker, utiliza una cuenta independiente activada y una asesoría ficticia. Envía la invitación desde Clientes y abre el mensaje en Mailpit. Antes de aceptar, comprueba que la asesoría no pueda abrir los documentos. Acepta con el cliente, vuelve a entrar y comprueba Mi asesoría, la bandeja pendiente y el histórico personal. Reenvía una invitación y comprueba que el enlace anterior falle. Usa siempre datos ficticios.

Evidencias ejecutadas y límites en [la ficha de la tarea 49](sdd/existing-client-firm-invitations.md). La prueba de este flujo en Hostinger queda pendiente de despliegue autorizado.
