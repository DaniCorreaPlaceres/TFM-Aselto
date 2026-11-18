# Demostración del TFM

Todos los nombres, contactos, importes y archivos de esta carga son ficticios. Los PDF indican que son una demostración sin validez fiscal. No se envían correos ni documentos a IA externa.

## Preparar

Con Docker arrancado y la base migrada:

`docker compose exec -T --user www-data app php bin/console app:seed-demo`

El comando solo funciona en desarrollo. Crea dos asesorías, diez accesos activados, cinco clientes, 33 documentos, cinco cierres del tercer trimestre de 2026 y doce conversaciones, con cuatro adjuntos generales. El cuarto trimestre de 2026 queda abierto.

Puede repetirse: si la carga está completa, conserva datos, contraseñas y archivos tal como estén. No reinicia los ejercicios realizados, no cambia los accesos anteriores y no restaura archivos eliminados. Si faltan registros originales o encuentra un correo o identificador ocupado, se detiene sin sobrescribir ni reparar automáticamente. No incluye una opción de borrado o reinicio. Un fallo durante la primera carga revierte sus datos y elimina únicamente los archivos que acaba de crear.

## Accesos

Contraseña inicial para todos los accesos siguientes: **`aselto-demo`**. Se usa solo en el entorno local. Si se cambia una contraseña, repetir la carga no la restablece.

- `demo-superadmin@example.test`: superadmin; consulta asesorías y clientes independientes. No tiene acceso fiscal a los clientes de asesorías.
- `demo-admin-sol@example.test`: admin de Asesoría Sol.
- `demo-gestor-sol@example.test`: gestor de Asesoría Sol.
- `demo-autonomo-sol@example.test`: autónomo de Madrid, IVA y retención opcional en ingresos.
- `demo-sociedad-sol@example.test`: sociedad de Ceuta, IPSI manual al 3 %.
- `demo-admin-atlantico@example.test`: admin de Asesoría Atlántico.
- `demo-gestor-atlantico@example.test`: gestor de Asesoría Atlántico.
- `demo-autonomo-atlantico@example.test`: autónomo de Las Palmas, IGIC al 7 % y retención opcional en ingresos.
- `demo-sociedad-atlantico@example.test`: sociedad de Madrid, IVA.
- `demo-independiente@example.test`: autónomo sin asesoría, con confirmaciones y cierres personales.

Sol y Atlántico tienen cada una dos clientes, un admin y un gestor. El cliente independiente no pertenece a ninguna.

## Recorridos

1. **Trimestre cerrado.** Entrar como cliente o como equipo de su asesoría y elegir año 2026, trimestre 3. Incluye ingreso de 1.000 € de base y gasto de 200 € de base: resultado de 800 €. Las cuotas se muestran por territorio; las retenciones y el recargo, separados. El cierre conserva su instantánea y autoría. Intentar corregir un documento exige reabrir el periodo.
2. **Revisión.** Como admin o gestor, abrir la bandeja de documentos del trimestre 4 de 2026. Cada cliente de asesoría tiene un ingreso y un gasto validados, un gasto enviado pendiente, otro rechazado con motivo y otro con petición de justificante. Validar el pendiente, corregir y reenviar el rechazado desde el cliente y adjuntar un justificante a la petición. Los originales PDF se pueden descargar.
3. **Impuestos.** Comparar el autónomo de Sol (IVA), el de Atlántico (IGIC) y la sociedad de Sol (IPSI). Los gastos con IVA muestran recargo del 5,2 %; los ingresos de autónomos, retención del 15 %. Son ejemplos de los campos admitidos por el TFM, no una recomendación fiscal.
4. **Cierre bloqueado.** Intentar cerrar el trimestre 4 antes de resolver sus pendientes. La operación se rechaza. Resolverlos, consultar las cifras y cerrar; reabrir conserva el histórico.
5. **Control personal.** Entrar como cliente independiente. El trimestre 4 contiene un ingreso confirmado, un borrador y un documento sin fecha. Ambos pendientes impiden cerrar. El trimestre 3 conserva el cierre personal anterior; el documento sin fecha es una subida posterior que no modifica su instantánea.
6. **Comunicación.** Cada cliente de asesoría tiene una conversación ordinaria con un PDF adjunto, una solicitud pendiente ya respondida y una solicitud resuelta. Leer desde admin y después desde gestor para comprobar que la lectura es individual. La respuesta del cliente no resuelve la solicitud; lo confirma el equipo.
7. **Aislamiento.** Comparar los listados de Sol y Atlántico. Cada equipo solo ve sus clientes, documentos y conversaciones. Intentar abrir una URL de la otra asesoría no da acceso. El superadmin tampoco obtiene acceso fiscal por esas rutas.
8. **API.** Abrir [Swagger UI](http://localhost:8080/api/docs), obtener token con uno de los accesos y usar «Authorize». Consultar documentos, trimestres y comunicación. Usar `owner_id` de destinatarios para crear conversaciones y `client_id` para consultar el trimestre desde la asesoría. Las mismas restricciones se aplican por API.

Los ejemplos usan fechas fijas de 2026 para que sus cifras se puedan repetir en la defensa. Si se prueba en otro año, seleccionar esos periodos expresamente. La fecha actual no desplaza los datos automáticamente.

## Verificación repetible

`docker compose exec -T --user www-data app php tests/Integration/demo-data-workflow.php`

La prueba usa identificadores, correos y almacenamiento temporales propios, por lo que también puede ejecutarse después de cargar la demostración. Sus datos se revierten y sus archivos se retiran. Evitar cambios manuales durante la ejecución, ya que compara los datos existentes antes y después.
