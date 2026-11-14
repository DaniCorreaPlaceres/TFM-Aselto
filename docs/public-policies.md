# Políticas públicas: borrador y preparación productiva

Las páginas de privacidad y cookies son borradores del TFM. No se ha identificado al responsable legal con la autoría del proyecto ni se han inventado correo, domicilio, plazos o bases jurídicas.

## Antes de producción

- Completar identidad del prestador/responsable, contacto de privacidad y domicilio publicable.
- Determinar el papel de plataforma y asesorías para cada tratamiento y formalizar encargos cuando proceda.
- Fijar bases, conservación, supresión, copias, ejercicio de derechos y tratamiento de datos de terceros en documentos.
- Revisar alojamiento y SMTP efectivos, proveedor de IA, contratos y transferencias; Hostinger todavía es un destino previsto, no comprobado como despliegue actual.
- Incorporar información básica en la recogida de datos y revisar jurídicamente textos y flujos antes de utilizarlos como información definitiva.

## Evidencia técnica local

`framework.session: true`; PHP usa sesión nativa. El controlador de CSRF puede crear cookies dinámicas en los formularios que empleen tokens sin estado, pero su presencia no se deduce de que exista el controlador: depende del token y del envío. Mercure autoriza durante 60 segundos por cookie HttpOnly, ruta del hub y SameSite Strict; en desarrollo su nombre es `mercure_access_token`. `clear_cookie_on_logout` está desactivado en esta configuración y el token caduca por plazo. No se afirma borrado inmediato de todas las cookies al salir.

DataTables tiene `stateSave: false`. No se encontraron usos de localStorage/sessionStorage en los controladores propios revisados ni servicios de analítica/publicidad en la configuración inspeccionada. El inventario de producción debe medirse de nuevo con HTTPS y proveedores definitivos.

El extractor configurado de OpenAI envía el contenido del archivo a su API de Chat Completions. Sin clave se usa el extractor sintético. No se atribuyen aquí garantías de retención, ubicación o entrenamiento al proveedor sin revisar la contratación efectiva.

## Fuentes primarias

- [AEPD: derecho de información](https://www.aepd.es/derechos-y-deberes/conoce-tus-derechos/derecho-de-informacion): identificación, finalidades, bases, destinatarios, conservación y derechos en capas.
- [AEPD: guía sobre cookies, mayo de 2024](https://www.aepd.es/guias/guia-cookies.pdf): cookies técnicas para prestar el servicio solicitado y límites de la excepción. Clasificar las cookies de Aselto como técnicas es una interpretación de su uso observado, no una certificación de la AEPD sobre este producto.

Referencias consultadas durante la tarea 37. La política de la propia AEPD no se ha copiado como si describiese Aselto.
