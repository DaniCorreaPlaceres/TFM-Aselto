# Evidencias adicionales de Hostinger · tarea 45

## Objetivo

Completar comprobaciones pendientes de API y lectura de documentos en la recuperación privada de la primera instalación. Rama feature/hostinger-verification-evidence desde develop, tras integrar el PR 65.

## Cambios planteados

- Consultar por HTTP únicamente rutas públicas y API sin autenticar; registrar estado/cabeceras sin cookies, tokens ni contenido privado.
- Preparar un verificador CLI de documentos recuperados, fuera de public_html: destino de base explícito, raíz privada, transporte null, IA/alertas desactivadas, consultas de lectura y uso de los lectores de archivos de la aplicación. Mostrar solo contadores, nunca datos documentales, rutas almacenadas o credenciales.
- Probar el verificador localmente en un destino sintético y registrar resultados reales del hosting solo cuando el usuario los comparta.
- Recoger salidas fechadas de cron; si faltan, conservarlas pendientes. Actualizar el resumen para el TFM sin convertir confirmaciones generales en evidencias técnicas.

## Fuera de alcance

CI/CD, cambios en protecciones/DNS, migraciones, creación de cuentas, envío de correos, IA real y descarga de copia externa (aplazada por el usuario). No abrir públicamente la recuperación ni ejecutar fixtures en la base del hosting.

## Criterios de aceptación

- Las consultas públicas no modifican datos de negocio y registran respuestas esperadas/observadas con sus límites.
- El verificador rechaza bases equivocadas y configuración de entrega activa antes de consultas de datos; no modifica esquema ni registros.
- Las lecturas se realizan sobre la copia privada, sin exponer contenido ni secretos en salida.
- Se distinguen pruebas locales, salidas del servidor, confirmaciones del usuario y pendientes.

## Comprobado

- GET reales: salud 200; me, documentos, documentos de asesoría y conversaciones de API sin autenticar 401 con no-store/private. No se enviaron credenciales ni se modificaron datos de negocio.
- Helper CLI preparado, fuera del producto público. Prueba sintética reproducible: ocho comprobaciones correctas en una MariaDB 11.8.9 desechable, sin puertos públicos ni base local habitual; PHP 8.5.12 local. Incluye rechazo de base equivocada/configuración activa y archivo ausente. PHPStan sin errores y formato PHP 8.4 aplicado; no se envían correos ni se llama a IA.
- Archivo de entrega verify-restored-files.php: SHA-256 37aa6fcc39c852a8e039d8c6fde9d4d1190f14186c3032582e4b52fde4c69df9. Pendiente de subida y ejecución por el usuario en la recuperación del hosting; no se atribuyen los contadores sintéticos a datos reales.
- Guía hostinger-verification.md creada y enlazada. Pendientes lectura real, evidencias fechadas de ambos cron y controles de API autenticada. Copia externa continúa aplazada. La tarea 45 permanece abierta.
- Ejecución real del helper en Hostinger: hash coincide; informe UTC 2026-11-18T17:38:44+00:00, database_matches=true, read_only_transaction=true, originals=1, supporting_files=0, message_files=0, failed=0. Se acredita lectura del original referenciado por la base recuperada. Cero justificantes/adjuntos significa ausencia en esa copia, no prueba real de esos tipos; sus lecturas solo se han probado en el ensayo sintético. No se aportó código de salida, pero el informe acredita los contadores anteriores. Siguen pendientes salidas fechadas de cron y API autenticada.
- Evidencia posterior de cron: captura del panel muestra empty; el usuario confirma que corresponde a app:security alerts --send de Aselto, aunque el fondo de la captura muestra tareas de otro dominio y no permite atribuirla por sí sola. Para app:security retention comunica «Archivos retirados: 0» desde la salida solicitada en hPanel. Se registran ambas ejecuciones automáticas por confirmación del usuario, sin fecha/hora exacta ni código de salida aportados. No se acredita envío de resumen con alertas ni eliminación efectiva de registros antiguos (había cero).
- Prueba API autenticada guiada desde Swagger con cuenta de autónomo de ensayo: el usuario comunica 200 en POST /api/v1/auth/login y GET /api/v1/me tras autorizar con el token. No se recogen contraseña/token ni cuerpo de identidad. Se acredita por confirmación del usuario acceso e identidad autenticados; autorización documental propia/ajena todavía pendiente.
- Aclaración del ensayo documental: inicialmente se comunicaron tres 200, pero /me mostró firm_id no nulo; el token correspondía a una cuenta vinculada. Esos resultados no acreditan una fuga entre propietarios ni superan la prueba de rechazo. Tras cerrar la autorización y usar el autónomo independiente, el usuario confirma firm_id=null y 404 en GET /api/v1/documents/{id} y GET /api/v1/documents/{id}/file para el documento del otro cliente vinculado. Se acredita denegación documental ajena con ese actor por confirmación del usuario. El código del listado con el token independiente corregido y la lectura de un documento propio mediante API quedan sin confirmación específica.
- Contraste posterior con el mismo token independiente y un documento propio: el usuario confirma «200 ambos» para detalle y archivo. Se acredita lectura propia por confirmación funcional; no se interpreta esa respuesta como confirmación adicional explícita del código del listado.

## Balance de esta ronda

API anónima rechazada; login/identidad autenticados, lectura propia y rechazo de documento ajeno confirmados por el usuario. Un original recuperado leído sin fallos desde una transacción de solo lectura en Hostinger. Salidas de ambos cron confirmadas, sin fechas exactas capturadas. Preparación local del helper: ocho comprobaciones sintéticas y PHPStan correctos. No se han modificado reglas del producto ni ejecutado migraciones o envíos reales en esta rama.

La tarea 45 conserva límites declarados: copia externa aplazada; adjuntos/justificantes ausentes de la copia; fechas de cron, resumen con umbral superado, matriz API completa entre asesorías y capacidad efectiva no verificados aquí. No se da por cerrada toda la publicación ni se inicia CI/CD con este balance.
