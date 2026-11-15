# Auditoría web/API · tarea 42

Fecha: **14 de noviembre de 2026**. Código analizado: `78c73e52fc5c54a5b44a603e6fb6ce8e3c11c016`, integrado en develop antes de crear `feature/owasp-security-audit`. Esta rama añade diagnóstico y herramientas locales; no corrige la aplicación.

## Resultado

**23 controles cumplidos, 12 incumplidos y 5 no comprobados**, de 40 controles propios; ninguno no aplicable. Cada uno pesa una unidad. El catálogo se fijó antes de puntuar en la [ficha SDD](../sdd/owasp-security-audit.md).

- Resultado conservador: **23/40 = 57,5 %**. Los no comprobados no suman como cumplidos.
- Cumplimiento observado: **23/35 = 65,7 %**, excluyendo únicamente los no comprobados.
- Cobertura de comprobación: **35/40 = 87,5 %**.

Es una medida de esta muestra, no una probabilidad de seguridad, certificación ASVS, evaluación completa de un nivel ASVS ni autorización para publicar en producción. Un control agregado falla aunque parte de sus medidas ya exista. La gravedad de un hallazgo no altera el peso numérico. Los 13 hallazgos no equivalen a 13 controles fallidos: F12 describe exposición local condicionada y CFG-05 productivo permanece no comprobado.

Abrir [report.html](report.html) directamente en el navegador: contiene la matriz con búsqueda y filtro, hallazgos desplegables, impactos, límites y propuestas. Funciona sin servidor ni conexiones externas. [audit.json](audit.json) es la fuente de datos completa, con criterio, estado, método, evidencia y referencia por control. El HTML se genera desde ese JSON.

## Qué se ha encontrado

Prioridad propuesta para la tarea 43, **todavía sin aplicar ni dar por acordadas las correcciones**:

1. **F01: revocación JWT tras reset de contraseña.** La prueba sintética conserva HTTP 200 con el token anterior, aunque la contraseña antigua ya falla. `User::withPassword()` conserva `authVersion`, mientras el validador JWT compara esa versión. El token puede seguir válido hasta el TTL configurado de una hora. Corregir e introducir regresión de revocación.
2. **F02: origen de enlaces de recuperación.** El servidor local acepta un Host alternativo y el generador produce un enlace a ese host. Se capturó el correo en memoria, sin enviarlo. El impacto requiere que el acceso público permita ese host y que la víctima siga el enlace; no se demuestra toma de cuenta. Fijar origen canónico y validar hosts, revisando también activación e invitaciones.
3. **F03: intentos sin limitación temporal.** No hay protección de frecuencia en login/recuperación en el código y configuración revisados. No se hizo fuerza bruta ni carga. Añadir límites por cuenta/IP y pruebas de ventana y recuperación.
4. **F04–F07 y F11:** contraseñas comunes, caducidad web, CSRF de salida, cabeceras y registro de seguridad. La salida sin CSRF tiene impacto acotado de cierre forzado, con mitigación SameSite; no acredita cambios fiscales. La longitud mínima actual de ocho caracteres no se presenta como infracción de ASVS 6.2.1: el criterio fallido es no bloquear contraseñas comunes (6.2.4).
5. **F08–F10, F12 y F13:** decidir defensa adicional de archivos, límites de recursos/IA, aislamiento de servicios locales y CI. Mantener las cuotas comerciales aprobadas. `main` **sí está protegida** mediante ruleset activo; la brecha es la ausencia de checks obligatorios y de workflows negativos web/API en CI. No se han cambiado reglas del remoto.

Cada hallazgo en JSON/HTML incluye evidencia, impacto condicionado, propuesta y prueba futura. No se ha enviado malware ni se afirma ejecución remota. Las descargas ya comprueban permisos y tienen cabeceras privadas; la falta de cabeceras globales no implica que todas las respuestas carezcan de protección.

En la repetición de JWT se observó también una ejecución con 401 después del reset; otras ejecuciones, incluidas las guardadas y la repetición consecutiva, devolvieron 200. No se atribuye causa a esa variación sin más evidencia. El hallazgo demuestra que la revocación **no está garantizada**, no que cualquier token vaya a ser aceptado siempre. El código conserva la versión de autenticación al cambiar contraseña; la futura prueba debe exigir 401 de forma estable.

## Evidencias de esta ejecución

- [runtime-probes.json](evidence/runtime-probes.json) y [repetición consecutiva](evidence/runtime-repeat.json): JWT antes/después del reset, contraseñas anterior/nueva, enlaces de uso único/caducidad/hash, contraseña común, host del correo capturado, salida sin CSRF, escape HTML, error con Kernel prod y conservación de identidades tras rollback.
- [http.json](evidence/http.json): códigos y cabeceras seleccionadas de localhost, Host alternativo y preflight de origen ajeno. No guarda cookies, tokens ni cuerpos privados.
- [configuration.json](evidence/configuration.json): configuración efectiva de sesión, reglas GitHub, superficie Docker y alcance de revisión de secretos. Es una síntesis de observaciones, no un volcado de secretos.
- [composer.json](evidence/composer.json): consulta de avisos de dependencias PHP fijadas, exit 0, sin avisos ni abandonos a fecha de auditoría. Composer mostró aviso de propiedad Git del montaje; terminó correctamente sin cambiar safe.directory ni dependencias.
- [workflows.json](evidence/workflows.json): PHPUnit **346 pruebas / 860 aserciones** y workflows de autenticación API **54**, documentos API **108**, comunicación API **79**, clientes independientes **55**, asesorías **70**, trimestres **43** y actualización real **38**, correctos. Los flujos retiran o revierten sus datos sintéticos y comprueban conservación de los existentes. No convertir estos totales en cobertura de todos los riesgos.

Los archivos son una instantánea del diagnóstico, no resultados que se actualicen automáticamente. No se publican hashes de contraseña, tokens, secretos, correos reales ni documentos. Las cuentas de las pruebas usan datos sintéticos `example.test`.

El HTML se ha generado y validado estructuralmente; queda pendiente su revisión visual. El navegador integrado bloquea el protocolo de archivos locales, por lo que no se presenta esa revisión como ejecutada.

## Reproducción local

Requisitos: Docker local ya instalado según [instalación](../local-installation.md), esquema migrado y fixtures de demostración compatibles con los workflows. Usar un entorno local desechable, sin actividad concurrente ni correo real; no ejecutar estas herramientas contra producción. El workflow trimestral espera perfiles existentes: no sustituirlos por datos reales. No ejecutar workflows con escrituras en paralelo.

Desde la raíz, PowerShell:

```powershell
docker compose exec -T --user www-data app php docs/security/audit-probes.php
docker compose exec -T --user www-data app php vendor/bin/phpunit
docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php
docker compose exec -T --user www-data app php tests/Integration/api-documents-workflow.php
docker compose exec -T --user www-data app php tests/Integration/api-communication-workflow.php
docker compose exec -T --user www-data app php tests/Integration/platform-independent-clients-workflow.php
docker compose exec -T --user www-data app php tests/Integration/platform-firms-workflow.php
docker compose exec -T --user www-data app php tests/Integration/quarter-workflow.php
docker compose exec -T --user www-data app php tests/Integration/live-quarter-workflow.php
docker compose exec -T --user www-data -e COMPOSER_CACHE_DIR=/tmp/aselto-security-audit-cache app composer audit --locked --format=json
node docs/security/render-report.mjs
```

`audit-probes.php` inicia una transacción, crea una identidad sintética y la revierte en finally. Usa claves JWT del entorno sin imprimirlas; captura un correo con un Mailer en memoria y utiliza dominio reservado, sin SMTP ni IA. Arranca también un Kernel prod/debug false para un 404 local; esto **no** prueba el despliegue de Hostinger. Salida JSON en consola; guardarla en `var/` para comparar con la instantánea, sin sobrescribir la línea base de forma inadvertida. Un hallazgo se cierra solo al repetir la prueba y registrar el nuevo resultado.

Para revisar HTTP sin imprimir cookies:

```powershell
$response = Invoke-WebRequest http://localhost:8080/ -SkipHttpErrorCheck
$response.StatusCode
foreach ($name in @('Content-Security-Policy','X-Frame-Options','X-Content-Type-Options','Referrer-Policy','Strict-Transport-Security')) {
    [pscustomobject]@{Name=$name; Value=($response.Headers[$name] -join ', ')}
}
(Invoke-WebRequest http://localhost:8080/recuperar -Headers @{Host='audit-invalid.example.test'} -SkipHttpErrorCheck).StatusCode
gh api repos/DaniCorreaPlaceres/TFM-Aselto/branches/main --jq '{branch: .name, protected: .protected}'
gh api repos/DaniCorreaPlaceres/TFM-Aselto/rules/branches/main --jq '[.[] | {type: .type}]'
```

Repetir la observación de cabeceras en `/entrar`, `/api/v1/me`, `/api/docs` y errores; comprobar tipos de descarga con los workflows. El endpoint clásico `/branches/main/protection` devuelve 404 porque se usan rulesets: no interpretarlo como rama desprotegida. La revisión de frecuencia, timeouts, SQL, escape, ficheros y logging se reproduce leyendo las rutas citadas en cada control y buscando mecanismos equivalentes antes de concluir ausencia. El generador verifica conteos, estados y enlaces internos entre controles/hallazgos; no ejecuta un nuevo escáner.

## Pendiente de comprobar

HTTPS/HSTS/cookies reales y servicios de Hostinger, avisos de todas las bibliotecas JS y su integridad, restauración de base y documentos, y política verificable de retención/minimización/proveedor. No se ha analizado todo el historial Git, probado penetración exhaustiva, medido canales laterales de tiempo ni realizado pruebas de carga, malware o ataques a terceros. Los puertos Docker en todas las interfaces no prueban accesibilidad desde Internet: faltan red/firewall. La privacidad sigue siendo borrador del TFM por decisión previa del usuario; esto no se presenta como dictamen legal.

## Referencias fijadas

- [ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0): referencias por capítulo; no equivalencia con un nivel completo. En autenticación/sesiones se consultaron también los requisitos 6.2.1, 6.2.4, 6.3.1, 7.1.1, 7.3.1, 7.3.2 y 7.4.
- [Top 10 web 2025](https://top10.owasp.org/2025/): categorías de riesgo, no lista exhaustiva de verificación.
- [API Top 10 2023](https://api-security.owasp.org/editions/2023/en/0x11-t10/): categorías API; los identificadores de audit.json pertenecen a esta edición.
- [Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html) y [Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html): recomendaciones para contraseñas y origen de enlaces. Documentos vivos consultados el 14/11/2026; las ediciones numeradas anteriores quedan fijadas.
