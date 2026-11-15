# Integraciones de seguridad en GitHub · F13

## Qué ejecuta

El job `Seguridad web y API`, en `.github/workflows/comprobaciones.yml`, se añade a cada PR a `develop`/`main` y push a esas ramas. No tiene filtros de rutas ni `continue-on-error`. Usa Ubuntu, PHP 8.4 y MySQL 8.4 desechable. Genera claves JWT sintéticas, instala Importmap, compila Sass, aplica migraciones y valida el esquema antes de las pruebas. El job tiene 25 minutos de máximo y cada workflow, 300 segundos.

`php bin/check-security.php` ejecuta 19 workflows en procesos separados, secuencialmente. Sale con código 1 al primer error, timeout o fallo de limpieza. No reintenta ni convierte un fallo en éxito. Exige `SECURITY_CHECKS_DISPOSABLE=1`, `APP_ENV=dev`, una base cuyo nombre termine en `_security_ci`, coincidencia con la base realmente conectada y tablas de negocio vacías. Comprueba ese estado inicial y después de cada workflow. No crea, vacía ni elimina bases automáticamente.

Se usa `dev` porque los kernels de estas integraciones ya lo declaran explícitamente; no se utiliza el sufijo automático `_test` de Doctrine. Esto no configura una aplicación productiva: el runner y la base son desechables y no contienen datos reales. Los cinco jobs anteriores conservan su comportamiento.

El ejecutor fuerza correo `null://null`, clave de OpenAI vacía, alertas desactivadas y publicación Mercure a un loopback sin hub. Las pruebas de subida usan extractores sintéticos y las de correo capturan notificaciones. No recibe secretos de producción, no envía correo real ni documentos a IA. La suscripción SSE real se verifica por separado en el workflow local `live-quarter-workflow.php`, que no forma parte de este job.

## Cobertura

- Documentos API y web: acceso por propietario/asesoría, originales, justificantes, estados, revisión y periodos cerrados.
- Autenticación API: firma, caducidad, roles actuales, cuentas inactivas/eliminadas y revocación tras cambio de contraseña.
- Comunicación API/aplicación: ámbito de conversaciones y archivos, solicitudes y permisos.
- Trimestres API/web/aplicación: ámbito, cierre/reapertura, histórico y bloqueo de escrituras concurrentes.
- Administración de asesorías, clientes independientes e invitaciones: permisos y exclusión del contenido fiscal.
- Enlaces canónicos, límites de autenticación y almacenamiento compartido entre seis procesos, política de contraseña, caducidad y logout CSRF.
- Cabeceras del navegador, presupuestos de recursos y registro privado de seguridad.

La prueba API de comunicación reinicia únicamente su `ArrayAdapter` de límites entre casos independientes de validación/permisos, para que el 429 no oculte el resultado que se quiere comprobar. El workflow de recursos conserva sus contadores para probar los presupuestos reales. La prueba de bloqueo de trimestre crea y elimina un cliente sintético comprometido fuera de la transacción de bloqueo, necesario para que la segunda conexión pueda verlo; ya no selecciona un cliente demo existente.

La cobertura es una muestra de regresión, no una certificación OWASP ni una prueba de todos los permisos posibles. No resuelve antimalware F08, producción, carga, configuración del hosting o seguridad de dependencias completa.

## Reproducir

Preparar una **copia separada**, sin `.env.local`, claves, caché o datos del entorno habitual. Instalar dependencias de desarrollo, generar JWT de prueba y preparar Importmap/Sass. Crear mediante operaciones una base exclusiva, vacía y desechable con nombre terminado en `_security_ci`; el usuario SQL solo necesita permisos en ella.

Ejemplo para una terminal Bash dentro de esa copia aislada, adaptando la conexión a su MySQL desechable:

```bash
export APP_ENV=dev APP_DEBUG=0
export APP_SECRET=synthetic-security-ci-secret
export JWT_PASSPHRASE=synthetic-security-ci-passphrase
export DATABASE_URL='mysql://synthetic:synthetic@127.0.0.1:3306/aselto_security_ci?serverVersion=8.4&charset=utf8mb4'
export SECURITY_CHECKS_DISPOSABLE=1
export MAILER_DSN='null://null' OPENAI_API_KEY='' SECURITY_ALERT_EMAIL_ENABLED=0
composer install --no-interaction --prefer-dist --no-scripts
php bin/console lexik:jwt:generate-keypair
php bin/console importmap:install
php bin/console sass:build
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
php bin/check-security.php
```

No ejecutar contra la base habitual ni reutilizar claves de producción. Para diagnosticar un único caso se admite una selección de la lista cerrada, por ejemplo `php bin/check-security.php --only=api-documents`; GitHub siempre llama sin selección y ejecuta la lista completa. No lanzar dos ejecutores a la vez en la misma copia/base: algunos workflows usan ubicaciones temporales de prueba fijas.

En la prueba local de F13 se utilizó `/tmp/aselto-f13-security-ci` dentro del contenedor y la base nueva `aselto_f13_security_ci`. Se extrajeron archivos versionados, sin configuración privada ni claves del usuario; se reutilizaron las dependencias y los módulos Importmap locales y se generaron claves sintéticas nuevas. No equivale a una ejecución remota de GitHub ni a descargar todas las dependencias desde cero.

## Verificación y regresión deliberada

Los 19 workflows han pasado en la copia aislada con PHP 8.5.12. También pasan PHPUnit **449 pruebas / 1.286 aserciones**, PHPStan y el formato con PHP 8.4 sobre copia LF (**405 archivos**). YAML de Actions válido.

Se retiró únicamente en la copia temporal la condición de propietario de `OpenClientDocument`. El ejecutor salió con **1** en `api-documents`, con `Another client must not read an original or its metadata.`. Se restauró el archivo, se comparó su hash con el original y la suite completa volvió a pasar. La mutación no se integra y el código de negocio habitual no cambia.

Se comprobaron rechazos con código 1 sin ejecutar workflows: ausencia de opt-in, base con nombre no permitido, selección desconocida y una base desechable con una fila sintética. La fila se eliminó al terminar. La versión final verifica también la limpieza después de cada workflow.

Durante el diagnóstico de autenticación se observó un retroceso real del reloj local: un token con `iat=1794782016` se comprobó cuando tanto `time()` como DateTime indicaban `1794782014`. Su rechazo es correcto. No se añade tolerancia temporal, reintentos ni se debilita la validación para ocultarlo. Si se reproduce en un runner, debe revisarse su reloj; las comprobaciones siguen fallando. La evidencia conserva esta limitación de repetibilidad local y las incidencias iniciales de contadores/datos demo.

## Propuesta de comprobaciones obligatorias — pendiente de autorización

Consulta realizada el 15/11/2026: ruleset activo `main protegida` (26658888), con PR, bloqueo de borrado y force-push; sin regla de checks obligatorios. La protección clásica de `develop` devuelve 404 y el listado de rulesets solo contiene el de main. No se modifica ninguna regla en esta entrega.

Propuesta concreta, tras observar el nuevo job verde en un PR:

- Aplicar a `main` y `develop`, exigiendo PR, sin añadir aprobaciones de otra persona para este TFM individual.
- Exigir estos seis nombres exactos: `PHP-CS-Fixer`, `PHP-Stan`, `PHPUnit`, `Esquema MySQL`, `OpenAPI` y `Seguridad web y API`.
- Aceptar resultados de GitHub Actions (app 15368, verificada en los cinco checks existentes), con rama actualizada respecto a su base antes de mergear.
- Conservar el bloqueo de borrado y force-push; no crear excepciones que permitan saltarse las comprobaciones ni cambiar los métodos de merge existentes.

Primero hay que publicar el PR, observar el nuevo check y autorizar los ajustes anteriores. Después se aplican y se verifica en GitHub que un check fallido impide mergear sin utilizar excepciones. **Un job añadido no equivale a una protección activa**: F13 continúa parcialmente pendiente hasta esa verificación. No se ha creado un PR vulnerable ni se ha intentado mergearlo.

Referencias: [servicios MySQL en Actions](https://docs.github.com/en/actions/tutorials/use-containerized-services/use-docker-service-containers) y [checks obligatorios en rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/available-rules-for-rulesets). La puntuación histórica de la auditoría permanece intacta.
