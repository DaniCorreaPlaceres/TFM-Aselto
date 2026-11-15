# Cabeceras de seguridad del navegador · F07

Implementación local del 15/11/2026. [Ficha](../sdd/browser-security-headers.md) y [evidencia](evidence/f07-browser-security-headers.json). No modifica la puntuación de la auditoría histórica.

## Política de aplicación

`BrowserSecurityHeaders` añade a las respuestas principales de Symfony, incluidos redirecciones y errores:

- `X-Content-Type-Options: nosniff` y `X-Frame-Options: DENY`.
- `Referrer-Policy: no-referrer`: no enviar la URL de origen, también cuando contiene un enlace sensible.
- `Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=(), display-capture=()`. La cámara del propio origen conserva la posibilidad del selector existente; no concede permisos al dispositivo.
- CSP con `frame-ancestors 'none'`, `base-uri 'none'` y `object-src 'none'`.

Para HTML, la CSP efectiva usa origen propio como valor por defecto, bloquea frames y manejadores inline, limita formularios y conexiones al propio origen y permite scripts locales o con un nonce aleatorio de 192 bits generado por petición. No permite JavaScript `unsafe-inline`, `unsafe-eval`, scripts `data:` ni CDN externos. Los recursos PDF.js y Swagger son locales. El worker PDF es del propio origen. Imágenes y fuentes admiten `data:` y `blob:` para sus usos locales; seleccionar una imagen genera una URL de objeto y no envía el archivo hasta confirmar la subida.

Solo las rutas de revisión `client_document` y `firm_document` permiten `wasm-unsafe-eval`, necesario para los decodificadores WebAssembly de PDF.js; no habilita la evaluación de JavaScript. El meta `csp-policy`, seguido por Turbo con `data-turbo-track="reload"`, fuerza una navegación completa al cambiar entre la política ordinaria y la del visor: una respuesta obtenida mediante fetch no puede cambiar la CSP del documento actual. El meta `csp-nonce` y los atributos nonce de Importmap mantienen la integración con Turbo.

**Excepción deliberada:** `style-src 'self' 'unsafe-inline'` admite los estilos dinámicos de los componentes de interfaz. La excepción no se aplica a scripts. Esta entrega no acredita una CSP estricta para CSS ni el bloqueo de cualquier inyección HTML; tampoco sustituye el escape y la autorización del servidor.

El CSS de Sass se enlaza explícitamente como stylesheet local. Se retira su importación desde JavaScript para evitar los módulos `data:application/javascript` que AssetMapper genera al importar CSS. Importmap recibe el nonce mediante su API oficial. Se retira el bloque opcional de recarga FrankenPHP con dependencia CDN, ajeno al entorno PHP-FPM de Docker.

El HTML se entrega `private, no-store` para evitar reutilizar cuerpos con un nonce distinto de su cabecera. Las rutas con token `/activar/`, `/recuperar/`, `/invitacion/` y `/confirmar-correo-admin/` reciben también no-store aunque el enlace sea inválido y la respuesta no sea HTML. Se ejecuta después del listener de sesión para conservar ese resultado final.

JSON, descargas y recursos no HTML conservan su tipo y contenido; reciben la CSP de marcos/base/objetos, sin imponerles la política de scripts del documento. En particular, no se presenta la CSP de JSON como defensa de ejecución de una página HTML. Las descargas privadas conservan sus permisos y no-store anteriores.

## Servidor local y despliegue

`docker/nginx/security-headers.conf` se monta desde Compose y añade las cuatro cabeceras comunes con `always`. Cubre archivos estáticos y errores de nginx que no atraviesan Symfony. La ubicación Mercure incluye también el fragmento porque su `add_header X-Accel-Buffering` impide heredar el del servidor. Se ocultan las copias de esas cuatro cabeceras de FastCGI y del hub para no duplicarlas.

La CSP HTML se genera en Symfony, no se fija un nonce en nginx ni se aplica una política de scripts indiscriminada a los archivos worker. Los errores generados exclusivamente por nginx tienen las cuatro cabeceras comunes, pero no la CSP HTML de Symfony. La política de conexión admite el proxy Mercure del propio origen, utilizado por las suscripciones actuales.

No se añade HSTS en HTTP local. En Hostinger hay que reproducir la protección de recursos estáticos/errores del servidor contratado, evitar duplicados y verificar HTTPS, cookies y HSTS antes de afirmarlos operativos. Esta entrega no verifica el servidor productivo, una configuración de CDN ni todos los navegadores/dispositivos.

## Comprobaciones y reproducción

Ejecutado en Docker local:

```powershell
docker compose up -d --no-deps web
docker compose exec -T web nginx -t
docker compose exec -T --user www-data app php tests/Integration/browser-security-headers-workflow.php
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T app php vendor/bin/phpstan analyse --no-progress
```

El workflow HTTP comprueba 149 condiciones sobre home, acceso, Swagger, nonce distinto/coherente, ausencia de módulos script data:, CSS explícito, API 401, redirección privada, 404, 405 y enlaces sensibles válidos en formato o malformados. No escribe datos ni envía correos. PHPUnit: 407 pruebas / 1.057 aserciones, incluyendo alcance de WebAssembly, descargas y subpeticiones. PHPStan, formato, contenedor, 61 Twig y 23 YAML correctos. Sass y AssetMapper compilan 32 recursos; no se versionan sus salidas generadas. En desarrollo hay que retirar el directorio generado `public/assets` para volver a servir cambios desde AssetMapper.

Regresiones: autenticación 74, sesión 78, logout CSRF 45, trimestre en vivo 38 y subida documental 43; 35 pruebas JavaScript. Todo correcto; los workflows con fixtures retiran/revierten sus datos y no usan IA ni SMTP externos.

Navegador integrado, cuentas sintéticas de demo: home con estilos, acceso/salida, navegación Turbo, bandeja inicializada, búsqueda filtrada, descarga CSV, descarga Excel reconocida como archivo XLSX con sus entradas ZIP, PDF renderizado y texto extraído, transición desde trimestre al visor con política PDF, imagen seleccionada con vista previa sin enviarla, estado de conexión y actualización manual de cifras. Swagger ejecuta GET /api/v1/me sin JWT y muestra el 401 esperado con las cabeceras nuevas. Sin errores/avisos de consola en las vistas inspeccionadas. No se comprobaron documentos PDF arbitrarios con todos los formatos de imagen, selector físico de cámara ni otro motor de navegador.

HTTP real por nginx: recurso JavaScript 200 con su MIME, error nginx 404 y error del hub 400, con una sola copia de las cuatro cabeceras. No se forzó una caída del servidor para simular un 502. Los atributos `always` quedan comprobados mediante validación de configuración, no mediante todos los errores posibles.

## Referencias

- [Symfony 7.4 AssetMapper](https://symfony.com/doc/7.4/frontend/asset_mapper.html): atributos nonce de Importmap y recursos CSS.
- [MDN: CSP](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy) y [script-src](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/script-src): alcance de nonce y WebAssembly. Consultadas el 15/11/2026.
