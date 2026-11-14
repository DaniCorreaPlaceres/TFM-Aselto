# Invitaciones de registro

El superadmin dispone de **Invitaciones** en el menú. Elige el tipo de cuenta e indica el correo:

- **Asesoría:** el destinatario completa los datos de la asesoría y crea su cuenta de admin. También sirve para un asesor que trabaja solo.
- **Autónomo sin asesoría:** crea un cliente independiente autónomo.
- **Empresa sin asesoría:** crea un cliente independiente de tipo sociedad.

Enviar no crea todavía usuarios, asesorías ni perfiles de cliente. El destinatario recibe un enlace de uso único, completa nombre, datos fiscales y contraseña y confirma su registro. La cuenta queda activada y puede entrar; no se inicia sesión automáticamente. El correo y el tipo de cuenta no se pueden cambiar desde ese formulario.

## Estados y reenvío

- **Pendiente:** enlace disponible durante 24 horas desde su generación.
- **Caducada:** ya no permite registrarse; puede reenviarse.
- **Aceptada:** cuenta creada; no puede reutilizarse ni reenviarse.
- **Correo ya registrado:** la dirección obtuvo una cuenta por otra vía. No permite aceptar ni reenviar y no modifica la cuenta existente.

Reenviar conserva el destinatario y el tipo de cuenta, renueva las 24 horas e invalida el enlace anterior. Si ya hay una invitación para un correo, se consulta la existente: no se crea otra ni se cambia de tipo al reenviarla. Una cuenta existente, aunque no haya completado su activación, no se convierte en otro tipo de cuenta mediante este flujo.

El listado muestra fechas en horario de Canarias; se almacenan en UTC. La fecha de envío corresponde a la generación del enlace y no certifica su entrega. Si el transporte de correo falla, aparece un aviso y la invitación queda disponible para reenvío. El enlace anterior permanece invalidado aunque falle ese envío.

## Comprobarlo localmente

1. Aplicar la migración del proyecto con `docker compose exec -T --user www-data app php bin/console doctrine:migrations:migrate --no-interaction`.
2. Entrar como `demo-superadmin@example.test` con la contraseña inicial `aselto-demo`, si se ha cargado la [demostración](demo.md).
3. Abrir **Invitaciones → Enviar invitación**, elegir un tipo y usar un correo ficticio nuevo de `example.test`.
4. Abrir [Mailpit](http://localhost:8025). Copiar el enlace del correo y abrirlo en una sesión sin autenticar para completar los datos y contraseña.
5. Entrar con la nueva cuenta y comprobar el rol y, para clientes independientes, la ausencia de asesoría.
6. En otra invitación pendiente, guardar el enlace inicial, reenviar y comprobar que solo el nuevo enlace funciona. Tras aceptar, el enlace debe mostrar «Invitación no disponible» y el estado «Aceptada».

Los datos de demostración ya creados no sirven como destinatarios nuevos. Los correos de Mailpit se quedan en el entorno local; no se entrega correo externo con esta configuración.

## Permisos y alcance

Solo el superadmin administra estas invitaciones. Este permiso no concede acceso a documentos, cifras, trimestres o conversaciones de las cuentas invitadas. La operación de registro y consumo es atómica, con bloqueo de la invitación y unicidad del correo. El token se guarda como hash; los listados no contienen el token ni su hash y las páginas de aceptación indican que no deben almacenarse en caché ni enviar referentes.

Los flujos anteriores de alta con todos los datos, activación de cuentas y equipo de asesoría se conservan. Sus enlaces previos no se convierten en invitaciones del nuevo listado. El registro público sigue disponible.

La ficha [platform-invitations.md](sdd/platform-invitations.md) recoge las verificaciones. La integración `tests/Integration/platform-invitations-workflow.php` requiere un MySQL migrado y usa cuentas ficticias y un notificador de prueba. Crea y elimina registros propios confirmados para comprobar transacciones; se recomienda una base temporal separada. No sustituye una prueba con un proveedor de correo externo.
