# Administración de asesorías

El superadmin entra en **Asesorías** y pulsa el nombre de una asesoría para abrir su ficha. Puede consultar y editar nombre, NIF, teléfono y dirección completa. El listado conserva el acceso a invitaciones y al alta con todos los datos.

La ficha muestra el correo del admin y su estado de activación. Si no existe un único admin, no permite cambiar su correo. No ofrece acceso a documentos, movimientos, trimestres, conversaciones o archivos privados de la asesoría ni de sus clientes. Los permisos de administración son distintos de los del equipo de la asesoría.

## Cambiar el correo del admin

1. El superadmin indica el nuevo correo desde la ficha. Se rechazan direcciones inválidas o que ya pertenecen a otra cuenta, incluso pendiente de activación.
2. Se envía al nuevo correo un enlace de 24 horas. El correo actual sigue vigente y los accesos existentes siguen funcionando.
3. Abrir el enlace muestra la confirmación; no cambia nada. El destinatario debe pulsar **Confirmar nuevo correo**.
4. La confirmación conserva identificador de usuario, asesoría, rol, activación y contraseña. Cambia el correo e invalida sesiones, JWT y enlaces abiertos de activación y recuperación anteriores. Se envía un aviso al correo antiguo.
5. El admin vuelve a entrar con el nuevo correo y su contraseña habitual. Si todavía no había creado una contraseña, puede solicitarla desde **He olvidado mi contraseña**, usando el nuevo correo.

Los enlaces son de uso único y se almacenan como hash. Reenviar genera otro enlace y el anterior deja de funcionar, aunque falle el envío nuevo. **Cancelar cambio pendiente** invalida su enlace sin modificar la cuenta. Un correo ocupado por otra cuenta después de solicitar el cambio tampoco puede confirmarse.

Si falla el envío al nuevo correo, la ficha muestra el aviso y permite volver a enviarlo. Si falla el aviso al antiguo después de confirmar, el cambio ya está realizado: se conserva el aviso pendiente y el superadmin puede reenviarlo. No se permite solicitar otro cambio hasta enviar ese aviso. Un reintento de correo no es una garantía de entrega final en el buzón.

## Cambios concurrentes

Los formularios identifican la versión de los datos que mostraron. Si otro usuario los cambia, no se sobrescriben: hay que recargar y revisar. Esto también se aplica al formulario de datos fiscales del propio admin. La solicitud, confirmación y cancelación del correo se serializan con la ficha y el usuario; la recuperación de contraseña comprueba de nuevo el usuario y el enlace tras adquirir su bloqueo.

No se cambia un rol ni se reasigna una cuenta o un cliente a otra asesoría desde esta ficha. La edición del perfil no recalcula cifras ni modifica documentos o instantáneas cerradas.

## Probarlo en local

- Entrar como `demo-superadmin@example.test` con la contraseña inicial `aselto-demo` si se ha cargado la [demostración](demo.md), y abrir **Asesorías**.
- Para no alterar los accesos de la demostración, crear una asesoría ficticia mediante [Invitaciones](invitations.md) o el alta con todos los datos.
- Editar nombre o dirección y comprobar que se guardan. Mantener otra pestaña abierta, cambiar desde la primera y comprobar que el formulario antiguo no sobrescribe los datos nuevos.
- Solicitar un cambio a un correo ficticio nuevo de `example.test`. Leer la confirmación en [Mailpit](http://localhost:8025), confirmar en una sesión sin autenticar y comprobar el aviso en el correo antiguo.
- Comprobar que el correo anterior ya no permite entrar y que el nuevo conserva la contraseña, rol y asesoría.
- Antes de confirmar otra solicitud, probar su reenvío o cancelación y verificar que el enlace anterior ya no sirve.

La migración `Version20261114020000` añade revisión de autenticación, versión de asesoría y solicitudes de cambio de correo. No borra datos y su reversión automática está bloqueada porque retirar revisiones podría restaurar accesos revocados. Esta actualización exige volver a iniciar sesión para los JWT y sesiones antiguos, que todavía no contienen la revisión de autenticación. Los tokens nuevos incluyen un identificador estable de usuario y su revisión: reutilizar después un correo no restaura accesos de su anterior titular.

La ficha [platform-firm-administration.md](sdd/platform-firm-administration.md) registra las pruebas ejecutadas. El flujo `tests/Integration/platform-firms-workflow.php` se ejecuta expresamente en una base MySQL temporal migrada, con claves JWT locales disponibles, perfiles ficticios y un notificador de prueba; crea y elimina sus propios registros confirmados. El correo externo y la revisión visual en navegador quedan fuera de esas comprobaciones HTTP.
