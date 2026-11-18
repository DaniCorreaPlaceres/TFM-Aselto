# Caducidad de sesión web · tarea 43 / F05

## Objetivo

Aplicar en servidor límites de inactividad y duración absoluta a la sesión web, sin que el trabajo automático de actualizaciones los prolongue. Rama desde develop tras integrar F04.

## Cambios planteados

- 30 minutos sin actividad HTTP de usuario y 8 horas desde el acceso; parámetros de aplicación positivos, con inactividad menor o igual a duración absoluta.
- Tiempos registrados solo en el servidor al iniciar sesión. Comprobar caducidad antes del firewall/controlador y renovar la última actividad tras una petición autenticada, sin desplazar el inicio absoluto.
- No contar API JWT, recursos/diagnóstico, renovación de suscripciones ni fragmentos automáticos. La actualización manual de cifras se identifica expresamente como acción de usuario; no se considera una prueba de presencia humana.
- Caducidad: invalidar la sesión y CSRF anteriores, limpiar el token en memoria y la cookie de suscripción. Navegación privada lleva al acceso con aviso; peticiones automáticas reciben 401 privado sin datos. GET públicos continúan como anónimo, preservando enlaces de recuperación/invitación sin conservar autenticación.
- Las sesiones sin metadatos válidos deben reautenticarse tras el despliegue. Un nuevo acceso correcto inicia un ciclo nuevo y conserva la rotación de identificador de Symfony.

## Fuera de alcance

Cambiar TTL/revocación JWT, MFA, permisos, timeout de transporte/Hub, cierre de sesión CSRF (F06), avisos previos/heartbeat de teclado, despliegue Hostinger y otros hallazgos. No abortar una operación que ya empezó autorizada ni recalcular auditoría histórica.

## Criterios de aceptación

- Umbrales exactos, inactividad, duración absoluta pese a actividad reciente, metadatos antiguos/alterados/futuros y reentrada probados con reloj controlado, sin esperas reales.
- Peticiones automáticas repetidas no mantienen la sesión; acciones normales/manuales renuevan solo la inactividad. API mantiene independencia de la cookie web.
- Sesión caducada no alcanza datos privados, operaciones ni renovación de suscripción, limpia credenciales de sesión/cookie y no se restaura al reutilizar el identificador/estado antiguo.
- Flujos de autenticación, revocación y tiempo real conservados; fixtures actualizados para representar accesos con metadatos válidos, sin dar excepciones a la política productiva.
- PHPUnit, HTTP sintético, JavaScript relevante y comprobaciones de calidad/documentación con resultados reales. La tarea 43 permanece abierta para otros hallazgos.

## Comprobado

- Rama desde develop `239f8e4`, tras PR #51. Política pura compartida y adaptador de sesión/eventos; parámetros 1800/28800 segundos y gc_maxlifetime enlazado al máximo absoluto. Sin migraciones/dependencias nuevas.
- PHPUnit completo: **404 pruebas / 1.042 aserciones**, correcto. Fronteras exactas, actividad reciente sin evitar el máximo absoluto, configuración y metadatos inválidos/antiguos/futuros.
- Kernel HTTP con reloj controlado y MockArraySessionStorage: **78 comprobaciones**, correcto. Login real y rotación, expiración, actividad automática/manual, independencia JWT, limpieza de sesión/CSRF/cookie, reentrada y prioridad sobre respuestas tempranas del limitador. Recuperación pública continúa como anónimo. Identidades originales conservadas tras rollback.
- JavaScript de tiempo real: **11 pruebas**, correctas; marca manual de una petición y cierre/retirada de cifras al recibir 401.
- Regresiones autenticación **74**, tiempo real **38**, trimestres **43**, mensajería **37**, subida **43**, asesorías **70**, invitaciones **118** y clientes independientes **55**, correctas. Fixtures adaptados mediante helper de marcas de acceso, sin excepción en producción.
- Probe de auditoría conserva F01–F04 y datos originales; la salida sin CSRF de una sesión vigente sigue pendiente en F06. No se cambia puntuación histórica.
- PHPStan, contenedor, 61 Twig y 23 YAML correctos. [Guía](../security/session-expiration.md), [evidencia](../security/evidence/f05-session-expiration.json) y seguimiento actualizados.
- No comprobados: replay de cookie/almacenamiento de Hostinger, revisión visual de navegador ni espera real de ocho horas. Los controles se prueban con reloj controlado y sesión simulada; la caducidad se evalúa en peticiones, sin abortar operaciones en curso ni retirar respuestas ya entregadas. Tarea 43 sigue abierta.
- PHP-CS-Fixer de los 16 PHP modificados/nuevos y `git diff --check` de la tarea, correctos. El cambio local previo de config/reference.php queda fuera de la entrega.
