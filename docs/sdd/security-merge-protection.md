# Protecciones de merge y comprobación de bloqueo · cierre de F13

## Objetivo

Aplicar la autorización del usuario del 15/11/2026: exigir PR y seis checks de GitHub Actions correctos en main y develop, rama actualizada, sin bypass, conservando bloqueo de borrado y force-push. Rama `feature/security-merge-protection` desde develop `b3155c5`, tras PR #59.

## Cambios planteados

- Ampliar el ruleset existente a ambas ramas y añadir los seis checks exactos, vinculados a GitHub Actions (app 15368). Conservar métodos de merge y cero aprobaciones obligatorias del TFM individual.
- Guardar la configuración revisable en docs y comprobar las reglas efectivas de main/develop después de aplicarla.
- Crear una rama y PR temporales desde develop con una prueba sintética de PHPUnit que falle expresamente. No modificar código de negocio ni permisos.
- Observar el check fallido y el estado de merge bloqueado en GitHub; no intentar integrar ni saltarse reglas. Cerrar el PR y retirar su rama remota tras la comprobación.
- Actualizar evidencia, ficha y seguimiento de F13 con solo resultados observados. No marcar cerradas las limitaciones de producción ni F08.

## Fuera de alcance

Merge a main/develop, cambios fiscales/productivos, publicación 1.0.0, despliegue, aprobaciones adicionales de revisores y bypass administrativos. config/reference.php conserva sus cambios locales previos y queda fuera.

## Criterios de aceptación

- Reglas efectivas para ambas ramas: PR, checks PHP-CS-Fixer, PHP-Stan, PHPUnit, Esquema MySQL, OpenAPI y Seguridad web y API; source app 15368; strict/up-to-date; sin bypass; borrado y force-push bloqueados.
- PR temporal muestra PHPUnit fallido por el caso deliberado y GitHub indica merge bloqueado. No se realiza ningún merge.
- PR temporal cerrado sin integrar, rama remota retirada y prueba fallida ausente de develop/main y de la rama de documentación.
- Documentación y evidencia permiten revisar configuración, ejecución y resultado. F13 se cierra solo en el alcance comprobado, manteniendo otros pendientes explícitos.

## Comprobado

Aplicado y verificado el 15/11/2026. [Configuración aplicada](../security/required-checks-ruleset.json), [evidencia](../security/evidence/f13-merge-protection.json).

- PR #59 ejecutó correctamente los seis checks, incluidos los 19 workflows de seguridad con PHP 8.4 en GitHub (run 43771892654).
- Ruleset 26658888 ampliado a main/develop, activo y sin bypass; se consultaron las reglas efectivas de ambas ramas. Exige los seis nombres exactos de GitHub Actions 15368, PR y rama actualizada. Conserva bloqueo de borrado/force-push, cero revisiones adicionales y métodos de merge existentes.
- PR temporal [#60](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/60): un único archivo de prueba, PHPUnit **450 pruebas / 1.287 aserciones / 1 fallo deliberado**, otras cinco comprobaciones correctas. GitHub devolvió `mergeStateStatus=BLOCKED` y `mergeable=MERGEABLE`: no había conflicto de código y el merge estaba bloqueado.
- Se cerró sin merge a las 23:02:37 UTC y se eliminó su rama remota (consulta posterior 404). Develop conserva `b3155c5`; el archivo fallido no existe en develop ni en la rama de documentación. No se intentó mergear, forzar push ni usar bypass.

El bloqueo se observó en un PR a develop; main comparte el mismo ruleset efectivo verificado, sin abrir un PR contra main. La rama de documentación no cambia PHP ni workflows: no se repite la batería de aplicación. F13 verificada en este alcance; F08 y las comprobaciones de producción continúan pendientes. La puntuación histórica no cambia.
