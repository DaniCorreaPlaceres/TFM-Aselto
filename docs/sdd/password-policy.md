# Política de contraseñas y lista local · tarea 43 / F04

## Objetivo

Aplicar una política compartida al elegir contraseña en activación, recuperación y aceptación de invitaciones. Rechazar contraseñas comunes sin consultas externas ni exposición de credenciales. Rama desde develop tras integrar F03.

## Cambios planteados

- Mínimo 15 caracteres Unicode al no existir MFA; máximo 128 caracteres, sin truncar, recortar espacios ni imponer composición/rotación periódica. Conservar exactamente la contraseña aceptada para el hash.
- Lista local «10k-most-common» de SecLists: contiene realmente 10.001 entradas distintas; procedencia y licencia documentadas, fijada a un commit y hash. Consulta normalizada solo para detectar variantes comunes; nunca normalizar la credencial guardada.
- Puerto de dominio para la consulta de contraseñas comunes, política de aplicación compartida y adaptador de archivo privado. Sin dependencias de HTTP, Doctrine ni proveedor externo en dominio/aplicación.
- Mensajes sencillos en español en formularios, errores diferenciados de longitud/lista y validación previa a hash/escritura/consumo del enlace.

## Fuera de alcance

Forzar cambios a usuarios existentes, modificar contraseñas de demo, cambiar algoritmo/formato de hash, MFA, consultar servicios de contraseñas filtradas ni actualizar la puntuación histórica de auditoría. Otros hallazgos quedan pendientes.

## Criterios de aceptación

- Activación, recuperación e invitaciones usan la misma política; rechazar un valor conserva enlace, cuenta y versión de autenticación.
- Lista completa disponible e íntegra, fuera de public. Si falta o no se puede leer, no aceptar contraseñas silenciosamente.
- Probar contraseñas comunes de longitud válida, variantes de mayúsculas/espacios, límites 14/15/128/129, Unicode y frases sin símbolos. Hash/login conservan espacios y caracteres, incluidos valores largos que comparten los primeros 72 bytes.
- Las contraseñas existentes siguen autenticando. Mensajes y atributos del formulario actualizados; sin imprimir contraseñas reales, tokens o secretos en evidencias.
- Pruebas de dominio/aplicación y recorridos HTTP sintéticos de activación/recuperación/invitación; regresiones de autenticación, revocación y límites previos.
- Documentar resultados ejecutados y límites de una lista finita, conservar auditoría original y dejar el resto de tarea 43 pendiente.

## Comprobado

- Rama desde develop `7d4efd8`, tras PR #50. Política compartida y puerto/adaptador local, sin cambios de algoritmo, rutas API, contraseñas heredadas ni migraciones.
- Lista fijada a commit y digest, licencia MIT copiada y procedencia documentada en [password-list.md](../security/password-list.md). Se verifican 10.001 entradas importadas; nueve complementos en código. Recurso no público y bytes preservados con .gitattributes.
- PHPUnit completo: **388 pruebas / 1.017 aserciones**, correctas. Límites Unicode, valores comunes cortos/largos, variantes, frases, integridad, archivo ausente/alterado y conservación de hash largo/espacios.
- HTTP activación/recuperación **33 comprobaciones**, correcto: errores 422, tokens y credenciales conservados al rechazar, aceptación posterior y uso único, legado de ocho caracteres y sufijos distintos después de 72 bytes. Invitaciones **118 comprobaciones**, correctas para los tres destinos.
- Regresiones autenticación **74**, límites **45** y asesorías/cambio de correo **70**, correctas. Sin SMTP/IA externos; datos sintéticos revertidos/retirados.
- [Probe posterior](../security/evidence/f04-after-policy.json) rechaza comunes corta/larga, conserva F01/F02 e identidades tras rollback. La auditoría original no cambia de puntuación.
- PHPStan, contenedor, 61 Twig y 23 YAML correctos. Revisión visual de navegador y despliegue productivo no ejecutados. Tarea 43 sigue abierta para otros hallazgos.
- Formato de los 16 PHP modificados/nuevos y `git diff --check` de la tarea, correctos. El cambio local previo de config/reference.php queda fuera de la entrega.
