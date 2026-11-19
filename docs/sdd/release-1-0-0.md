# Publicación de Aselto 1.0.0

## Objetivo

Publicar la versión 1.0.0 del TFM con la vinculación consentida de clientes existentes, mediante PR a main y el CI/CD autorizado de Hostinger. Solicitud del usuario del 19/11/2026. Rama `feature/release-1-0-0` desde develop; integración previa a develop y después PR de develop a main.

## Cambios planteados

- Sincronizar el último main en la rama de preparación para cumplir las comprobaciones estrictas sin modificar protecciones.
- Cambiar la única fuente `app.version` a `1.0.0`; conservar la versión independiente del contrato OpenAPI.
- Actualizar notas de versión y estado de publicación con límites reales, sin atribuir a producción pruebas locales.
- Verificar comando/footer y checks de GitHub antes de integrar. Mantener el interruptor autorizado de despliegue automático.
- Tras integrar main, verificar el despliegue, migración, salud y footer. Asociar `v1.0.0` al commit exacto verificado de main, sin reutilizar etiquetas.
- Preparar pruebas guiadas con el usuario en Hostinger: vinculación, permisos e histórico y demás recorridos finales. Registrar solo resultados observados.

## Fuera de alcance

Cambiar protecciones, activar Git nativo de Hostinger, acceder con credenciales del usuario, cargar datos fiscales reales, resolver todos los pendientes de seguridad/legales o declarar cerrada la tarea 47 sin probarla. La documentación guardada de otra tarea y `config/reference.php` quedan fuera.

## Criterios de aceptación

- Producto y footer identifican `1.0.0` desde el mismo parámetro.
- PR y checks correctos, sin push directo a main. Despliegue automático identificado por SHA, copia previa, migración y esquema correctos.
- Etiqueta anotada solo sobre el commit final de main comprobado; no afirmar publicación antes del resultado real.
- Comprobaciones de Hostinger documentadas como realizadas o pendientes; no exponer claves ni documentos.

## Estado

Preparación local comprobada el 19/11/2026: `app:version` devuelve `1.0.0`, YAML válido y home local HTTP 200 con footer `Versión 1.0.0`. La rama incorpora el último main sin modificar archivos de producto en esa sincronización. El PR #74 de vinculación tiene los seis checks obligatorios y su paquete correctos en GitHub.

Notas actualizadas con los límites aceptados. Integración de versión, despliegue, etiqueta y pruebas productivas de la vinculación pendientes. No se repite la batería local por un cambio de parámetro/documentación; GitHub ejecutará las comprobaciones obligatorias completas.
