# Límites de extracción IA · tarea 43 / F10

## Objetivo

Acotar los recursos del adaptador de extracción IA y mantener la recuperación manual ante saturación, errores o respuestas excesivas. Rama `feature/ai-resource-limits` desde develop `4e13407`, tras integrar F09 (PR #55).

## Cambios planteados

- Mantener el endpoint HTTPS, modelo configurable y contrato de extracción existentes. Consultar la referencia oficial para el presupuesto de salida, sin migrar proveedor ni modelo.
- Entrada máxima de 8.000.000 bytes y cuerpo JSON acotado antes de enviar. Una sola petición, sin redirecciones ni reintentos automáticos.
- Timeout de inactividad de 10 segundos y duración total HTTP de 30 segundos. Consumir respuesta por fragmentos sin buffering ilimitado, hasta 65.536 bytes; propuesta JSON hasta 16.384 bytes, con profundidad acotada.
- Solicitar una única propuesta, máximo 1.024 tokens de salida y `store: false`. Rechazar respuestas truncadas, filtradas, refusals o estructuras inesperadas.
- Máximo configurable de dos llamadas simultáneas por instalación con el bloqueo existente. Sin espera si no hay un hueco y liberación/cancelación también al fallar.
- Conservar el documento para revisión manual si falla/satura la IA, sin datos de ejemplo enmascarando un fallo del proveedor. Sin clave continúa el adaptador de ejemplo explícito. La IA nunca confirma ni valida registros.

## Fuera de alcance

Cambiar modelo/precios/planes, presupuesto mensual monetario o tokenización exacta de PDFs/imágenes, extracción asíncrona, despliegue Hostinger, antimalware aplazado, llamadas reales al proveedor, documentos fiscales reales y recalcular la auditoría histórica.

## Criterios de aceptación

- El adaptador rechaza entradas excesivas antes de hacer HTTP y no sigue redirecciones ni reintenta errores.
- El cliente tiene límites de tiempo y salida explícitos; la respuesta se cancela al exceder tamaño, sufrir timeout o fallar su validación.
- Una tercera llamada concurrente no sale al proveedor; errores y éxitos liberan el hueco. No hay espera por un hueco de IA.
- Proveedor simulado correcto, lento, excesivo, truncado, malformado y con error. Verificar opciones HTTP y petición, sin llamadas externas ni secretos en las evidencias.
- El caso de uso conserva archivo/registro y revisión humana tras fallo/saturación; no confirma automáticamente ni duplica consumo mensual.
- Regresiones relevantes, formato compatible con CI PHP 8.4, análisis estático y documentación reproducible. Lo no ejecutado sigue pendiente.

## Comprobado

- Cliente dedicado con reintentos/caché desactivados, timeout 10, max_duration 30, max_redirects 0 y buffer false por petición. Entrada de 8.000.000 bytes, JSON de petición de 11.000.000, respuesta de 65.536 y propuesta de 16.384; profundidad JSON 16. Límites comprobados en progreso y lectura de fragmentos, no mediante toArray ilimitado.
- Una propuesta con 1.024 tokens de salida y store false. Rechazo de truncamiento, refusal, tool_calls, JSON mal formado y respuestas inesperadas. Cancelación y liberación del hueco tras éxito/error; dos huecos no bloqueantes por instalación, sin llamada al saturarse ni con fallo del almacén de bloqueos.
- PHPUnit completo: **438 pruebas / 1.236 aserciones**, correctas. Incluye entrada en el máximo con base64 de barras, respuesta excesiva anunciada y por fragmentos sin leer el resto, error/redirección/429, inactividad y duración total simuladas, slots y recuperación manual conservando archivo, registro y una llamada de cuota. Sin proveedor real.
- Regresiones: subida documental **43**, documentos API **108** y recursos F09 **56**, correctas. La primera ejecución secuencial de trimestres API recibió una respuesta inesperada en la comprobación de cliente vinculado; la repetición aislada pasó sus **60** comprobaciones. No se atribuye una causa ni se cambia autenticación fuera de alcance.
- PHPStan, contenedor y **24 YAML** correctos. PHP-CS-Fixer con **PHP 8.4** sobre copia temporal LF y composer.json del proyecto: **390 archivos, cero cambios requeridos**. La copia de comprobación no modifica el repositorio. Sin dependencias ni migraciones nuevas.
- [Funcionamiento y reproducción](../security/ai-resource-limits.md), [evidencia](../security/evidence/f10-ai-resource-limits.json). Pendientes proveedor real/modelo/coste/calidad, temporización real y Hostinger/múltiples nodos. No se han enviado documentos ni claves a servicios externos. Auditoría histórica y puntuación intactas; tarea 43 continúa abierta.
