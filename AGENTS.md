# Reglas de trabajo

Se aplican a todo el repositorio. El índice de la documentación está en [docs/README.md](docs/README.md). El producto, el stack y la arquitectura están en [docs/memoria.md](docs/memoria.md).

## Orden

1. La memoria recoge stack, herramientas, arquitectura y especificaciones.
2. GitHub: `main` protegida, `develop` como rama de integración, y el entorno local en Docker.
3. Estructura básica de Symfony.
4. La plataforma, una tarea cada vez.

No se adelanta un punto si el anterior no está cerrado.

## Cada tarea

- Antes de tocar código, plantear los cambios que se van a hacer.
- Antes de programar, crear `docs/sdd/<tarea>.md` con objetivo, fuera de alcance y criterios de aceptación.
- La rama es `feature/<tarea>`, creada desde `develop`. El pull request vuelve a `develop`.
- Si la tarea contradice la memoria, se para y se decide. No se reescribe la memoria para encajar el código.
- Al cerrar, la ficha anota solo lo comprobado. Una prueba no ejecutada sigue pendiente.

## Ramas

- `main` está protegida. No se trabaja sobre ella ni se le hace push directo.
- `develop` es la rama de integración.
- Cada tarea tiene su subrama. No se mezcla otra tarea en la misma rama.
- No se cambian las protecciones del remoto sin pedirlo.

## Documentación

- Toda la documentación vive en `docs/`.
- Una decisión de producto, stack o arquitectura se actualiza en `docs/memoria.md`.
- Una tarea concreta se documenta en `docs/sdd/`, no dentro de la memoria.
- La marca oficial está en `docs/marca/`. No se redibuja el logotipo ni el icono.
- La interfaz sigue [docs/diseno/reglas-visuales.md](docs/diseno/reglas-visuales.md). Si un boceto y esas reglas chocan, mandan la claridad y la legibilidad de las reglas.

## Código

- Antes de tocar código, se plantean los cambios que se van a hacer.
- Arquitectura de la memoria: monolito modular. El dominio no conoce Doctrine, HTTP ni el proveedor de IA.
- La web y la API usan los mismos casos de uso.
- La IA propone datos. Una persona los revisa y confirma. Un registro no queda fiscalmente cerrado por la extracción.
- Cada consulta de negocio comprueba la asesoría y el permiso. Una asesoría no ve datos de otra.
- Los nombres de clases, funciones, métodos, variables y archivos van en inglés.
- Los textos de la interfaz van en español sencillo.
- No se añaden secretos ni datos fiscales reales a ejemplos, seeds ni pruebas.

## Fuera de este TFM

- La aplicación móvil.
- Los modelos oficiales de Hacienda, la presentación de impuestos, los bancos, el plan contable y la facturación de la plataforma.
