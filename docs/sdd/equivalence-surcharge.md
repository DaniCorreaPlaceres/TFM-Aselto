# Recargo de equivalencia en gastos

## Objetivo

Permitir registrar el recargo que figura en un gasto con IVA, calcular su cuota y sumarla al total. La persona revisa los datos y confirma; la aplicación no determina si el cliente está acogido a un régimen fiscal.

## Fuera de alcance

IRPF, regímenes fiscales del perfil, tipos históricos temporales, facturas con varias bases o tipos, recargos de otros tributos, trimestre y validación de la asesoría.

## Criterios de aceptación

- La selección es opcional: sin recargo por defecto. Los documentos anteriores mantienen importes y estado y no llevan recargo.
- Solo se admite en gastos con IVA: 5,2 % para IVA 21 %, 1,4 % para IVA 10 %, 0,5 % para IVA 4 %. El 1,75 % de tabaco se identifica expresamente y se admite con IVA 21 %. No se aplica a ingresos, IGIC, IPSI ni IVA 0 %.
- La cuota se calcula sobre la misma base, con aritmética entera y redondeo al céntimo. El total es base más cuota de impuesto más cuota de recargo. El navegador y el servidor calculan lo mismo.
- La revisión muestra tipo y cuota de recargo por separado. Se puede guardar borrador, corregir o quitar el recargo y confirmar. Un porcentaje incompatible se rechaza también en el servidor y se explica junto al campo.
- Al cambiar el tipo de movimiento o el IVA, la pantalla elimina las selecciones que dejan de ser compatibles y actualiza el total.
- La extracción puede proponer un recargo explícito y compatible, pero no lo deduce por el tipo de IVA. Se ignoran cuotas y totales propuestos por IA. El ejemplo no inventa un recargo y el fallo de lectura permite entrada manual.
- El recargo y su cuota se conservan al guardar y recuperar el documento. Los permisos del propietario siguen vigentes.
- Se comprueban los tipos, el redondeo, los casos incompatibles, el guardado, la confirmación, la extracción, la persistencia y la conservación de documentos existentes.

## Referencias

- [AEAT: tipos del recargo de equivalencia](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/manual-iva-2025/capitulo-06-regimenes-especiales-iva/regimen-especial-recargo-equivalencia/tipos-recargo-equivalencia.html).
- [AEAT: instrucciones de 2026](https://www3.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-iva-autoliquidacion_/instrucciones-2026/instrucciones-02-12-2t-4t-2026.html). Consultadas el 8 de noviembre de 2026.

## Comprobado

- PHPUnit en Docker: 133 pruebas y 346 aserciones correctas. Se comprueban tipos, redondeo, incompatibilidades, entradas mal formadas, borrador parcial, retirada del recargo, confirmación, acceso del propietario, extracción y mapeo de persistencia.
- PHPStan nivel 6 sin errores. Twig y OpenAPI válidos. PHP-CS-Fixer sin diferencias en una copia LF de los 41 archivos PHP del módulo, sus pruebas y la migración, como en Linux.
- Controlador de pantalla ejecutado con Node: doce escenarios de importes, base inválida, retirada de selección incompatible y ocultación en ingresos correctos.
- Migración Version20261108100000 aplicada en MySQL local. Los ocho documentos existentes conservan todos sus datos e importes y tienen las dos columnas nuevas a null.
- Peticiones GET y POST al núcleo HTTP de Symfony: rechazo de recargo en ingreso, guardado de borrador y confirmación de gasto con base 100 €, IVA 21 % y recargo 5,2 %. Muestra cuota de recargo 5,20 € y total 126,20 €. Se recuperaron de MySQL el recargo, su cuota, el total y el estado enviado. Las cuentas y documentos temporales se revirtieron.

## Pendiente

- No se ha probado la extracción con el proveedor real ni se ha realizado una revisión visual en navegador. La prueba HTTP se ejecutó contra el núcleo de Symfony, sin pasar por Nginx.
- Los avisos anteriores de OPcache y de versión mínima de PHP siguen presentes; no se modifican en esta tarea.
