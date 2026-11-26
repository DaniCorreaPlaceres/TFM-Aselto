# Revisión final del TFM · tarea 47

## Objetivo y alcance

Recopilar el resultado de los recorridos funcionales y operativos realizados en Hostinger, distinguir evidencia directa de confirmaciones del usuario y declarar las comprobaciones aplazadas o incompletas. El usuario acepta expresamente el cierre con límites el **26/11/2026**. No se inicia aquí la presentación de la tarea 48.

## Fuera de alcance

Certificar conformidad OWASP o preparación comercial, resolver todos los hallazgos, sustituir pruebas no ejecutadas por una aceptación general o publicar secretos, credenciales y documentos reales.

## Criterios de cierre

- Recorridos comprobados y fuentes de evidencia identificados.
- Incidencias encontradas registradas, con corrección y resultado productivo diferenciados.
- Aplazamientos y cobertura incompleta explícitos y conservados tras el cierre.
- Aceptación expresa del usuario para cerrar la revisión con estos límites.

## Resultado

Publicadas y etiquetadas **1.0.0** y **1.0.1**, con CI/CD correcto, versión pública, salud, esquema y caché comprobados. La incidencia de listado de mensajes se reprodujo, corrigió, probó y publicó como parche 1.0.1; el usuario confirmó el resultado en Hostinger.

El asistente comprobó directamente las ejecuciones de GitHub, las etiquetas, el footer y respuestas públicas, cabeceras seleccionadas y bloqueo de archivos internos. El usuario realizó los recorridos autenticados con cuentas de prueba y comunicó sus resultados en el chat; no se presentan como observación directa del asistente ni como una colección de capturas que no existe.

Recorridos seleccionados confirmados:

- Vinculación por invitación y aceptación: correo, ausencia de acceso previo, contacto de asesoría, acceso posterior, pendientes e histórico personal diferenciado.
- Validación y efecto en cifras; cierre/reapertura con historial; bloqueo de cierre por pendientes y documentos sin fecha; rechazo, corrección y reenvío sin duplicar.
- Justificantes y archivos originales; mensajes y adjuntos en ambos sentidos; solicitudes que requieren resolución por la asesoría.
- Excel/CSV filtrados y legibles; variante de varias páginas no confirmada expresamente.
- Aislamiento web entre otro cliente, segunda asesoría y superadmin.
- Casos API de identidad, documentos, archivos, conversaciones/adjuntos y trimestres; denegación de cambios concretos; cierre, rechazo de versión obsoleta y reapertura.

Detalle, códigos esperados confirmados y limitaciones de cada caso en [release-1-0-0.md](release-1-0-0.md) y [release-1-0-1.md](release-1-0-1.md). No se extiende el resultado de un papel del equipo no especificado simultáneamente a admin y gestor.

## Límites aceptados y conservados

- **Aplazados por decisión del usuario:** copia externa y ensayo de recuperación de justificantes/adjuntos desde copia reciente. Las copias concretas de ambos despliegues no se han restaurado. La recuperación inicial de un original no demuestra la de esos otros archivos.
- **Seguridad:** HSTS no aparece en las respuestas públicas comprobadas; antimalware F08 aplazado; restantes límites legales y de seguridad conservados. No se recalcula la puntuación de la auditoría histórica.
- **Capacidad:** sin ensayo de carga/concurrencia productiva ni valores numéricos de PHP aportados en esta ronda. No se acredita cobertura de cada ruta, papel y mutación de la API.
- **Operación:** cron confirmado de forma general, sin fecha/hora y salida exactas de la última ejecución. Alertas con umbral y borrado real de registros antiguos no ensayados; comportamientos restantes del proxy sin cobertura completa.
- **OPcache:** se mantiene la suposición operativa aceptada sobre la purga manual del sitio en hPanel; no se ha demostrado su invalidación efectiva mediante el despliegue.

## Estado de entrega documental

Tarea 47 marcada como terminada por aceptación expresa, con estos límites. Evidencias consolidadas en la rama `feature/release-1-0-1-verification`, conservando las copias de trabajo originales y la documentación independiente de pruebas. Commit documental solicitado por el usuario el 26/11/2026; PR a develop pendiente. Estos cambios no modifican la aplicación desplegada ni las etiquetas publicadas.

La siguiente tarea es la **48: presentación y guion del vídeo**, después de integrar estas evidencias. Antes de un uso comercial real deberán revisarse los límites operativos, de seguridad y legales que aquí quedan declarados.
