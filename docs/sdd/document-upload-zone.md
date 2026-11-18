# Zona única para añadir documentos

## Objetivo

Unificar foto, archivo y arrastrar y soltar en un solo formulario, conservando StoreClientDocument, CSRF y revisión humana.

## Fuera de alcance

Subida múltiple, conversión de formatos, progreso ficticio de extracción y cambios de API o reglas fiscales.

## Criterios de aceptación

- Un único campo file y envío para todos los orígenes. Selector nativo y cámara trasera cuando el dispositivo lo permita.
- Arrastre de un solo archivo; avisos de formato, vacío y límite de 8 MB. El servidor mantiene su validación.
- Nombre y tamaño visibles, opción de cambiar o quitar y vista previa de imagen con liberación de su URL temporal.
- Selección y envío sin JavaScript, etiquetas, teclado y errores accesibles. Diseño móvil.
- Estado de envío sin inventar porcentajes; bloqueo del envío repetido y restauración ante fallo o navegación Turbo.
- Pruebas de validación, selección, limpieza y envío; regresión del recorrido existente. Solo se anota lo comprobado.

## Comprobado

- Rama feature/document-upload-zone creada desde develop y ficha previa a la implementación.
- Un único formulario y campo file para selector, cámara y arrastre. Se conservan endpoint, token CSRF y StoreClientDocument. Sin dependencias nuevas ni migraciones.
- Nombre, tamaño en formato español, vista previa de imagen y retirada de selección. URLs de imagen revocadas al cambiar, quitar, desconectar o preparar la caché Turbo.
- Validación previa de archivo único, vacío, formato y límite de 8000000 bytes. El MIME del navegador es una ayuda de interfaz; el servidor mantiene la comprobación del archivo recibido.
- Envío repetido bloqueado sin deshabilitar el campo que necesita FormData. Estado de envío y recuperación ante fallo, sin porcentajes ni avance de extracción inventados.
- Los errores POST de la página de subida devuelven 422 para que Turbo los renderice. GET conserva 200; respuestas privadas sin caché. La extracción no confirma el documento.
- JavaScript: 13 pruebas nuevas correctas de límites, selección, arrastre, sustitución inválida, cámara, limpieza, vista previa, doble envío y caché; 15 pruebas existentes de trimestre y exportación correctas. Pruebas nuevas añadidas a CI.
- PHPUnit: 298 pruebas y 712 aserciones correctas. Integración HTTP de subida web con extractor sintético: 11 comprobaciones correctas (formulario único, CSRF, errores 422, archivo falso, subida válida a borrador, revisión y descarga exacta). Regresión API documental: 74 comprobaciones correctas.
- Las integraciones revierten sus datos y retiran archivos; documentos existentes sin cambios y ninguna llamada a extracción externa.
- PHPStan nivel 6, contenedor, Twig, Sass y sintaxis JavaScript correctos. Formato Symfony de los dos archivos PHP comprobado en copias LF, sin correcciones pendientes.
- Navegador: aviso sin archivo, selección nativa de icono.png como imagen de prueba, nombre, tamaño, previsualización y retirada. No se subió esa imagen al servidor. A 320 px hay un formulario multipart y un campo file, con 305 px de contenido y sin desbordamiento horizontal general. Vista de escritorio guardada para revisión.
- Arrastre y configuración de cámara verificados mediante pruebas JavaScript; no se ha probado el gesto físico de arrastre ni una cámara de móvil real. El funcionamiento sin JavaScript está previsto en el HTML, pero no se ha ejecutado una prueba manual con JavaScript desactivado.

## Cómo probar

Abrir Documentos → Añadir documento con un cliente de demostración. Elegir un PDF o imagen, comprobar nombre y tamaño y cambiar o quitar la selección. Arrastrar un archivo sobre la zona o usar Hacer una foto en un dispositivo compatible. Subir documento lleva a la revisión humana.

`node --test tests/Frontend/document-upload.test.mjs`

`docker compose exec -T --user www-data app php tests/Integration/document-upload-workflow.php`

La integración no usa IA externa y retira sus datos y archivos. Evitar cambios manuales de documentos durante ella, ya que compara el contenido existente.
