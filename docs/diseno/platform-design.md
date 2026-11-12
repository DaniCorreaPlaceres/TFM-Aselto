# Interfaz de la plataforma

La tarea 26 utiliza Bootstrap 5.3.9 personalizado con Sass/SCSS, tipografía Inter local, la marca oficial y la paleta aprobada. El logo y el icono se utilizan sin redibujarlos. No hay datos fiscales ficticios presentados como reales en la home comercial.

## Componentes

- Navegación compartida por papel, con estado activo y acceso a los datos fiscales. En escritorio es lateral; hasta 991 px empieza contraída y se despliega con un botón de tres líneas junto al logo. El botón indica su estado, funciona con teclado y el menú se cierra con Escape, al navegar y al cambiar de tamaño. Sin JavaScript los enlaces siguen visibles.
- Fondo claro, superficies blancas, espacios generosos y tipografía de alto contraste. Las cifras usan números tabulares; los gastos no se pintan de rojo.
- Formularios con etiquetas persistentes, controles amplios, errores explícitos y foco visible. El original documental y los campos se muestran juntos en escritorio y apilados en móvil.
- PDF.js local para ver páginas y texto extraíble, con enlace al original y mensaje alternativo si falla. Se conservan las rutas y permisos de descarga de la aplicación.
- DataTables con búsqueda, filtros, ordenación, selector de tamaño y paginación. La tabla HTML sigue disponible sin JavaScript. Se aplica a documentos, bandeja, clientes, asesorías, equipo, conversaciones y registros incluidos del trimestre.

La pantalla Mi asesoría muestra el nombre, teléfono, dirección y correo del admin activo de la asesoría vinculada, con acceso a mensajes. No expone datos de otra asesoría ni inventa datos ausentes.

## Exportaciones

Excel y CSV exportan todas las filas cargadas que cumplen los filtros, también las de otras páginas. No consultan datos adicionales ni incluyen columnas de acciones. Los filtros de la bandeja del servidor siguen delimitando los registros disponibles.

Excel recibe importes sin símbolos como valores numéricos; CSV usa UTF-8 con BOM, separador de punto y coma y coma decimal. Las fechas con valor estructurado usan ISO. Los campos de texto se protegen frente a fórmulas de hoja de cálculo.

Los recursos se sirven localmente. DataTables 3.1.5, Buttons 4.1.3, Responsive 4.1.1, jQuery 3.7.0, JSZip 3.10.1 y PDF.js 6.5.438 están fijados en las copias de `public/vendor/`, con licencias y procedencia. Inter está en `public/fonts/`. Bootstrap y SassBundle se fijan mediante Composer y composer.lock.

## Compilación

`assets/styles/app.scss` personaliza Bootstrap y carga los componentes base de `_foundation.scss`. AssetMapper integra el CSS resultante con el punto de entrada `assets/app.js`. Tailwind se ha retirado para evitar dos compilaciones de estilos.

Con Docker, el servicio `styles` detecta cambios mediante sondeo, también en los archivos compartidos desde Windows:

`docker compose up -d styles`

Compilación manual:

`docker compose exec -T --user www-data app php bin/console sass:build`

El arranque de PHP-FPM compila Sass como www-data. Para una entrega, ejecutar `sass:build --env=prod` antes de `asset-map:compile --env=prod`. En producción el CSS se comprime y no incluye mapas de fuentes. CI comprueba la compilación Sass y AssetMapper en el entorno test.

Después de probar `asset-map:compile` en desarrollo, retirar sus archivos generados en `public/assets/` para volver a servir cambios dinámicos. No se deben versionar esos archivos.

## Validación y límites

La ficha [platform-design](../sdd/platform-design.md) recoge las pruebas ejecutadas. El contraste de texto principal, secundario y acción principal supera 4,5:1 en los pares medidos. Esto no acredita conformidad completa con WCAG: quedan pendientes una revisión con lector de pantalla, ampliación al 200 % y pruebas con usuarios. Tampoco se ha verificado manualmente toda la variedad de PDF de varias páginas; el visor incluye controles de página y alternativa de apertura del original.
