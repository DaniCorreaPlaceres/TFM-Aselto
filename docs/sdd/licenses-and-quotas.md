# Licencias y cuotas · tarea 39

## Objetivo

Asignación manual por superadmin de Gratis y Profesional a independientes y Gratis/Asesoría a asesorías. Gratis limita a 20 registros documentales por mes natural de Canarias; planes de pago sin límite mensual, con los límites actuales de archivo. Web y API comparten el control.

## Fuera de alcance

Cobros, suscripciones, facturación y prorrateos. El superadmin no consulta documentación, importes fiscales ni consumo documental individual.

## Criterios de aceptación

- Cada registro creado consume una unidad incluso si queda borrador o se rechaza; correcciones y justificantes no consumen; borrar no devuelve cuota.
- Cambios inmediatos conservando registros y consumo. Clientes vinculados cubiertos por el plan Asesoría; sin él se aplica Gratis. Profesional se aplica solo mientras sea independiente.
- Mes Atlantic/Canary, control atómico ante subidas simultáneas y sin consumo en intentos fallidos.
- Histórico existente incorporado al consumo sin alterar documentos; no se reconstruyen borrados anteriores a esta funcionalidad.
- Asesoría cuenta clientes vinculados con cuenta activada, una vez por cuenta; importe de referencia, no factura.
- Asignación protegida por rol y CSRF; consulta propia sin identificadores arbitrarios; API responde con error comprensible al llegar al límite.
- Migración, pruebas de límites, cambios de plan, concurrencia y regresión; lista actualizada solo con evidencias comprobadas.

## Comprobado

Comprobados: 336 pruebas PHPUnit/820 aserciones, PHPStan sin errores, OpenAPI válido, Twig y contenedor válidos, migración con originales preexistentes preservados y meses de verano/invierno correctos, 13 comprobaciones de esquema, 20 de licencias con carrera de dos procesos, 21 del formulario de subida y administración (rol, CSRF y asignación) y 77 de API documental. Revisión visual en escritorio y 320 px e integración local de las seis ramas realizadas. Evidencias conjuntas y límites de la revisión en `../public-content-batch.md`.
