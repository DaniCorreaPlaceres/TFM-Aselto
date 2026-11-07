# Memoria de Aselto

Versión 0.1. Documento de partida del TFM. Vive en `docs/`, junto al resto de la documentación. El índice está en [docs/README.md](README.md). Cada tarea posterior se especifica en `docs/sdd/` antes de implementarse. Esta memoria no se reescribe para contar el trabajo del día: si una decisión cambia, se actualiza aquí y la tarea lo señala.

## 1. Producto

Aselto es una plataforma para simplificar la gestión económica y documental de autónomos y sociedades, y el trabajo con su asesoría.

El autónomo o la sociedad reúne ingresos, gastos, facturas y justificantes en un mismo sitio. La plataforma lee el documento y propone los datos. Una persona los revisa y los confirma. La asesoría consulta esa información, pide lo que falta y valida. El cliente y la asesoría ven el trimestre con cifras actualizadas.

El nombre junta asesoramiento y evolución. El eslogan es **Tus finanzas bajo control**. El dominio previsto es aselto.com. La marca oficial es el logotipo de `docs/marca/aselto-logo-eslogan-v2.png` y el icono de `docs/marca/icono.png`. No se redibuja la palabra ni la A.

Va dirigido a autónomos, sociedades y equipos de asesoría en España. La aplicación móvil queda fuera de este TFM. La API se diseña para que esa aplicación pueda usarla después.

## 2. Qué entra en el TFM

Entra una web en la que conviven varias asesorías, aisladas entre sí.

Papeles:

- Superadmin de la plataforma. Da de alta asesorías y también un cliente sin asesoría. No hay planes ni cobros.
- Admin de una asesoría. Invita gestores y da de alta clientes.
- Gestor. Revisa documentos, pide justificantes, valida y cierra el trimestre.
- Cliente. Un acceso por autónomo o por sociedad. Puede usar la plataforma solo o vinculado a una asesoría. Sube documentos y consulta su actividad.

Recorrido principal:

1. El cliente elige hacer una foto o subir un archivo.
2. Un extractor propone fecha, tercero, NIF, base, tipo de IVA, cuota, total y si es ingreso o gasto.
3. El cliente corrige, guarda un borrador o confirma el envío. La IA no confirma sola.
4. El gestor valida, corrige, rechaza o pide otro documento.
5. Solo lo validado entra en el trimestre.
6. Cliente y gestor ven las cifras. El gestor puede cerrar el trimestre y dejarlas congeladas.

Cifras, iguales para autónomo y sociedad, calculadas solo con movimientos validados:

- Ingresos: suma de bases de ingresos.
- Gastos: suma de bases de gastos.
- IVA repercutido e IVA soportado.
- Resultado: ingresos menos gastos.
- IVA del periodo: repercutido menos soportado.

Estados de un documento: `subido`, `extrayendo`, `borrador`, `validado`, `rechazado`.

Queda fuera: la app, los modelos oficiales de Hacienda, la presentación de impuestos, los bancos, el plan contable, el cobro y la factura de la licencia de Aselto, y la emisión de facturas. La licencia existe en el producto, pero aquí se supone que el cliente puede registrarse directamente.

## 3. Stack

| Capa | Elección |
| --- | --- |
| Lenguaje | PHP 8.4 o superior. En esta máquina y en Docker, PHP 8.5. |
| Framework | Symfony 7.4 |
| Base de datos | MySQL 8.4 |
| Persistencia | Doctrine ORM y migraciones |
| Web | Twig, Stimulus, Turbo y AssetMapper |
| Estilos | Hoja propia con la paleta de esta memoria. Tailwind queda disponible en el proyecto y no es obligatorio para maquetar. |
| Tiempo real | Mercure, cuando el trimestre en vivo se implemente |
| Trabajos en segundo plano | Symfony Messenger con transporte Doctrine |
| API | JSON en `/api/v1`, misma lógica que la web, autenticación JWT |
| Web | Sesión de Symfony |
| Ficheros | Disco local en `var/storage`, detrás de un puerto sustituible |
| Lectura de documentos | Puerto `ExtractorDocumentos`. Adaptador de visión si hay clave. Adaptador de ejemplo si no la hay. |

La web y la API llaman a los mismos casos de uso. La app futura solo tendrá que consumir la API.

## 4. Herramientas

- Git y GitHub. Repositorio: <https://github.com/DaniCorreaPlaceres/TFM-Aselto>.
- Docker Desktop para el entorno local.
- Composer.
- `gh` para el remoto y las reglas de rama.
- OpenAI, solo como adaptador opcional de extracción. Sin clave, el flujo se enseña con el adaptador de ejemplo.
- PHPUnit, PHPStan, PHP-CS-Fixer y la validación OpenAPI. En cada pull request hacia `develop` o `main` los ejecuta `.github/workflows/comprobaciones.yml`.
- Node, cuando haga falta construir estilos.

Método: especificación corta antes de cada tarea. La ficha vive en `docs/sdd/<tarea>.md` y contiene objetivo, fuera de alcance y criterios de aceptación. La rama de esa tarea sale de `develop`. Al terminar se anota solo lo que se ha comprobado.

## 5. Arquitectura

Monolito modular. El dominio no conoce Doctrine, HTTP ni el proveedor de IA.

```mermaid
flowchart TB
  subgraph ui [UI]
    Web[Twig y Stimulus]
    Api[API JSON v1]
  end
  subgraph aplicacion [Aplicacion]
    Casos[Casos de uso]
  end
  subgraph dominio [Dominio]
    Modelo[Modulos de negocio]
  end
  subgraph infra [Infraestructura]
    Db[MySQL]
    Ficheros[Almacen]
    Ia[Extractor]
    Cola[Messenger]
    Vivo[Mercure]
  end
  Web --> Casos
  Api --> Casos
  Casos --> Modelo
  Casos --> Db
  Casos --> Ficheros
  Casos --> Ia
  Casos --> Cola
  Casos --> Vivo
```

Cada módulo tiene cuatro carpetas: `Domain`, `Application`, `Infrastructure` y `Ui`.

- `Shared`. Reloj, identificadores, dinero, errores de dominio y puertos que usan varios módulos.
- `Identidad`. Usuario, papel y acceso.
- `Asesoria`. Asesoría y su equipo. Una asesoría no ve datos de otra. Un cliente sin asesoría solo ve los suyos.
- `Cliente`. Ficha de autónomo o sociedad. La asesoría y el gestor son opcionales.
- `Documento`. Fichero, extracción y estados.
- `Finanzas`. Movimiento validado, consulta del trimestre y cierre.
- `Comunicacion`. Solicitudes y mensajes entre cliente y asesoría.

Orden de construcción acordado:

0. Esta memoria. Hecha.
1. GitHub, ramas y Docker. Hecho. `main` está protegida, `develop` integra, y cada pull request ejecuta las cuatro comprobaciones. El entorno local arranca y `GET /salud` responde.
2. Estructura Symfony. Hecha. Los siete módulos tienen `Domain`, `Application`, `Infrastructure` y `Ui`. El dominio no se registra como servicio.
3. A partir de aquí, la plataforma, tarea a tarea. Ya están el acceso, la home comercial, el alta de asesorías y de su equipo, y el alta de clientes. El primer recorrido presentable sigue siendo el de los bocetos: inicio del cliente, alta de documento, revisión humana y bandeja de la asesoría.

## 6. Especificaciones de producto

### Identidad visual

Profesional y cercano, con mucho espacio. Una acción principal por pantalla. Comprensible para alguien con poca experiencia digital.

Paleta:

- Fondo `#F7F9FA`
- Superficie `#FFFFFF`
- Texto `#14243B`
- Texto secundario `#475569`
- Acción `#006B70` con texto blanco
- Selección `#E3F1F1`
- Borde de control `#64748B`
- Error `#B42318`
- Aviso `#854D0E`
- Confirmación `#166534`

Los gastos no se pintan como errores. No hay degradados decorativos.

Tipografía sans serif, cuerpo 18 px, texto auxiliar 16 px, títulos 24–32 px, interlineado cerca de 1,5. Importes en formato español: `1.250,00 €`. Controles de al menos 48 px en móvil, con etiqueta visible.

Menú del cliente, en este orden: Inicio, Ingresos y gastos, Documentos, Mi asesoría, Ayuda. En el alta de un documento los pasos son añadir, revisar y confirmar. Los verbos son «Añadir documento», «Hacer una foto», «Subir archivo», «Guardar borrador» y «Confirmar registro».

No se dice «Todo claro» ni «Todo al día» si hay algo pendiente. La IA propone datos y no promete deducibilidad ni validez fiscal. La confianza no se inventa como un porcentaje.

El inicio muestra el saludo, la acción de añadir un documento, los ingresos y gastos del periodo y lo que está pendiente de revisión.

### Reglas de negocio ya cerradas

- El superadmin no pertenece a una asesoría. El admin y el gestor sí.
- Un cliente puede registrarse directamente, en principio sin asesoría. El superadmin también puede dar de alta un cliente sin asesoría.
- El admin de una asesoría da de alta a los clientes de la suya. Si un cliente está vinculado, un gestor solo ve los de esa asesoría.
- El cliente puede corregir el borrador. El gestor puede corregirlo al validar.
- Si el trimestre de esa fecha está cerrado, no se valida otro movimiento dentro de él.
- La foto en el móvil usa la cámara del dispositivo. En escritorio, el mismo paso admite un archivo.
- Tipos de IVA previstos: 0, 4, 10 y 21. La cuota se calcula desde la base y el tipo cuando la persona confirma.
- Archivos admitidos: PDF, JPEG, PNG y WebP.

### API prevista

Prefijo `/api/v1`. La sesión web no sustituye a esta API. El contrato vive en `openapi/openapi.yaml`: OpenAPI 3.0.3, título «Aselto API», versión 0.1.0. Hoy declara el documento y deja las rutas vacías. Las operaciones de producto se añaden en la tarea de la API. La primera comprobación de entorno es `GET /salud`, que no forma parte del producto.

## 7. Ramas y entorno

`main` está protegida. No se trabaja sobre ella ni se le hace push directo. Recibe una versión cuando se promueve desde `develop` mediante pull request.

`develop` es la rama de integración. Cada tarea crea una subrama `feature/<tarea>` desde `develop` y vuelve a `develop` mediante pull request.

Cada pull request y cada push a `develop` o `main` ejecuta `.github/workflows/comprobaciones.yml` con PHP 8.4, la versión mínima del proyecto. Los cuatro trabajos son:

- **PHP-CS-Fixer.** Revisa el estilo en seco, con las reglas `@Symfony`. No reescribe los finales de línea.
- **PHP-Stan.** Analiza `src`, `tests` y `bin/validate-openapi.php` en el nivel 6.
- **PHPUnit.** Ejecuta la suite de `tests/`.
- **OpenAPI.** Valida `openapi/openapi.yaml` con `bin/validate-openapi.php`.

Entorno local:

```bash
docker compose up --build
```

La web queda en <http://localhost:8080>. MySQL publica el puerto 3306, con base `aselto`, usuario `aselto` y contraseña `aselto`. Mercure publica el puerto 9080. Esas credenciales son solo de desarrollo.

Dentro de los contenedores la base se llama `database`. Desde la máquina anfitriona, `127.0.0.1`.

La imagen de PHP instala `intl`, `pdo_mysql`, `zip` y `bcmath`. OPcache se activa en `docker/php/php.ini`. El script `docker/php/entrada.sh` se guarda con finales de línea Unix; `.gitattributes` lo mantiene así.

`GET /salud` no forma parte del producto. Ya está comprobado: responde 200 con `{"estado":"ok","base_de_datos":"ok"}`. MySQL 8.4 queda sano en el puerto 3306. Mercure responde en el 9080.

`/` es la home comercial y es pública. Usa el logotipo oficial y se dirige a autónomos, sociedades y asesorías. Explica que el documento se añade, los datos se revisan y el trimestre se actualiza. La única acción es Entrar, hacia `/entrar`. Si ya hay sesión, esa acción se llama Tu acceso y abre `/cuenta`. No hay precios en esta página. Quien no tiene sesión ve también «Crear acceso», hacia `/registro`.

El acceso de la web es la sesión de Symfony. `GET /entrar` pide el correo y la contraseña. Si el acceso es correcto, `/cuenta` muestra el papel. Salir cierra la sesión. `GET /salud` sigue abierto sin entrar.

Tras migrar, `php bin/console app:seed-dev-access` crea la asesoría de prueba y cuatro accesos locales. La contraseña es `aselto-dev` y solo vale en desarrollo:

- `superadmin@example.test`, sin asesoría
- `admin@example.test` y `gestor@example.test`, en «Asesoría de prueba»
- `cliente@example.test`, sin asesoría

El superadmin abre `/asesorias` desde su acceso. Da de alta una asesoría con nombre, correo del admin y contraseña inicial. No hay planes ni cobros, y no se envía ningún correo: esa persona entra con la contraseña que se le ha puesto. El admin abre `/equipo`, ve solo el equipo de la suya e invita a un gestor del mismo modo. Un gestor no abre esas pantallas. Una asesoría no ve el equipo de otra. La contraseña inicial tiene al menos 8 caracteres. Un correo que ya existe se rechaza.

`/registro` es público. Crea un cliente, autónomo o sociedad, sin asesoría. El superadmin, en `/clientes`, da de alta otro cliente sin asesoría y ve solo esos. El admin da de alta a los de su asesoría. El gestor ve los de la suya y no puede darlos de alta. Una asesoría no ve los clientes de otra.

## 8. Documentación

Todo el material escrito del proyecto está en `docs/`:

- `memoria.md`, este documento.
- `producto/`, la introducción.
- `diseno/`, las reglas visuales y los bocetos.
- `marca/`, el logotipo y el icono oficiales.
- `sdd/`, la ficha de cada tarea antes de programarla.

## 9. Estructura de código prevista

```text
src/
  Shared/
  Identidad/
  Asesoria/
  Cliente/
  Documento/
  Finanzas/
  Comunicacion/
templates/
assets/
docker/
```

`GET /salud` vive en `src/Controller/HealthController.php`. Cada módulo tiene `Domain`, `Application`, `Infrastructure` y `Ui`. El dominio no se registra como servicio.

La home comercial vive en `src/Controller/CommercialHomeController.php`. Identidad ya tiene el usuario, el papel y el acceso. La tabla es `identity_user`. Asesoría ya da de alta la asesoría y permite invitar a su gestor. La tabla es `advisory_firm`. Cliente ya registra autónomos y sociedades. La tabla es `client_profile`. Siguen sin hacerse el inicio del cliente, los documentos y la bandeja.
