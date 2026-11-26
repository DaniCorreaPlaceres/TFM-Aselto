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
- [x] 32. Administración de clientes independientes por el superadmin: listado y ficha administrativa de clientes sin asesoría, edición de nombre y contacto fiscal, alta directa e invitaciones. Cuenta y tipo conservados, control de versiones y vinculación concurrente, sin acceso a actividad privada por web, API o descargas. Guía en `platform-independent-clients.md`.

## Comercialización y contenido público

Tareas 34–39 integradas en `develop` mediante seis ramas y PR independientes. Decisiones confirmadas y evidencias conjuntas en [public-content-batch.md](public-content-batch.md).

- [x] 33. Reorientar la home a la venta del producto: secciones para autónomos/empresas y asesorías, beneficios y acciones diferenciados, ejemplo documental ilustrativo y proceso en tres pasos. Acciones conectadas al registro y acceso existentes; «Tu cuenta» con sesión iniciada. Detalle en `sdd/commercial-home.md`.
- [x] 34. Crear tres landing pages enlazadas desde la home: autónomos, empresas y asesorías. Adaptar a cada público los problemas, beneficios, ejemplos y llamadas a la acción.
- [x] 35. Reforzar la propuesta de IA en home y landings con textos, iconos e imágenes coherentes con la marca. Explicar cómo ayuda a organizar y extraer información, manteniendo la revisión humana y sin prometer validación fiscal automática o capacidades no implementadas.
- [x] 36. Añadir un footer común con «Quiénes somos», privacidad, cookies, precios y enlaces a las landings de autónomos, empresas y asesorías. Incluir «© [año] Daniel Correa Placeres», con el año actualizado.
- [x] 37. Preparar las páginas de «Quiénes somos», política de privacidad y política de cookies. Identificar responsable, tratamientos, proveedores y cookies realmente utilizados; concretar la información que debe aportar el titular y dejar señalada la revisión legal pendiente, sin inventar datos ni considerar estos borradores una validación jurídica.
- [x] 38. Crear la página de precios: Gratis, Profesional por 4,99 €/mes y Asesoría por 2,99 €/mes por cliente. Explicar los planes y sus llamadas a la acción. Aclarar impuestos incluidos o no, alcance de cada licencia y forma de contratación antes de publicarla como oferta definitiva.
- [x] 39. Incorporar las licencias y el límite del plan Gratis de 20 documentos al mes. Acordar cómputo mensual, documentos que consumen cuota, cambios de plan y contabilización de clientes del plan Asesoría. Aplicar los límites de forma coherente en web y API, con avisos comprensibles. No presupone integrar cobros ni facturación.

## Experiencia del cliente e impuestos

- [x] 40. Mejorar la distribución de «Ingresos y gastos», conservando el inicio actual. Separar con claridad cifras, movimientos, pendientes e histórico y facilitar añadir un ingreso o un gasto desde esa pantalla, reutilizando los casos de uso y las reglas de revisión existentes. Implementación y evidencias en `sdd/client-movements-layout.md`.
- [x] 41. Añadir la opción «Exento» a los registros para que la cuota del impuesto sea cero. Distinguir exención de un tipo del 0 %, conservar el territorio y reflejar el estado en revisión, cálculos, API, exportaciones e instantáneas. Reglas aprobadas: sin recargo, IRPF opcional donde corresponda y corrección tras reapertura autorizada. Detalle en `sdd/tax-exempt-records.md`.

## Seguridad y publicación

- [x] 42. Auditar la web y API con una lista de controles OWASP aplicables y una metodología reproducible. Documentar alcance, versión de las referencias, evidencias, controles cumplidos, incumplidos, no comprobados y no aplicables. Obtener una puntuación con denominador explícito y acompañarla de riesgos y limitaciones; priorizar después las mejoras junto al usuario. [Informe y evidencias](security/README.md): 23/40 controles cumplidos (57,5 % conservador), 12 incumplidos y 5 sin comprobar; correcciones pendientes en tarea 43.
- [x] 43. Corregir y verificar las mejoras que se acuerden tras la auditoría, en tareas o PR separados según su alcance. No marcar hallazgos resueltos sin volver a comprobarlos. F01–F07 integradas: [revocación](sdd/password-reset-token-revocation.md), [origen confiable de enlaces](sdd/canonical-account-links.md), [límites](sdd/authentication-rate-limits.md), [contraseñas](sdd/password-policy.md), [caducidad de sesión](sdd/session-expiration.md), [CSRF de cierre de sesión](sdd/logout-csrf.md) y [cabeceras del navegador](sdd/browser-security-headers.md), con excepción de estilos inline documentada y producción pendiente. F08 (análisis antimalware) aplazado por decisión del usuario el 15/11/2026; permanece sin resolver. F09 integrada: [límites de recursos, paginación y exportación completa](sdd/resource-limits.md), con dimensionamiento productivo y de agregados fiscales pendiente. F10 integrada y verificada con proveedor simulado: [límites de extracción IA](sdd/ai-resource-limits.md), con temporización real, proveedor y producción pendientes. F11 integrada y verificada localmente: [registro privado, retención y alertas](sdd/security-event-logging.md); transporte y cron productivos pendientes. F12 integrada y verificada localmente: [puertos restringidos a loopback](sdd/local-service-bindings.md), con prueba desde otra máquina y producción pendientes. F13 integrada y verificada en GitHub: [19 workflows de seguridad en CI](sdd/security-ci-checks.md), con [protecciones autorizadas de main/develop y bloqueo de merge comprobado](sdd/security-merge-protection.md). Quedan los demás hallazgos y su priorización. Cierre de la tarea por decisión expresa del usuario; no implica que todos los hallazgos estén resueltos. Se mantienen los aplazados y las verificaciones pendientes documentados en el seguimiento de seguridad.
- [x] 44. Versionado publicado: 1.0.0 en main/Hostinger el 19/11/2026 y parche 1.0.1 el 26/11/2026, con fuente única de versión, footer, notas y etiquetas anotadas sobre sus commits verificados. Contrato OpenAPI 0.6.0 independiente. [Evidencias 1.0.0](sdd/release-1-0-0.md), [evidencias 1.0.1](sdd/release-1-0-1.md). No implica cerrar los límites de seguridad u operación declarados.
- [x] 45. Preparar y verificar la publicación en Hostinger. Preparación cerrada por aceptación expresa del usuario el 18/11/2026, con las evidencias de los PR 65/66: instalación, acceso, visor, revisión documental, permisos web/API, cron comunicado y lectura del original recuperado. [Relato para el TFM](deployment/hostinger-tfm.md), [verificaciones adicionales](deployment/hostinger-verification.md). Los controles restantes pasan a la revisión final (47); copia externa aplazada. El cierre de preparación no acredita una auditoría completa, release 1.0.0 ni CI/CD ejecutado.
- [x] 46. Configurar y documentar GitHub–Hostinger y el CI/CD de publicación de main. Primera publicación automática correcta el 18/11/2026: ejecución 44397469824, main 3633342, seis checks, paquete y job SSH correctos; copia previa privada, migraciones al día, esquema/caché y comprobaciones HTTP. Footer nuevo verificado por HTTP. Interruptor true por autorización del usuario. [Guía](deployment/github-hostinger.md), [evidencias e incidencia resuelta](sdd/hostinger-deployment-investigation.md). Conserva límites: OPcache bajo suposición aceptada y purga manual sin confirmar, retención manual de tres copias correctas/fallidas hasta resolver, copia externa aplazada y restauración de la nueva copia concreta no ensayada. No publica etiqueta/release final 1.0.0 ni cierra revisión final 47.

## Cierre de la entrega

- [x] 49. Vinculación de clientes independientes mediante invitación de admin o gestor y aceptación del cliente, sin duplicar cuentas ni cambiar roles. Conserva cierres personales diferenciados y pasa confirmados de periodos abiertos a revisión. Implementada, desplegada y recorrido principal confirmado por el usuario en Hostinger: correo, aceptación, contacto, acceso anterior/posterior, pendientes e histórico. [Ficha](sdd/existing-client-firm-invitations.md), [evidencia productiva](sdd/release-1-0-0.md), [guía](client-firm-linking.md).

- [x] 47. Revisión final del TFM cerrada por aceptación expresa del usuario el 26/11/2026, con recorridos web/API, aislamiento, documentos, mensajes/adjuntos, solicitudes, cifras, exportaciones y cierre/reapertura documentados. [Cierre y límites](sdd/final-review.md), [evidencias detalladas](sdd/release-1-0-1.md). Se mantienen recuperación de justificantes/adjuntos y copia externa aplazadas, HSTS pendiente, capacidad y cobertura API exhaustiva no comprobadas, valores efectivos de PHP y evidencias detalladas de cron/alertas sin aportar, y suposición de OPcache aceptada. El cierre no equivale a resolver todos los riesgos ni acreditar preparación comercial.
- [ ] 48. Preparar la presentación y el guion del vídeo con los requisitos del máster, incluyendo arquitectura, IA con revisión humana, aislamiento entre asesorías, auditoría y despliegue. Preparar y verificar los materiales finales que se acuerden con el usuario. Bloque de despliegue recopilado con guion y propuesta de diapositivas en [hostinger-tfm.md](deployment/hostinger-tfm.md); presentación y vídeo completos pendientes. Capítulo explicativo de toda la configuración preparado en [configuracion-despliegue-tfm.md](deployment/configuracion-despliegue-tfm.md).
