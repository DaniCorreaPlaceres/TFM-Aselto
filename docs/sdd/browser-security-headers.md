# Cabeceras de seguridad del navegador · tarea 43 / F07

## Objetivo

Aplicar cabeceras de protección a respuestas web/API y una CSP efectiva compatible con la plataforma. Proteger navegación con tokens frente a caché y referencias, conservando PDF.js, Swagger, listados y tiempo real. Rama desde develop tras integrar F06.

## Cambios planteados

- CSP con origen propio, nonce aleatorio por respuesta para scripts de Importmap, sin unsafe-inline de JavaScript ni unsafe-eval. Autorizar solo capacidades necesarias de imágenes, PDF/worker, estilos y conexiones.
- Protección de marcos, nosniff, política de referencias y permisos de navegador acotados. Mantener los atributos útiles del selector de cámara de subida.
- CSS servido como stylesheet local explícito para evitar habilitar data: como origen general de scripts. Conservar carga de estilos y navegación Turbo.
- Desactivar caché y referencias en rutas que llevan tokens, también errores; no imponer HSTS en HTTP local ni afirmar HTTPS productivo verificado.
- Diferenciar respuestas Symfony de archivos/errores servidos por nginx y documentar el alcance real. No ampliar dominios externos por compatibilidad sin evidencia.

## Fuera de alcance

Despliegue/HTTPS de Hostinger, escaneo de archivos (F08), corrección de todos los demás hallazgos, cambios de marca/diseño y recalcular la auditoría histórica. Sin nuevas bibliotecas salvo necesidad que se justifique.

## Criterios de aceptación

- Web pública/privada, API, redirecciones y errores reciben cabeceras consistentes; respuestas con tokens no tienen caché ni referencias.
- Los scripts inline de Importmap usan nonce coherente con CSP y cambiante entre respuestas. Scripts arbitrarios y marcos externos quedan bloqueados por la política; no se aceptan orígenes desde cabeceras Host/consulta.
- PDF.js con worker y recursos locales, Swagger, tablas/exportación, subida de imagen y Mercure conservan funcionamiento; documentar cualquier permiso CSP limitado necesario.
- Pruebas HTTP de cabeceras/nonce/errores y comprobación real de navegador de las funciones relevantes. Lo no ejecutado queda expresamente pendiente.
- Regresiones relevantes, formato/análisis estático y documentación con evidencias reales. La tarea 43 continúa abierta para otros hallazgos.

## Comprobado

- F06 integrada mediante PR #53; develop actualizado a `2f847f3` y rama `feature/browser-security-headers` creada desde ahí. Sin nuevas dependencias ni migraciones.
- CSP efectiva en HTML con nonce de 192 bits, marcos/manejadores inline bloqueados, recursos locales, CSS explícito y no-store. WebAssembly limitado al visor, con cambio de política seguido por Turbo mediante recarga completa. Estilos inline de componentes admitidos y documentados como excepción.
- Cabeceras comunes en Symfony y nginx, también estáticos/errores y proxy Mercure sin duplicados. Enlaces sensibles sin caché ni referencias, también inválidos.
- Workflow HTTP: **149 comprobaciones**. PHPUnit: **407 pruebas / 1.057 aserciones**. JavaScript: **35 pruebas**. Regresiones: autenticación **74**, sesión **78**, logout **45**, tiempo real **38**, subida documental **43**, correctas.
- PHPStan, formato, contenedor, **61 Twig**, **23 YAML** y nginx correctos. Sass y AssetMapper compilan **32 recursos**; salidas generadas sin versionar.
- Navegador integrado con demo: estilos/acceso/salida/Turbo, búsqueda DataTables, CSV y XLSX descargados, visor PDF y texto, transición al visor con política PDF, imagen con vista previa sin envío, conexión/actualización manual y Swagger GET con 401 esperado. Sin errores/avisos de consola en las vistas inspeccionadas. HTTP por nginx: estático 200, error 404 y hub 400 con cabeceras comunes únicas.
- [Funcionamiento, límites y reproducción](../security/browser-security-headers.md), [evidencia](../security/evidence/f07-browser-security-headers.json). Hostinger, HTTPS/HSTS, cámara física, todos los formatos PDF y otros motores de navegador siguen pendientes. La auditoría histórica y su puntuación no se cambian; tarea 43 permanece abierta.
