# Aselto

**Tus finanzas bajo control.** Proyecto TFM de Daniel Correa Placeres: plataforma web para que autónomos, empresas y asesorías organicen sus documentos y consulten su actividad económica.

El cliente añade una factura o justificante y revisa los datos propuestos. Sin asesoría, confirma el registro para su control personal; con asesoría, el equipo lo revisa y valida. **La IA propone: una persona revisa y confirma.** Aselto no presenta impuestos ni sustituye el criterio profesional.

## Qué está implementado

- Acceso por papeles, activación mediante enlaces por correo y recuperación de contraseña.
- Alta de asesorías, equipos y clientes; perfiles con datos fiscales y separación entre asesorías.
- [Invitaciones del superadmin](docs/invitations.md) a asesorías y clientes independientes: el destinatario completa sus datos y contraseña, con caducidad, uso único, estados y reenvío.
- Subida de documentos en una sola zona: archivo, foto o arrastre. Revisión del original con visor PDF, borradores y confirmación humana.
- Extracción mediante un proveedor de IA cuando se configura; sin clave se utiliza una propuesta de ejemplo claramente identificada. Un fallo de extracción permite la revisión manual.
- Impuesto según territorio (IVA, IGIC o IPSI manual), recargo de equivalencia y retenciones opcionales según el registro y el perfil.
- Bandeja de asesoría para validar, rechazar con motivo, corregir y reenviar o pedir justificantes adicionales, conservando original e historial.
- Trimestres con cifras, pendientes, cierre, reapertura e instantáneas históricas. Los pendientes y los documentos sin fecha bloquean el cierre. Actualización en vivo de las cifras con Mercure y alternativa de actualización manual.
- Conversaciones y solicitudes entre cliente y asesoría, lectura individual y adjuntos privados.
- Interfaz adaptable con Bootstrap y Sass, navegación móvil desplegable y listados con búsqueda, filtros, paginación y exportación Excel/CSV.
- API JSON con JWT para identidad, documentos, trimestres y comunicación, con contrato OpenAPI y Swagger UI.
- Datos ficticios de demostración de dos asesorías, sus equipos y clientes, más un cliente independiente.

### Papeles y acceso a la información

- **Superadmin:** da de alta asesorías y clientes independientes. Su papel no da acceso a documentos, cifras ni conversaciones privadas. La ampliación de su administración y edición está planificada.
- **Admin de asesoría:** gestiona su equipo y clientes y revisa documentos, trimestres y comunicaciones de su asesoría.
- **Gestor:** revisa documentos, gestiona trimestres y atiende comunicaciones dentro de su asesoría. No tiene las mismas facultades de alta y edición de perfiles que el admin.
- **Cliente:** gestiona sus documentos y consulta sus cifras. Sin asesoría, sus confirmaciones y cierres son personales; con asesoría se distinguen de la validación del equipo.

## Arquitectura y tecnologías

Monolito modular con los módulos `Identidad`, `Asesoria`, `Cliente`, `Documento`, `Finanzas`, `Comunicacion` y `Shared`. Cada módulo separa `Domain`, `Application`, `Infrastructure` y `Ui`. El dominio no depende de Doctrine, HTTP ni del proveedor de IA; web y API reutilizan los casos de uso.

- Symfony 7.4 y PHP: 8.4 en CI y 8.5 en la imagen Docker.
- MySQL 8.4, Doctrine ORM y migraciones.
- Twig, Stimulus, Turbo y AssetMapper.
- Bootstrap 5, Sass/SCSS, Inter, DataTables y PDF.js con recursos locales.
- Mercure para actualizaciones privadas; Symfony Messenger con transporte Doctrine disponible para trabajos en segundo plano.
- Sesión de Symfony en la web; JWT firmado con claves RSA en la API.
- Almacenamiento privado en `var/storage`, servido mediante rutas autorizadas.

La [memoria](docs/memoria.md) contiene las decisiones de producto y arquitectura. Las [fichas SDD](docs/sdd/README.md) documentan objetivos, criterios y comprobaciones por tarea.

## Desarrollo local

Se necesita Git y Docker Desktop con Docker Compose, además de puertos libres para los servicios publicados. No hace falta instalar PHP, Composer ni MySQL en el equipo para usar el entorno Docker. Para ejecutar las pruebas JavaScript fuera del contenedor, CI utiliza Node.js 22.

**Arranque de un entorno ya configurado:**

```bash
docker compose up --build -d
```

Para preparar una copia nueva, seguir la [guía de instalación local](docs/local-installation.md): configuración privada, claves, migraciones, recursos y demostración, con los resultados y límites de la verificación realizada.

Servicios del entorno local:

- Web: [Aselto](http://localhost:8080).
- Comprobación de entorno: [salud](http://localhost:8080/salud).
- Documentación interactiva: [Swagger UI](http://localhost:8080/api/docs).
- Correo de desarrollo: [Mailpit](http://localhost:8025).
- MySQL: puerto 3306. Configuración local en [compose.yaml](compose.yaml).
- Mercure: puerto 9080 y acceso de la aplicación a través de Nginx.
- `styles`: recompila Sass al cambiar los estilos; `quarter_updates`: publica las actualizaciones de trimestre pendientes.

Las credenciales de Compose y de los datos de ejemplo son exclusivamente de desarrollo. Las claves JWT y la configuración privada no se versionan. El despliegue de producción en Hostinger es una tarea posterior, no está configurado por este arranque local.

## Probar la demostración

Con el entorno preparado, las claves locales y la base migrada:

```bash
docker compose exec -T --user www-data app php bin/console app:seed-demo
```

Crea dos asesorías, diez accesos activados, cinco clientes y documentos, trimestres y conversaciones ficticios. Si la carga ya existe, conserva sus datos y contraseñas actuales; no reinicia los ejercicios realizados.

Acceso de ejemplo: `demo-admin-sol@example.test`, contraseña inicial local `aselto-demo`. La [guía de demostración](docs/demo.md) recoge todos los usuarios, fechas de los ejemplos y recorridos de revisión, impuestos, cierres, mensajes y aislamiento. No utiliza datos fiscales reales.

## Comprobaciones

Cada PR hacia `develop` o `main`, y cada push a esas ramas, ejecuta el [flujo de comprobaciones](.github/workflows/comprobaciones.yml):

- PHP-CS-Fixer para formato.
- PHPStan nivel 6 para análisis estático.
- PHPUnit, compilación de recursos y pruebas JavaScript de trimestre, exportación y subida.
- Validación del contrato OpenAPI.
- Migraciones y validación de esquema en MySQL 8.4, con prueba de conservación de registros y notificaciones pendientes.

Ejemplos de ejecución local:

```bash
docker compose exec -T --user www-data app php vendor/bin/phpunit
node --test tests/Frontend/live-quarter.test.mjs tests/Frontend/table-export.test.mjs tests/Frontend/document-upload.test.mjs
```

La prueba de integración `schema-workflow.php` se ejecuta en el quinto trabajo de CI, contra un MySQL temporal creado desde cero con las migraciones. Las demás pruebas de [tests/Integration](tests/Integration) se ejecutan expresamente contra MySQL y el entorno local preparado. Cada ficha describe los requisitos y las verificaciones realizadas. Antes de ejecutarlas, consultar su documentación y evitar ediciones manuales concurrentes sobre los datos que contrastan.

## Documentación y estado del proyecto

- [Índice de documentación](docs/README.md).
- [Memoria de producto y arquitectura](docs/memoria.md).
- [Lista de tareas y ampliación del alcance](docs/Tareas.md).
- [Usuarios y recorridos de demostración](docs/demo.md).
- [Uso de la API](docs/api/README.md) y [contrato OpenAPI](openapi/openapi.yaml).
- [Reglas visuales](docs/diseno/reglas-visuales.md) y [componentes y compilación Sass](docs/diseno/platform-design.md).
- [Reglas del repositorio](AGENTS.md).

`develop` es la rama de integración. Cada tarea se desarrolla en `feature/<tarea>` y vuelve mediante PR. `main` está protegida; no se trabaja ni se hace push directo sobre ella.

### Límites y próximos pasos

La aplicación móvil, la presentación de impuestos, los bancos, el plan contable, la emisión de facturas y los cobros de la plataforma quedan fuera del alcance implementado. El IPSI se introduce manualmente; los cálculos no determinan la validez fiscal de un documento. La API todavía no expone edición de perfiles fiscales.

Están planificados, entre otros, la ampliación de administración de asesorías y clientes independientes, home y landings comerciales, planes y cuotas, registros exentos, auditoría OWASP, versión de producto 1.0.0 y publicación automatizada en Hostinger. No se consideran disponibles por aparecer en la planificación.

Las fichas de [rediseño](docs/sdd/platform-design.md) y [subida unificada](docs/sdd/document-upload-zone.md) señalan comprobaciones manuales pendientes, como lector de pantalla, ampliación al 200 % y cámara de móvil real. Las revisiones realizadas no constituyen una declaración de conformidad completa de accesibilidad ni una auditoría integral de seguridad.
