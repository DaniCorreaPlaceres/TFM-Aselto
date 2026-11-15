# Comprobaciones de seguridad en CI · tarea 43 / F13

## Objetivo

Ejecutar en cada PR a develop/main y push a esas ramas las integraciones sintéticas de autenticación, permisos, aislamiento y archivos. Rama `feature/security-ci-checks`, desde develop `ce4eab6` tras PR #58.

## Cambios planteados

- Job independiente `Seguridad web y API`, con MySQL desechable, claves JWT efímeras, recursos locales preparados y transporte de correo nulo. Sin secretos productivos ni llamadas a IA.
- Ejecutor reproducible de una lista cerrada de workflows, en procesos separados y de forma secuencial, deteniéndose con error ante el primer fallo. Rechazar ejecución sin autorización explícita de base desechable y nombre/estado inicial adecuados.
- Cubrir web/API: autenticación y revocación, enlaces canónicos, límites y persistencia, contraseñas, sesión/logout, aislamiento documental y archivos, comunicación, trimestres, administración, cabeceras y registro privado.
- Verificar localmente en una base independiente creada para esta tarea. Demostrar que una regresión deliberada de permisos hace fallar el ejecutor, usando únicamente una copia aislada que nunca se integra.
- Documentar cobertura, ejecución, resultados y propuesta concreta de checks obligatorios. Consultar las reglas actuales sin modificarlas.

## Fuera de alcance

Cambiar protecciones sin autorización específica, publicar un PR o commit antes de que se solicite, despliegue, secretos de Hostinger, escáner antimalware aplazado, puntuación histórica y ejecución remota de GitHub antes de subir la rama.

## Criterios de aceptación

- El job se ejecuta sin filtros de rutas y sin continue-on-error; fallo o timeout de un workflow produce estado fallido.
- Base efímera separada, entorno de desarrollo explícito para los kernels existentes, claves de prueba generadas y directorios privados fuera de public. Sin usar la base local habitual ni correo/IA externos.
- Suite elegida pasa sobre esquema migrado y conserva el aislamiento entre procesos. Un cambio deliberado de aislamiento es detectado; la copia vulnerable se elimina y el código habitual permanece intacto.
- Reglas y propuesta de checks documentadas; las protecciones no se cambian en esta entrega sin aprobación adicional. No afirmar que GitHub bloquea un merge hasta verificarlo con las reglas activas.

## Comprobado

Preparado y verificado localmente el 15/11/2026. [Cobertura, operación y propuesta de reglas](../security/security-ci-checks.md), [evidencia](../security/evidence/f13-security-ci-checks.json).

- Los 19 workflows pasan en una copia aislada con base nueva y 19 migraciones, correo nulo, extracción sintética y JWT efímeros. La versión final comprueba tablas de negocio vacías antes y después de cada workflow.
- Mutación solo en la copia: retirar el permiso de propietario provoca salida 1 y el fallo `Another client must not read an original or its metadata.`. Archivo restaurado, hash comparado y suite completa correcta después. El código de negocio habitual no cambia.
- Guardas comprobadas: falta de opt-in, nombre no permitido, selección desconocida y base con datos rechazadas antes de workflows. La fila sintética de la última prueba se elimina.
- PHPUnit **449 / 1.286**, PHPStan incluyendo el ejecutor, YAML y formato PHP 8.4 sobre **405 archivos** correctos.
- Se adaptan dos fixtures para CI: contadores propios de validación de comunicación y cliente sintético propio de bloqueo trimestral. No se relajan límites ni permisos productivos.
- La intermitencia JWT local se reproduce con un retroceso de reloj de dos segundos. Se conserva el rechazo y se documenta; no se añade tolerancia ni reintentos. La prueba local reutiliza dependencias/Importmap y usa PHP 8.5; no equivale al runner remoto PHP 8.4.
- Reglas consultadas en modo lectura: main exige PR pero no checks; develop sin protección clásica y sin ruleset específico. Propuesta de seis checks para ambas ramas documentada; no se ha aplicado ninguna regla.
- Al terminar se comprueba que las tablas temporales de negocio están vacías y se retiran la base, su permiso SQL específico y la copia aislada; la base habitual no se modifica.

**Actualización posterior, 15/11/2026:** el PR #59 ejecutó correctamente los seis checks y 19 workflows en GitHub con PHP 8.4. Tras autorización explícita se aplicaron las protecciones de ambas ramas y el PR temporal #60 demostró el bloqueo con un fallo sintético de PHPUnit y los otros cinco checks verdes. Se cerró sin merge y se eliminó su rama. [Ficha de cierre](security-merge-protection.md) y [evidencia](../security/evidence/f13-merge-protection.json). F13 verificada en ese alcance; la puntuación histórica permanece intacta y `config/reference.php` queda fuera.
