# Administración de clientes independientes

El superadmin abre **Clientes sin asesoría**. El listado incluye autónomos y empresas cuyo perfil y cuenta están sin asesoría y corresponden a un usuario cliente. También muestra las cuentas pendientes de activar. Los clientes vinculados a una asesoría y los perfiles con una cuenta inconsistente quedan fuera de este ámbito.

Al pulsar el nombre se abre la ficha administrativa. Se consultan tipo de cliente, correo y estado de activación; se pueden editar nombre o razón social, NIF, teléfono y dirección completa. No se cambian correo, contraseña, tipo, rol, titular ni asesoría desde esta ficha.

Los datos administrativos no dan acceso a facturas, justificantes, ingresos, gastos, cifras, trimestres, conversaciones o descargas privadas. La ficha no muestra enlaces a esa actividad. El superadmin sigue sin poder consultarla por las rutas web o por la API.

## Altas y edición

- **Invitar a un autónomo / Invitar a una empresa:** reutiliza las [invitaciones](invitations.md); el destinatario completa sus datos y contraseña al aceptar.
- **Dar de alta un cliente:** conserva el formulario con todos los datos y el correo de activación. El cliente creado por el superadmin queda sin asesoría.
- **Guardar datos:** valida nombre y contacto fiscal y conserva la cuenta y el tipo de cliente. Los documentos y las instantáneas ya cerradas no se modifican.

Cada perfil tiene una versión. Si otro usuario ha guardado cambios, el formulario antiguo no los sobrescribe: hay que recargar y revisar. La misma protección se aplica a los formularios fiscales del cliente y de su asesoría. Antes de guardar se vuelve a comprobar el permiso bajo bloqueo del perfil y la cuenta. Un cliente que se vincule a una asesoría deja de estar disponible para esta administración.

La ruta fiscal anterior de un cliente independiente redirige al superadmin a la nueva ficha. Los admins y gestores conservan sus consultas dentro de su asesoría y los clientes sus propios datos fiscales; no reciben acceso a esta administración de plataforma.

## Probarlo en local

1. Entrar como `demo-superadmin@example.test` con la contraseña inicial `aselto-demo`, si se ha cargado la [demostración](demo.md).
2. Abrir **Clientes sin asesoría** y pulsar el cliente independiente de demostración, o crear uno ficticio mediante invitación o alta directa.
3. Guardar un cambio de nombre o dirección y comprobar que correo y tipo de cuenta se mantienen.
4. Mantener la ficha abierta en dos pestañas. Guardar desde una y comprobar que la otra rechaza el formulario antiguo.
5. Entrar como cliente o personal de una asesoría y comprobar que no se puede abrir la ficha administrativa de plataforma.

La migración `Version20261114030000` añade la versión al perfil, sin modificar sus datos previos. No se revierte automáticamente porque retiraría la protección de edición concurrente. No hace falta generar nuevas claves ni cambiar credenciales.

La ficha [platform-independent-clients.md](sdd/platform-independent-clients.md) recoge los resultados. La integración `tests/Integration/platform-independent-clients-workflow.php` requiere una base MySQL temporal migrada y claves JWT locales. Usa perfiles, actividad y archivo ficticios, confirma sus registros para simular peticiones independientes y los retira al terminar. Los correos de alta se capturan mediante un notificador de prueba; no envía correo externo. La revisión visual en navegador no forma parte de esa prueba HTTP.
