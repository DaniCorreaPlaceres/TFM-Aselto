# Registros exentos · tarea 41

## Objetivo

Permitir marcar expresamente un registro como exento de su impuesto indirecto, manteniendo IVA, IGIC o IPSI y distinguiendo exención del tipo cero. Cuota cero y sin recargo; retención opcional en ingresos de autónomos. La persona decide la exención.

## Fuera de alcance

Decidir si corresponde una exención, determinar motivos o normativa fiscal, no sujeción, modelos oficiales y modificar cierres históricos automáticamente.

## Criterios de aceptación

- Estado exento independiente del porcentaje: sin tipo aplicable, cuota cero con base conocida y total base menos retención cuando proceda; rechazar porcentaje o recargo contradictorios en dominio/API.
- Revisión web y API comunes: selección explícita, confirmación humana, permisos y versiones existentes. La IA no asigna exención.
- Preservar territorio y documentos existentes como no exentos; históricos sin atributo se restauran como no exentos, sin reescribir instantáneas.
- Marcar/desmarcar requiere las correcciones y reaperturas habituales; estados validados/cierres no se eluden.
- Exención visible en documentos, revisión, importes, movimientos, exportaciones, API e historial/instantáneas.
- Verificar migración sin cambios en datos anteriores, esquema, cálculos, incompatibilidades, permisos/cierres y recorrido web/API; registrar comprobaciones y límites.

## Comprobado

- Dominio: cuota cero y total con IRPF en IVA/IGIC/IPSI; distinción del 0 %, base ausente, rechazo de recargo y porcentaje contradictorios. Extractor incapaz de asignar exención y retorno a revisión manual. Históricos anteriores sin indicador se restauran como no exentos.
- 346 pruebas PHPUnit / 860 aserciones y 33 de frontend correctas; PHPStan sin errores. Formato PHP, OpenAPI, 61 plantillas Twig y Sass comprobados.
- API documental: 108 comprobaciones con exención en los tres territorios, booleano estricto, contradicciones (incluido porcentaje 0 y no admitido), IRPF de autónomo, restricción en sociedades, validación de asesoría, estado validado, bloqueo por cierre de origen y destino y corrección tras reapertura. La instantánea previa mantiene su exención.
- Web documental: 43 comprobaciones, incluida confirmación de exento con eliminación de porcentaje/recargo y conservación de retención. Trimestres: 43 comprobaciones. Actualización en vivo real: 38, incluidas etiquetas Exento en movimiento actualizado y cierre congelado. Los workflows retiran datos y archivos sintéticos.
- Migración desde esquema antiguo con datos: preserva campos anteriores e inicializa como no exentos. 13 controles de esquema y 20 de regresión de licencias/cuotas correctos. Se adaptó la semilla del antiguo esquema para no depender de columnas futuras del ORM.
- Entorno local: copia previa a migración; 44 documentos y cinco trimestres guardados conservan sus datos e instantáneas. Nueva columna sincronizada con Doctrine; no se reescribieron históricos anteriores.
- Visual en escritorio y móvil de 320 px: checkbox con área de 48 px, cuota cero, recargo retirado, retención del 15 % sobre 200 € y total 170 €. Ancho de contenido 305 px, sin desbordamiento. Cambios del formulario descartados sin guardarlos y conservación de datos nuevamente comprobada. Captura en `var/tax-exemption-review.png`.
- Movimientos e historial muestran el territorio y Exento; Excel/CSV reutilizan ese texto y su formateador probado. No se ha descargado manualmente un Excel/CSV con exentos ni se ha realizado prueba con lector de pantalla o recorrido completo con JavaScript desactivado. La normalización del formulario sin JavaScript sí se ha comprobado por HTTP.
