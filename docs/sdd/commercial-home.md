# Home comercial

## Objetivo

Presentar Aselto como solución de control de facturas y documentación para autónomos, empresas y asesorías, con beneficios y acciones diferenciados y una portada visual coherente con la marca.

## Fuera de alcance

Landings específicas, precios, planes, footer general y páginas legales (tareas posteriores). Nuevas funciones de negocio, cambios de registro y ampliación de la campaña visual de IA.

## Criterios de aceptación

- Mensaje principal comprensible, con sección para autónomos/empresas y otra para asesorías.
- Beneficios reales de documentos, revisión humana, comunicación y cifras; sin promesas de validación fiscal automática, emisión o presentación de impuestos.
- Acciones conectadas a rutas existentes o secciones de la página, sin enlaces vacíos ni alta pública de asesorías inventada. Crear cuenta como botón secundario.
- Home con sesión muestra «Tu cuenta» y lleva a la cuenta actual.
- Marca oficial, Sass y componentes existentes; lectura y navegación adaptadas a móvil, teclado y texto ampliado.
- Verificar renderizado, enlaces y compilación, diferenciando pruebas ejecutadas de revisiones pendientes.

## Comprobado

- Home con mensaje documental, ejemplo de bandeja con datos ficticios, bloques para negocios y asesorías, beneficios y proceso en tres pasos. Marca oficial e iconos locales reutilizados; nuevo parcial Sass con estilos limitados a `.commercial-page`.
- Acciones de negocio al registro existente; asesorías a explicación del alta por invitación y entrada existente. Navegación interna sin enlaces vacíos. «Crear cuenta» secundario y «Tu cuenta» hacia `/cuenta` con sesión, comprobados por HTTP con un acceso ficticio de demostración.
- Un único `main`. Corregido el salto al contenido para situar su destino después de la cabecera comercial y evitar la interceptación de Turbo: con teclado se muestra «Ir al contenido» con foco y Enter lleva el foco al elemento `MAIN#main-content`.
- Revisión visual en navegador integrado de escritorio y a 320 px. Sin desbordamiento horizontal; enlaces de navegación y acceso de asesorías comprobados. Controles visibles medidos con altura mínima de 48 px. Vista temporal móvil retirada y portada de escritorio dejada abierta para revisión.
- Contrastes calculados de las combinaciones nuevas: petróleo sobre fondo 5,96:1; texto secundario sobre blanco 7,58:1; tonos claros sobre bloque oscuro 9,83:1 y 11,00:1; estados de ejemplo 6,42:1 y 6,38:1. No equivalen a una auditoría de accesibilidad.
- Compilación Sass correcta y AssetMapper con 26 recursos. Retirado exclusivamente `public/assets` generado por la prueba para conservar el desarrollo dinámico. Home, entrada, registro y cuenta con respuesta 200. Sin errores de consola observados durante la revisión.
- No se han añadido dependencias, funciones de negocio, precios, landings ni footer general. No se ha ejecutado revisión con lector de pantalla, zoom real al 200 %, dispositivos físicos o CI remoto. No se añaden pruebas que repliquen el texto de la plantilla.
- Tarea 33 y memoria actualizadas. `config/reference.php` conserva sus cambios previos y queda fuera de la tarea.
