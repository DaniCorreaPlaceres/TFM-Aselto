# Lista local y política de contraseñas

La selección de contraseña (activación, recuperación y aceptación de invitaciones) usa una política compartida de **15 a 128 puntos de código Unicode**. No exige mayúsculas, números ni símbolos, ni caducidad periódica. Las frases y gestores de contraseñas son compatibles; no se cambia ni recorta la credencial que se entrega al hasher. La comparación con la lista sí ignora mayúsculas y espacios exteriores. Un valor compuesto solo por espacios se rechaza.

La política se aplica a nuevas selecciones. No se ejecuta al autenticar una contraseña existente ni fuerza cambios en usuarios de demostración. No hay consultas a servicios externos de contraseñas filtradas: no se envían contraseñas, hashes ni prefijos. No se modifica el algoritmo de Symfony, que ya trata valores largos para evitar el truncamiento de bcrypt.

## Procedencia e integridad

Archivo de ejecución: `resources/security/common-passwords.txt`, fuera de public. Copia exacta de [SecLists, Passwords/Common-Credentials/10k-most-common.txt](https://github.com/danielmiessler/SecLists/blob/913b327317496d062bcc7cace524aaad8a693be2/Passwords/Common-Credentials/10k-most-common.txt).

- Commit fijado: `913b327317496d062bcc7cace524aaad8a693be2`.
- Importado: 15/11/2026.
- **10.001 entradas distintas**, pese al nombre «10k» del archivo.
- SHA-256 de los bytes importados: `68782d6a4a19a4768d5f15dd66bd534e7a33055cc755411e33f16d18c50fdcce`.
- Licencia MIT, copyright 2018 Daniel Miessler: [copia de la licencia](password-list-license.txt), obtenida del mismo commit.

`LocalCommonPasswords` añade nueve valores públicos y previsibles de longitud suficiente: secuencias numéricas/teclado, repeticiones de password/contraseña/Aselto y una frase de ejemplo ampliamente difundida. Están declarados en el adaptador y se revisan con el código. No son credenciales de usuarios. Las claves de consulta se pasan a minúsculas; esta transformación nunca se aplica al hash de una contraseña aceptada.

El adaptador comprueba el digest antes de cargar el conjunto y lo mantiene en memoria durante su vida útil. Si el archivo falta o está alterado, falla explícitamente; no se sustituye por una lista vacía. `.gitattributes` desactiva la conversión de saltos para este recurso, de modo que Windows/Linux conservan los mismos bytes. Las pruebas comprueban digest, cantidad y pertenencia de todas las entradas normalizadas, más los rechazos funcionales.

## Uso y límites

La longitud se cuenta en puntos de código, no bytes ni grafemas visuales. El formulario permite hasta 256 unidades UTF-16 para no impedir 128 puntos de código suplementarios; el servidor aplica el máximo real de 128. Los errores POST vuelven con 422 para que se pueda corregir el formulario; el password no se repuebla en el HTML. Rechazar un valor conserva el enlace, hash y versión de autenticación.

La lista es finita y está fijada a una versión; no acredita que cualquier contraseña aceptada tenga alta entropía ni que no aparezca en otras filtraciones. No implementa búsqueda completa de contraseñas filtradas, MFA ni un medidor de fortaleza. La [auditoría original](README.md) mantiene su puntuación histórica; el estado posterior se registra en [remediation.md](remediation.md).

Para actualizarla, elegir y revisar una versión concreta de una fuente con licencia compatible, sustituir el archivo conservando bytes, actualizar digest/cantidad/procedencia/licencia y ejecutar las pruebas de política, activación/recuperación e invitaciones. No descargar actualizaciones durante una petición de usuario ni publicar datos de contraseñas reales para comprobarlas.

Referencias de diseño: [Authentication Cheat Sheet de OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html) (15 caracteres sin MFA y soporte de frases largas) y [ASVS 5.0.0, autenticación](https://github.com/OWASP/ASVS/blob/v5.0.0/5.0/en/0x15-V6-Authentication.md). La política propia no equivale a certificar un nivel ASVS ni a verificar el universo de credenciales filtradas.
