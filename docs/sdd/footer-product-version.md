# Versión del producto junto al copyright

## Objetivo

Mostrar la versión configurada de Aselto junto al copyright del footer existente para reconocer el cambio después de publicar. Rama feature/footer-product-version desde develop. El usuario solicita integrar este cambio antes de activar el primer despliegue y volver a publicar en main.

## Cambios planteados

- Exponer el parámetro app.version como global Twig product_version, sin duplicar su valor.
- Mostrar «Versión 1.0.0-dev» junto al copyright de Daniel Correa Placeres en el footer compartido de páginas públicas y acceso.
- Conservar el diseño y el valor actual 1.0.0-dev; no crear una release/etiqueta ni activar el CD en esta tarea.

## Fuera de alcance

Nuevo footer en las pantallas de trabajo, cambio de número de versión, identificador público de commit, cambios del instalador, merge a main y activación del despliegue.

## Criterios de aceptación

- El copyright y la versión aparecen juntos en las páginas que ya incluyen el footer.
- La versión procede de app.version, la misma fuente de app:version; no se fija como literal en Twig.
- Plantillas/configuración válidas y respuestas locales de páginas públicas sin errores.
- No se publica ni cambia HOSTINGER_DEPLOY_ENABLED antes de integrar/revisar este cambio.

## Comprobado

- Global Twig product_version conectado a app.version; footer muestra la versión junto al copyright. No se cambia el valor 1.0.0-dev ni el CSS existente, que ya permite envolver el contenido del footer.
- lint:twig y lint:yaml correctos. HTTP local /, /entrar y /precios: 200 y texto conjunto de autor/versión presente en las tres respuestas. No se ha realizado comprobación visual en móvil ni publicación en Hostinger.
- Documentación de versionado actualizada. No se ha activado el despliegue, hecho commit, creado PR ni promovido a main en esta tarea.
- El número de producto identifica una versión lógica, no un commit único: el cambio visible del footer ayudará a comprobar esta publicación, pero no acredita por sí solo todos los archivos ejecutados ni invalidación de OPcache.
