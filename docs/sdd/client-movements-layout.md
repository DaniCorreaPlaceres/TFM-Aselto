# Ingresos y gastos · tarea 40

## Objetivo

Mejorar la distribución del trimestre: selector compacto, resumen visible, movimientos, pendientes y gestión/histórico diferenciados. Facilitar añadir ingresos y gastos desde esta pantalla mediante el flujo documental existente.

## Fuera de alcance

Inicio del cliente, nuevos cálculos fiscales, registros sin archivo, exenciones, reglas de cierre, cambios de permisos, cuotas o API y nuevas librerías.

## Criterios de aceptación

- Acciones Añadir ingreso y Añadir gasto solo para el propietario. Subida y revisión existentes con tipo preseleccionado, modificable y sin confirmar automáticamente. Se conservan CSRF, cuota, validación y cierres.
- Resumen y estado del periodo claros; movimientos con DataTables, filtro por tipo, búsqueda y exportaciones existentes. Los filtros del listado no alteran las cifras del trimestre.
- Pendientes visibles y enlazados según permisos, incluidos documentos sin fecha; histórico y cierre diferenciados sin esconder avisos de bloqueo.
- Funciona con actualización en vivo y sin JavaScript. Contenido común coherente para cliente y asesoría; ningún acceso nuevo a documentos ajenos.
- Interfaz adaptable a móvil y teclado, sin cambios del inicio ni nuevas dependencias.
- Comprobar recorrido de ingreso/gasto, periodo abierto/cerrado, pendientes/histórico, plantillas, Sass, regresión y revisión visual; anotar solo evidencia ejecutada.

## Comprobado

- 336 pruebas PHPUnit / 820 aserciones correctas; PHPStan sin errores; formato PHP y 61 plantillas Twig válidas; Sass compilado.
- Flujo HTTP documental: 39 comprobaciones con originales sintéticos, incluidos ingreso y gasto, conservación de la elección tras un error, preselección sin modificar la propuesta persistida, confirmación explícita, cuota sin consumo extra y ausencia de cambios por query en un registro confirmado. Archivos y datos sintéticos retirados.
- Trimestres: 43 comprobaciones de cierre, reapertura y permisos; actualización en vivo: 36 comprobaciones, incluidos fragmentos con cifras guardadas. Datos existentes sin cambios tras finalizar.
- Revisión visual con cliente independiente de demostración: escritorio y 320 px, acciones de 51/53 px de alto, resumen, pendientes, búsqueda sin coincidencias con cifras intactas, filtro Gasto, trimestre cerrado e histórico con cierre anterior.
- Detectado y resuelto desmontaje de DataTables al navegar a anclas: navegación interna enfoca y desplaza la sección sin generar una visita Turbo que desactive los componentes. Sin JavaScript se conservan los enlaces nativos y la tabla admite desplazamiento horizontal dentro de su contenedor. Dos pruebas Node específicas comprueban navegación local y respeto de enlaces externos al componente/clics modificados; 30 pruebas frontend correctas en total.
- Navegación móvil tras la corrección: ancho 320 px, contenido de 305 px y un listado DataTables activo al cambiar entre Resumen y Movimientos. No se ha realizado revisión con lector de pantalla ni sesión completa con JavaScript desactivado; las funciones avanzadas del listado requieren JavaScript.
- Inicio del cliente, dominio, permisos, casos de uso, cuotas y contrato API no modificados. Sin migraciones ni dependencias nuevas. Captura local en `var/movements-review.png`.
