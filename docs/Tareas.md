# Tareas

Estado de las tareas: [x] hecha, [ ] pendiente o en curso. El detalle de cada una se escribe en `docs/sdd/` antes de implementarla.

## Cimientos

- [x] 1. Memoria de producto, stack, arquitectura y especificaciones.
- [x] 2. Ramas `main` y `develop`, con `main` protegida.
- [x] 3. Entorno local en Docker, comprobado.
- [x] 4. Estructura básica de Symfony.

## Plataforma

- [x] 5. Acceso y papeles: superadmin, admin, gestor y cliente.
- [x] 6. Home comercial pública en `/`.
- [x] 7. Alta de asesorías y de su equipo.
- [x] 8. Alta de clientes, autónomo o sociedad.
- [x] 9. Cuando un superadmin da de alta una asesoría, el alta se hace mediante un email que le llega y con un enlace tiene que crear la cuenta. El enlace va con token y tiene tiempo limitado.
- [x] 10. Cuando una asesoría da de alta a su equipo, email con enlace para registrarse. Token de tiempo limitado.
- [x] 11. Hay que añadir un sistema de recuperación de contraseña en el login.
- [x] 12. Inicio del cliente, con la marca y el menú.
- [x] 13. Añadir un documento, revisar los datos y confirmarlo.
- [x] 14. Lectura del documento por IA, con revisión humana.
- [x] 15. Alta de clientes y de asesorías con NIF o CIF, teléfono y dirección. Validar el formato del NIF, CIF o NIE.
- [x] 16. Perfil editable con los datos fiscales. Cada persona edita los suyos. El admin de la asesoría edita los de sus clientes. El gestor los ve y no los cambia.
- [x] 17. El territorio sale de la provincia. Península y Baleares usan IVA. Canarias usa IGIC: 0, 3, 7, 9,5, 15 y 20. Ceuta y Melilla usan IPSI con porcentaje manual. El documento usa el impuesto de ese territorio.
- [x] 18. Recargo de equivalencia opcional en los gastos con IVA, con cuota y total calculados.
- [x] 19. Retención de IRPF opcional en los ingresos de un autónomo, con porcentaje manual, cuota y total calculados.
- [x] 20. Bandeja de la asesoría: validar con correcciones, rechazar con motivo y reenvío o pedir justificante adicional, conservando historial y original.
- [x] 21. Trimestre: cifras, cierre, reapertura y consulta del histórico.
- [x] 22. Mensajes y solicitudes entre cliente y asesoría, con adjuntos y lectura individual.
- [x] 23. Actualización en vivo del trimestre.
- [x] 24. API `/api/v1` con los mismos casos de uso.
  - [x] Acceso JWT y consulta del usuario actual.
  - [x] Documentos y revisión de asesoría, incluidos justificantes y descargas.
  - [x] Consulta de trimestres, cierre, reapertura e histórico.
  - [x] Swagger UI en `/api/docs` con autorización Bearer.
  - [x] Comunicación: mensajes, solicitudes y adjuntos.
- [x] 25. Datos de demostración de dos asesorías, equipos y clientes, con documentos, trimestres y comunicación. Carga local repetible y guía en `docs/demo.md`.
- [x] 26. Rediseño visual de toda la plataforma y home comercial con Bootstrap 5, Sass/SCSS, DataTables con Excel/CSV y visor PDF.js local, manteniendo la marca y las reglas visuales. Límites de revisión de accesibilidad en `docs/diseno/platform-design.md`.
- [x] 27. Una sola zona para añadir el documento: foto, archivo o arrastrar y soltar, con selección, vista previa de imagen y avisos de validación.
## Documentación inmediata

La antigua tarea 28 se divide. Primero se realizarán las tareas 28 y 29; después se abordarán las nuevas funcionalidades una a una. La preparación final de la defensa queda al final de la lista.

- [x] 28. Actualizar el README para describir el producto implementado, su arquitectura, funciones, requisitos, comprobaciones y enlaces a documentación y demostración.
- [x] 29. Documentar y verificar una instalación local desde cero: configuración, Docker, dependencias, migraciones, claves JWT, recursos Sass/AssetMapper, servicios, accesos de demostración y resolución de problemas. Separar instrucciones verificadas de pasos pendientes de comprobar. Guía y limitaciones en `local-installation.md`.
- [x] 29a. Resolver la diferencia de esquema detectada durante la instalación: alinear metadatos con tipos, valores por defecto, comentarios e índices existentes y describir la cola SQL para su validación. Sin modificar datos ni migraciones publicadas. Verificación automática en MySQL y detalle en `sdd/doctrine-schema-alignment.md`.

## Administración e invitaciones

Las invitaciones a asesorías y a equipos ya tienen una primera implementación en las tareas 9 y 10. Las tareas siguientes completan y revisan el alcance solicitado; no se dan por hechas por existir esos primeros flujos.

- [x] 30. Completar las invitaciones del superadmin a asesorías y clientes independientes (autónomos o empresas): tipo y correo, registro del destinatario con sus datos y contraseña, enlace de uso único y caducidad, estados y reenvío invalidando el enlace anterior. Se bloquean cuentas existentes sin cambiar su rol ni vinculación. Guía en `invitations.md`.
- [x] 31. Administración de asesorías por el superadmin: listado y ficha con nombre, NIF, teléfono y dirección; alta existente y cambio del correo del admin mediante confirmación de 24 horas, sin modificar identidad o permisos. Revocación de accesos y enlaces anteriores, aviso al correo antiguo y protección de cambios concurrentes. No concede acceso a actividad privada. Guía en `platform-firms.md`.
- [ ] 32. Administración de clientes independientes por el superadmin: listar, añadir, consultar y editar los datos principales de los clientes sin asesoría. No permite entrar en su actividad económica, documentos, trimestres, conversaciones ni archivos. Revisar expresamente permisos de web, API y descargas.

## Comercialización y contenido público

- [ ] 33. Reorientar la home a la venta del producto: una sección para autónomos y empresas y otra para asesorías, con mensajes y llamadas a la acción diferenciados. Destacar el control de facturas y documentación de los clientes de una asesoría. Cambiar «Tu acceso» por «Tu cuenta» cuando hay sesión iniciada.
- [ ] 34. Crear tres landing pages enlazadas desde la home: autónomos, empresas y asesorías. Adaptar a cada público los problemas, beneficios, ejemplos y llamadas a la acción.
- [ ] 35. Reforzar la propuesta de IA en home y landings con textos, iconos e imágenes coherentes con la marca. Explicar cómo ayuda a organizar y extraer información, manteniendo la revisión humana y sin prometer validación fiscal automática o capacidades no implementadas.
- [ ] 36. Añadir un footer común con «Quiénes somos», privacidad, cookies, precios y enlaces a las landings de autónomos, empresas y asesorías. Incluir «© [año] Daniel Correa Placeres», con el año actualizado.
- [ ] 37. Preparar las páginas de «Quiénes somos», política de privacidad y política de cookies. Identificar responsable, tratamientos, proveedores y cookies realmente utilizados; concretar la información que debe aportar el titular y dejar señalada la revisión legal pendiente, sin inventar datos ni considerar estos borradores una validación jurídica.
- [ ] 38. Crear la página de precios: Gratis, Profesional por 4,99 €/mes y Asesoría por 2,99 €/mes por cliente. Explicar los planes y sus llamadas a la acción. Aclarar impuestos incluidos o no, alcance de cada licencia y forma de contratación antes de publicarla como oferta definitiva.
- [ ] 39. Incorporar las licencias y el límite del plan Gratis de 20 documentos al mes. Acordar cómputo mensual, documentos que consumen cuota, cambios de plan y contabilización de clientes del plan Asesoría. Aplicar los límites de forma coherente en web y API, con avisos comprensibles. No presupone integrar cobros ni facturación.

## Experiencia del cliente e impuestos

- [ ] 40. Mejorar la distribución de «Ingresos y gastos», conservando el inicio actual. Separar con claridad cifras, movimientos, pendientes e histórico y facilitar añadir un ingreso o un gasto desde esa pantalla, reutilizando los casos de uso y las reglas de revisión existentes.
- [ ] 41. Añadir la opción «Exento» a los registros para que la cuota del impuesto sea cero. Distinguir exención de un tipo del 0 %, conservar el territorio y reflejar el estado en revisión, cálculos, API, exportaciones e instantáneas. Acordar el tratamiento de recargo, retención y registros ya cerrados antes de implementar.

## Seguridad y publicación

- [ ] 42. Auditar la web y API con una lista de controles OWASP aplicables y una metodología reproducible. Documentar alcance, versión de las referencias, evidencias, controles cumplidos, incumplidos, no comprobados y no aplicables. Obtener una puntuación con denominador explícito y acompañarla de riesgos y limitaciones; priorizar después las mejoras junto al usuario.
- [ ] 43. Corregir y verificar las mejoras que se acuerden tras la auditoría, en tareas o PR separados según su alcance. No marcar hallazgos resueltos sin volver a comprobarlos.
- [ ] 44. Preparar el versionado 1.0.0 para la primera publicación en main: criterio de versiones, versión visible o consultable, notas de cambios y etiqueta asociada al commit de la versión final. Diferenciar versión del producto y versión del contrato OpenAPI.
- [ ] 45. Preparar y verificar las instrucciones de publicación en producción en Hostinger: comprobar el plan y sus capacidades, dominio, HTTPS, configuración, secretos, base de datos, archivos privados, correo, JWT, compilación de recursos, Mercure y procesos de fondo. Documentar copias de seguridad, migraciones, caché, comprobación de salud y recuperación, sin asumir que el hosting admite Docker o servicios persistentes.
- [ ] 46. Configurar y documentar la integración GitHub–Hostinger y el CI/CD de publicación de main: comprobaciones, construcción, transferencia de la versión, migraciones, caché y verificación posterior. Concretar si el despliegue se dispara al abrir el PR o al integrarlo; se propone producción tras el merge aprobado y validación previa en el PR. Proteger secretos, evitar despliegues simultáneos y documentar reversión, límites de las migraciones y el sistema para explicarlo en el TFM. Mantener main protegida.

## Cierre de la entrega

- [ ] 47. Revisar los recorridos completos, cerrar o declarar las comprobaciones manuales pendientes y recopilar evidencias, usuarios de prueba, límites conocidos y documentación definitiva.
- [ ] 48. Preparar la presentación y el guion del vídeo con los requisitos del máster, incluyendo arquitectura, IA con revisión humana, aislamiento entre asesorías, auditoría y despliegue. Preparar y verificar los materiales finales que se acuerden con el usuario.
