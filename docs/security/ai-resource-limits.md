# Límites de extracción IA · F10

El adaptador de OpenAI conserva Chat Completions, el modelo configurado en `OPENAI_MODEL` y la revisión humana. Esta entrega añade límites técnicos y recuperación manual; no cambia de proveedor ni realiza llamadas reales durante las pruebas. [Ficha](../sdd/ai-resource-limits.md).

## Petición y respuesta acotadas

- Entrada: 8.000.000 bytes, no vacía y con tipo PDF, JPEG, PNG o WebP. Cuerpo JSON: hasta 11.000.000 bytes antes de enviar. Base64 usa barras sin escape para no duplicar su tamaño al codificar JSON.
- Una petición POST al endpoint HTTPS fijo. Cliente dedicado `ai_extraction.client`, sin redirecciones, caché ni reintentos automáticos. Un 429/500 del proveedor no genera una segunda llamada.
- Inactividad de 10 segundos y duración total HTTP de 30 segundos mediante Symfony HttpClient. La recepción comprueba también el tiempo comunicado por el transporte.
- Buffering desactivado; máximo 65.536 bytes de respuesta, comprobado en progreso de descarga, tamaño anunciado y fragmentos recibidos. La propuesta de texto admite hasta 16.384 bytes. Ambos JSON tienen profundidad máxima 16.
- Se solicitan `n: 1`, `max_completion_tokens: 1024` y `store: false`. Solo se admite una respuesta finalizada con `finish_reason: stop`, contenido de texto y sin refusal ni tool_calls. Una respuesta truncada, incluso con JSON aparentemente utilizable, no se convierte en propuesta.

El lector mantiene sus reglas fiscales previas: cuotas y totales se calculan en el dominio, IPSI manual, retención solo cuando procede y ninguna exención asignada por IA. El adaptador no confirma ni valida un documento.

La respuesta HTTP se cancela al terminar o fallar. Los errores de transporte/payload se convierten en un error genérico sin encadenar la excepción del proveedor, su cuerpo ni la clave. No se añaden registros de documentos, claves o respuestas. Esto no sustituye la revisión global de logging/retención pendiente.

## Concurrencia y revisión manual

`app.ai_max_concurrent` vale **2**: se toman uno de dos bloqueos exclusivos de la instalación con la infraestructura Symfony Lock existente. Si ambos están ocupados, no se espera ni se envía una petición. Se libera el hueco en `finally`, también tras un error o una respuesta excesiva. El backend local flock libera sus recursos al acabar el proceso; no se presenta el TTL solicitado como sustituto del timeout HTTP.

El documento ya admitido permanece subido para introducir sus datos manualmente si la IA falla, está ocupada o no puede acceder al almacén de bloqueos. Conserva archivo, registro y una sola unidad de cuota mensual, igual que cualquier documento creado. No se devuelve un 429 de subida en este caso: se ha aceptado el documento, aunque no se haya obtenido propuesta. Los 429 de F09 suceden antes del controlador y no crean ni consumen documentos. Son situaciones distintas.

Sin clave, `ConfiguredDocumentExtractor` mantiene el adaptador de ejemplo y su advertencia visible. Con clave y fallo del proveedor no se sustituye por un ejemplo que pueda confundirse con una lectura real. La pantalla conserva el mensaje existente para escribir los datos manualmente.

## Configuración y despliegue

Los parámetros están en `config/services.yaml`:

```yaml
app.ai_max_concurrent: 2
app.ai_max_completion_tokens: 1024
```

Se admiten 1–8 huecos y 1–4.096 tokens como límites de configuración. Valores fuera del rango impiden construir el servicio. El cliente dedicado está en `config/packages/framework.yaml`; los límites de bytes están junto al adaptador. Sin nuevas dependencias, migraciones, credenciales ni cambios de modelo.

Con varios nodos, los bloqueos deben compartir un backend que permita exclusión entre instancias; flock solo cubre los procesos del mismo entorno local. Hostinger y concurrencia entre máquinas no están comprobados.

El tope de salida no limita exactamente los tokens de entrada de PDFs/imágenes ni constituye un presupuesto mensual monetario. El tamaño de archivo, los presupuestos de subida F09 y la concurrencia reducen el consumo, pero el coste depende del contenido y modelo. La configuración de cuenta, acceso al modelo, presupuestos del proyecto y capacidades reales del despliegue se revisarán antes de producción. `store: false` es una opción de la petición; no se presenta como garantía de retención cero del proveedor.

## Verificación reproducible

```powershell
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T app php vendor/bin/phpstan analyse --no-progress
docker compose exec -T --user www-data app php bin/console lint:container
```

Las pruebas usan MockHttpClient y almacenamiento de bloqueos en memoria. Cubren opciones y cuerpo, entrada exactamente en el máximo y fuera de él, respuesta por fragmentos, rechazo temprano por tamaño anunciado, propuesta excesiva, JSON inválido, truncamiento, refusal, redirección, 429/500, inactividad y duración total simuladas. Comprueban cancelación de las respuestas emitidas, liberación del hueco, saturación sin llamada y fallo del almacén de bloqueos. El caso de uso comprueba archivo y registro subido sin propuesta ni cifras confirmadas, y una sola llamada al contador documental ante fallo/saturación.

La duración simulada no espera 30 segundos ni mide latencia real: se verifican las opciones del transporte y las rutas de rechazo. No se prueba el servicio real, su facturación, calidad con documentos reales ni la temporización del hosting. Las regresiones HTTP usan sus fixtures sintéticos y adaptadores de prueba; no envían documentos al proveedor.

La comprobación de formato PHP 8.4 se hace sobre una copia temporal con finales LF como el checkout de GitHub, sin reescribir el repositorio de Windows. Una comprobación directa sobre CRLF puede producir diferencias de líneas ajenas al cambio. PHP-CS-Fixer y PHPStan mantienen sus reglas existentes.

## Referencia oficial

[Create chat completion](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create), consultada el 15/11/2026: `max_completion_tokens` acota la generación, `n` fija el número de propuestas y `store` controla el almacenamiento de completions. Se mantiene el contrato existente, sin migrar a Responses ni cambiar el modelo.
