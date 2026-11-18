# Comercialización y contenido público · cierre del lote

## Entregas

- 34. Landings por público: `feature/audience-landings`, [PR #39](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/39).
- 35. Explicación visual de IA y revisión humana: `feature/ai-public-presentation`, [PR #40](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/40).
- 36. Footer público y copyright: `feature/public-footer`, [PR #41](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/41).
- 37. Quiénes somos y borradores de políticas: `feature/public-information`, [PR #42](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/42).
- 38. Precios e impuestos incluidos: `feature/public-pricing`, [PR #43](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/43).
- 39. Licencias y cuota mensual: `feature/licenses-and-quotas`, [PR #44](https://github.com/DaniCorreaPlaceres/TFM-Aselto/pull/44).

Todas nacen de `develop` y vuelven a `develop`. Se han integrado localmente las seis sin conflictos de código en `codex/public-content-review`, sin integrar los PR en el remoto. La lista marca trabajo implementado y comprobado; no significa que ya esté mergeado. El cierre documental conjunto viaja en el último PR para evitar conflictos entre modificaciones adyacentes de la lista.

Orden recomendado de merge: #39, #40, #42, #43, #41 y #44. El footer muestra solo rutas que existen, por lo que también admite integración progresiva sin enlaces rotos. El bloque de IA de las landings se incorpora al integrar #40. Las políticas son deliberadamente borradores, no documentos jurídicos definitivos.

## Decisiones confirmadas por el titular

Precios con impuestos incluidos, activación manual por superadmin y sin cobros automáticos. Gratis limita a 20 registros creados por mes natural de Canarias; borrar no devuelve cuota, corregir y aportar justificantes no añade consumo. Profesional independiente y Asesoría no tienen límite mensual documental, conservando límites de archivo. Asesoría cubre a sus clientes vinculados y computa cuentas activadas. Los cambios son inmediatos y conservan datos y consumo. La identidad legal, correo y domicilio permanecen pendientes, como borradores del TFM expresamente elegidos por el titular. Detalle de licencias en [licenses.md](licenses.md).

## Evidencias comprobadas

- Integración: 336 pruebas PHPUnit, 820 aserciones; PHPStan sin errores; 61 plantillas Twig válidas; compilación Sass correcta.
- HTTP público sin sesión: home, tres landings, Quiénes somos, privacidad, cookies y precios, ocho rutas con HTTP 200 sin terminar en el login. Precio Profesional 4,99 €, Asesoría 2,99 €/cliente/mes e impuestos incluidos comprobados.
- Registro con sociedad preseleccionada comprobado; no cambia el rol ni la vinculación.
- Revisión visual de landings y políticas en escritorio y 320 px: jerarquía legible, un main y footer, sin desbordamiento. Bloque de IA presente en home y landings, ejemplo identificado como ficticio.
- Precios: tres planes en escritorio, disposición vertical en móvil, sin desbordamiento a 320 px, enlaces de activación al apartado correcto y footer con enlace a precios.
- Cliente: Mi plan muestra plan y consumo propios (demostración Gratis, 3 registros), menú móvil contraído y sin desbordamiento a 320 px. Superadmin: formulario de licencias revisado, con planes compatibles por tipo de cuenta, sin documentos ni consumo individual.
- Seguridad de formularios y flujo web en base temporal: 21 comprobaciones, incluyendo denegación de administración al cliente, CSRF, rechazo de plan incompatible, asignación por superadmin y aplicación inmediata. El superadmin no tiene vista de consumo personal.
- API documental: 77 comprobaciones, incluyendo HTTP 409 `document_quota_exceeded`, ausencia de registro nuevo al rechazar y subida permitida tras asignar Profesional.
- Licencias MySQL: 20 comprobaciones, dos procesos reales que compiten por la plaza 20; solo uno crea documento. Cambios de plan, borrado sin devolución, rollback, cambio de mes, cobertura al vincularse y cuenta activada en el cómputo.
- Migración desde el esquema anterior con originales sintéticos: documentos preservados campo a campo y mes de verano/invierno correcto. 13 controles de esquema correctos, sin DDL pendiente.
- Entorno local: copia de seguridad antes de migrar; los 44 documentos originales conservan todos sus campos tras aplicar la migración. Esquema sincronizado. Datos y archivos sintéticos de los workflows retirados.
- Los cinco trabajos de GitHub (formato PHP, PHPStan, PHPUnit/recursos, esquema MySQL y OpenAPI) se verifican en cada PR; el trabajo de esquema añade migración con datos anteriores y carrera de dos procesos.

## Límites pendientes fuera del alcance

Faltan los datos legales del responsable y la revisión jurídica antes de utilizar las políticas como definitivas. Referencias y cookies observadas en [public-policies.md](public-policies.md). No se han probado lector de pantalla, zoom real al 200 %, carga masiva ni despliegue productivo. La extracción mantiene bloqueos durante la creación para garantizar cuota y coherencia de vínculo/plan; el alcance de rendimiento está documentado en [licenses.md](licenses.md).

Las tareas 40 y siguientes siguen pendientes. `config/reference.php` conserva los cambios previos del entorno y está fuera de estos commits.
