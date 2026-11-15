# Versionado y primera publicación

## Versión del producto

La fuente única del valor ejecutable es `app.version`, en `config/services.yaml`. El estado inicial es **1.0.0-dev**: preparación de la primera versión, todavía sin publicación. No se toma la versión de Composer ni se calcula a partir de Git en el servidor; una copia sin `.git` también debe poder identificar su versión.

Consulta desde operaciones, en el directorio de la versión instalada:

```powershell
docker compose exec -T --user www-data app php bin/console app:version
```

En un servidor sin Docker, ejecutar `php bin/console app:version --env=prod --no-debug`. El comando imprime únicamente el valor y termina con código 0. No consulta datos fiscales ni MySQL y no añade una ruta pública. `/salud` conserva su respuesta de salud existente.

Al instalar código nuevo o cambiar `app.version`, reconstruir la caché del entorno antes de consultar: el contenedor compilado de producción conserva parámetros y comandos de la instalación anterior. En el procedimiento de despliegue se incluirá `php bin/console cache:clear --env=prod --no-debug` con el usuario PHP y permisos adecuados; consultar después sobre el directorio de la versión activa. En esta tarea se comprobó esa reconstrucción local, sin desplegar a Hostinger.

La versión identifica una línea de producto, no un commit único: durante `1.0.0-dev` puede haber varios commits. Operaciones debe registrar además el SHA del paquete instalado en el futuro sistema de despliegue. La versión final publicada se asocia a una etiqueta inmutable del commit exacto.

## Criterio de cambios

Se adopta [SemVer 2.0.0](https://semver.org/lang/es/): MAYOR.MENOR.PARCHE. Para Aselto, la compatibilidad publicada comprende el contrato API y los comportamientos documentados de acceso, documentos y trimestres. No implica compatibilidad con cualquier detalle interno o marcado HTML.

- PARCHE: correcciones compatibles, por ejemplo `1.0.1`.
- MENOR: funcionalidades nuevas compatibles, por ejemplo `1.1.0`.
- MAYOR: cambios incompatibles del contrato o comportamiento publicado que requieren adaptación, por ejemplo `2.0.0`.
- `-dev` identifica desarrollo; se puede preparar un candidato numerado, como `1.0.0-rc.1`, si se acuerda una ronda de validación. No se publican etiquetas de desarrollo automáticamente.

Una migración de base de datos no exige por sí sola cambiar la versión mayor. Su impacto en compatibilidad y recuperación debe revisarse y documentarse. No modificar una etiqueta o el contenido de una versión publicada: una corrección se publica con un nuevo número.

## Tres identificadores distintos

- **Producto:** `app.version`, inicialmente `1.0.0-dev`; la publicación objetivo es `1.0.0`, etiqueta Git `v1.0.0`.
- **Contrato:** `info.version` en `openapi/openapi.yaml`, actualmente `0.6.0`. Documenta la evolución del contrato y no se cambia por preparar el producto. La URL `/api/v1` identifica su familia de rutas, no la versión del producto.
- **Esquema:** versiones de migración Doctrine. No se renombran ni se recalculan para hacerlas coincidir con el producto.

Publicar Aselto 1.0.0 no convierte automáticamente el contrato 0.6.0 en un contrato 1.0.0 ni acredita que todos los pendientes de producción estén resueltos. Si se quiere declarar estable otro contrato, se hará en una tarea expresa con su revisión.

## Preparar 1.0.0

1. Completar las verificaciones de producción y CI/CD de las tareas 45/46 y revisar los pendientes de seguridad y legales. La existencia de este procedimiento no los da por cerrados.
2. Crear una rama desde develop. Cambiar solo la fuente ejecutable `app.version` a `1.0.0` y actualizar las [notas](releases/1.0.0.md), con fecha real, alcance y limitaciones finales. No usar una variable de entorno para presentar código de desarrollo como una versión final.
3. Integrar ese PR en develop con sus seis checks correctos. Abrir después el PR de develop a main y exigir sus seis checks, rama actualizada y revisión final. No hacer push directo a main.
4. Tras el merge autorizado, identificar el commit exacto de main y verificar el contenido de versión y las notas de ese commit. Crear la etiqueta anotada solo entonces. No etiquetar el commit anterior de develop ni el HEAD de una rama de trabajo.

## Etiquetado futuro — no ejecutado en esta tarea

Usar una copia limpia y revisar el resultado de cada comando. Si la etiqueta ya existe local o remotamente, detenerse y comprobarla; no usar `--force` ni borrarla para reutilizar el nombre.

```powershell
git fetch origin --tags
git status --short
$releaseCommit = (git rev-parse origin/main).Trim()
git show "${releaseCommit}:config/services.yaml" | Select-String '^    app.version:'
git show "${releaseCommit}:docs/releases/1.0.0.md"
git tag --list v1.0.0
git ls-remote --tags origin refs/tags/v1.0.0
```

Confirmar: copia limpia, commit realmente aprobado en main, `app.version: '1.0.0'`, notas definitivas y etiqueta inexistente. Comprobar también en GitHub los seis checks del commit de publicación; si origin/main avanzó, volver a revisar el SHA elegido.

Solo con la publicación autorizada y esas comprobaciones completas:

```powershell
git tag -a v1.0.0 $releaseCommit -m "Aselto 1.0.0"
git rev-parse 'v1.0.0^{commit}'
git push origin refs/tags/v1.0.0
```

El SHA desreferenciado debe coincidir con `$releaseCommit`. El prefijo `v` pertenece al nombre de la etiqueta, no al valor SemVer del producto. El push de una etiqueta no despliega nada por sí mismo con la CI actual. La automatización de publicación se concreta en la tarea 46.

Una GitHub Release es un registro de publicación adicional a la etiqueta, no un sustituto. Si se decide publicarla, usar las notas definitivas del mismo commit y una etiqueta ya verificada. Esta tarea no crea etiqueta, release ni despliegue.

Después de publicar, la siguiente línea de desarrollo se cambia mediante PR, por ejemplo a `1.0.1-dev` o `1.1.0-dev` según su alcance. Conservar las notas de 1.0.0 y añadir las nuevas por separado. No inventar una fecha de publicación antes de que ocurra.
