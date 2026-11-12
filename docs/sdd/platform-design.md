# Rediseño visual de la plataforma

## Objetivo

Renovar toda la interfaz con aspecto SaaS, Bootstrap 5 personalizado mediante Sass/SCSS y la marca oficial. Unificar navegación, tipografía, formularios, documentos, trimestres, mensajes y listados.

## Decisiones aprobadas

- Bootstrap 5 y Sass/SCSS integrados con AssetMapper.
- DataTables con Bootstrap 5, Responsive y Buttons para búsqueda, filtros, ordenación, paginación y exportación Excel/CSV. Exportación de filas filtradas cargadas de todas las páginas.
- Mantener el logo y los iconos oficiales, paleta petróleo/azul y claridad de los bocetos. Gastos en tonos neutros; rojo reservado a errores.
- PDF.js de Mozilla servido localmente, aprobado durante la tarea, para visualizar el original junto a los campos sin depender del visor nativo del navegador.
- Ajuste solicitado: en móvil y tablet la navegación empieza contraída y se despliega con un botón de tres líneas. Debe indicar su estado, funcionar con teclado y cerrarse con Escape o al navegar. En escritorio se conserva la barra lateral.
- Ajuste solicitado en Mi asesoría: el cliente vinculado consulta nombre, teléfono, dirección y correo del admin de su asesoría. Sin vinculación se mantiene el estado vacío; comprobar que no se muestran datos de otra asesoría.

## Fuera de alcance

Cambios de permisos, cálculos, reglas de negocio, nuevos endpoints o captura documental unificada (tarea 27).

## Criterios de aceptación

- Home comercial, acceso y pantallas de todos los papeles comparten componentes y jerarquía coherentes.
- Diseño adaptable a móvil, controles cómodos, foco visible y etiquetas persistentes. Sin recortes al ampliar texto ni scroll horizontal general a 320 px.
- Listados funcionan sin JavaScript como tablas HTML; mejoras se montan y desmontan con Stimulus/Turbo sin duplicarse.
- Exportaciones sin columnas de acciones, con números y fechas coherentes, protección de fórmulas y datos limitados al listado autorizado.
- Recursos locales y versiones fijadas, sin enviar datos fiscales a servicios externos.
- Compilación Sass reproducible en Docker y documentada. Regresión de los recorridos existentes y revisión visual en escritorio y móvil; anotar solo pruebas ejecutadas.

## Comprobado

- Rama `feature/platform-design`, creada desde develop, y ficha previa a la implementación.
- Bootstrap 5.3.9 y SassBundle 0.10.0 instalados mediante Composer; se retiró Tailwind y su configuración. Tema en app.scss y componentes base, Inter y marca oficial locales. Servicio Docker styles con sondeo y compilación inicial como www-data.
- Navegación compartida por papel, acceso a perfiles conservado, home comercial, acceso, cliente, asesoría y administración renovados por las plantillas base. Revisión documental con original y campos juntos, apilados en móvil.
- DataTables 3.1.5, Buttons 4.1.3, Responsive 4.1.1, jQuery 3.7.0 y JSZip 3.10.1 locales. Controlador en contenedor estable, desmontaje para Turbo y filtros sobre todos los registros iniciales. Un primer montaje sobre la propia tabla produjo un ciclo; corregido, se recuperó la pestaña con ayuda del usuario y se comprobó el montaje estable.
- PDF.js 6.5.438 local, aprobado por el usuario durante la tarea. Documento de ejemplo renderizado y texto extraíble; a 320 px mantiene la página sin desbordamiento. Recursos de fuentes, cmaps y wasm incluidos; sin envíos a CDN en ejecución.
- PHPUnit: 295 pruebas y 692 aserciones correctas. JavaScript: 6 pruebas de exportación y 9 de actualización del trimestre correctas. Sintaxis de los dos controladores nuevos correcta.
- Regresión web del trimestre: 43 comprobaciones correctas; mensajería: 37. Se conservaron campos, rutas, CSRF y casos de uso. No hay cambios del dominio ni migraciones.
- PHPStan nivel 6, contenedor, Twig y OpenAPI correctos. Compilación Sass y AssetMapper en test ejecutada; se añadieron a CI. Compilación Sass prod y ejecución como www-data comprobadas. Artefactos públicos generados durante la comprobación retirados para restaurar cambios dinámicos en desarrollo.
- Navegador: acceso de admin y cliente, listado, búsqueda, paginación, PDF y navegación. CSV de bandeja filtrada por Sociedad incluye dos filas; Excel correspondiente contiene importes numéricos, comprobados en el XML del XLSX. CSV de documentos incluye siete registros al mostrar cinco por página. Los tests verifican coma decimal CSV y protección de fórmulas.
- Revisión visual de acceso, home comercial, inicio de cliente, listado y documento a 1440 px y 320 px. En home, inicio, bandeja y revisión medidos a 320 px: contenido de 305 px con barra de desplazamiento, sin desbordamiento horizontal general. La navegación móvil se sustituyó después por el desplegable solicitado por el usuario.
- Acceso por Tab al enlace Ir al contenido con foco visible, y aviso de archivo obligatorio visible en móvil sin enviar ni crear documento. Contrastes medidos: texto principal 15,61:1; secundario 7,58:1; blanco sobre acción 6,29:1; estado suave 7,10:1; borde de control sobre blanco 4,76:1 y foco sobre blanco 4,86:1.
- Licencias y procedencia de recursos conservadas. Guía en docs/diseno/platform-design.md, decisión de stack en memoria y tarea 26 marcada como implementada.

## Pendiente de comprobación manual

- Ampliación de texto al 200 %, lector de pantalla y pruebas con usuarios. No se declara conformidad completa WCAG.
- PDF de varias páginas, PDF con todas las variantes de fuentes y formatos de imagen, y todos los estados visuales de cada formulario.

## Cómo probar

Con Docker actualizado, abrir la home y entrar con los accesos de docs/demo.md. Revisar inicio, documentos, trimestre, mensajes, clientes y equipo. En un listado usar búsqueda y filtro, mostrar cinco registros por página y exportar Excel/CSV. Abrir un documento para comprobar el visor. Reducir la ventana a móvil y usar Tab para recorrer los controles.

`docker compose up -d styles` activa la actualización de Sass. Los cambios siguen sin commit hasta revisión del usuario.

## Ajuste del menú móvil comprobado

A 320 px empieza cerrado, abre al pulsar o con Enter, cierra con Escape devolviendo el foco al botón y cierra al navegar a Documentos. aria-expanded cambia correctamente. No hay desbordamiento horizontal general (305 px de contenido para viewport de 320 px). A 1440 px se muestra la navegación lateral y se oculta el botón. Sintaxis JavaScript, compilación Sass y Twig de la base correctos.

## Contacto de Mi asesoría comprobado

Ficha con nombre, teléfono enlazado, correo del admin activo identificado como tal y dirección de la asesoría vinculada. Sin vinculación o con datos incompletos no se inventa contacto. Caso de uso con comprobación de usuario activo, papel cliente y coincidencia de asesoría entre usuario y perfil. Pruebas de aislamiento, rol, activación, vinculación inconsistente y datos ausentes. PHPUnit final: 298 pruebas y 712 aserciones correctas; PHPStan, contenedor, Twig y formato de los cuatro archivos PHP en copias LF correctos. Ficha verificada en navegador con el cliente demo de Sol y a 320 px, sin desbordamiento general.

## Ajustes finales de la home

Por petición del usuario, Crear acceso pasa a Crear cuenta y se presenta como botón secundario de borde petróleo junto a la acción principal. Compilación Sass y Twig de la home correctos.
