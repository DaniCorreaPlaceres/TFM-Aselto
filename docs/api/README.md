# API v1

El primer bloque permite entrar y consultar la identidad actual. El contrato está en [OpenAPI](../../openapi/openapi.yaml). Documentos, perfiles fiscales, trimestres y mensajes se incorporan después; todavía no hay rutas API para ellos.

## Operaciones disponibles

- `POST /api/v1/auth/login`: cuerpo JSON con `email` y `password`. Devuelve `token`, `token_type` (`Bearer`) y `expires_in` (3600 segundos).
- `GET /api/v1/me`: exige `Authorization: Bearer <token>` y devuelve `id`, `email`, `role`, `firm_id` y `activated`.

Los papeles son `superadmin`, `firm_admin`, `advisor` y `client`. Una cuenta sin asesoría devuelve `firm_id: null`. No se devuelve la contraseña ni ningún dato fiscal.

La cookie de sesión web no sustituye al token. El token solo se admite en la cabecera Authorization, no en parámetros de URL ni cookies. En cada petición se recarga el usuario para comprobar su activación, papel y asesoría actuales. Un token no es un permiso permanente: los casos de uso de los siguientes bloques también comprobarán su asesoría y permisos.

La contraseña se utiliza exactamente como se recibe, incluidos los espacios. El correo usa la normalización del proveedor de usuarios existente. El cuerpo de acceso no puede superar 16 KiB y admite únicamente ambos campos de texto; la contraseña tiene un límite de 4096 bytes.

## Errores

Todos tienen la forma `{"error":{"code":"...","message":"..."}}`, con un mensaje en español. Acceso incorrecto, cuenta inexistente e inactiva reciben el mismo `401 invalid_credentials`.

En una operación protegida, `401 token_missing`, `token_invalid` o `token_expired` requiere volver a autenticarse o enviar el token correctamente. La respuesta incluye `WWW-Authenticate: Bearer`. Los errores de petición usan 400, tipo de contenido incorrecto 415, tamaño excesivo 413, ruta inexistente 404 y método incorrecto 405, con cabecera Allow. Los errores internos usan un 500 genérico sin trazas.

Todavía no hay renovación, revocación ni cierre de sesión JWT. La caducidad es de una hora; salir de la web no revoca el JWT. No se debe guardar un token en Git, incluirlo en una URL ni imprimirlo en un informe o registro.

## Preparación local

Las claves locales están en `config/jwt/` y Git las ignora. Para generarlas sin sobrescribir unas existentes:

```powershell
docker compose exec -T --user www-data app php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

La configuración usa `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY` y `JWT_PASSPHRASE`. No se copia la clave privada al repositorio ni se reemplazan claves existentes para probar un acceso.

## Prueba manual en PowerShell

Con el entorno Docker iniciado, el siguiente ejemplo pide las credenciales sin escribir la contraseña en el comando y muestra solo la identidad, sin imprimir el token:

```powershell
$apiCredentials = Get-Credential -Message 'Acceso de prueba de Aselto'
$apiBody = @{ email = $apiCredentials.UserName; password = $apiCredentials.GetNetworkCredential().Password } | ConvertTo-Json
$apiAccess = Invoke-RestMethod -Uri 'http://localhost:8080/api/v1/auth/login' -Method Post -ContentType 'application/json' -Body $apiBody
Invoke-RestMethod -Uri 'http://localhost:8080/api/v1/me' -Headers @{ Authorization = "Bearer $($apiAccess.token)" }
Remove-Variable apiBody, apiCredentials, apiAccess
```

La prueba de integración usa cuentas sintéticas dentro de una transacción y no imprime contraseñas ni tokens:

```powershell
docker compose exec -T --user www-data app php tests/Integration/api-authentication-workflow.php
```
