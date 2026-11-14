# Footer público · tarea 36

## Objetivo

Footer compartido de páginas públicas y de acceso, con marca, públicos, precios, información legal y copyright anual de Daniel Correa Placeres.

## Fuera de alcance

Contenido de las páginas de destino, políticas y precios; cambios en navegación privada de trabajo.

## Criterios de aceptación

- Componente único, legible en móvil, enlaces con foco y copyright actualizado en Canarias.
- No introducir rutas Twig inexistentes al renderizar esta rama.
- Mostrar enlaces únicamente a rutas públicas disponibles; al integrar 34, 37 y 38 aparecen sus destinos sin enlaces rotos entre merges.

## Comprobado

- Sass y dos plantillas correctas; footer renderizado por HTTP en home, con copyright y enlaces. Estilos separados del espacio privado de trabajo.
- Extensión Twig que resuelve únicamente rutas `commercial_` existentes: no crea enlaces rotos si falta una página del lote. El enlace de precios aparecerá al integrar su ruta de la tarea 38.
