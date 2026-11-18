# Paginación y presupuestos operativos

Desde el contrato **0.6.0**, estos GET devuelven una página de hasta 100 elementos, por defecto 25:

- `/api/v1/documents`
- `/api/v1/advisory/documents`
- `/api/v1/conversations`
- `/api/v1/conversation-recipients`
- `/api/v1/conversation-recipients/{ownerId}/documents`

Parámetros: `start` (desplazamiento, 0–1.000.000), `length` (1–100) y `q` (texto literal, máximo 120 caracteres). Ejemplo: `/api/v1/documents?start=25&length=25&q=ejemplo`. No se admite `length=-1`. Los parámetros inválidos devuelven 400.

La colección conserva su nombre y sus elementos. Se añade `pagination`:

```json
{
  "documents": [],
  "pagination": {"offset": 25, "limit": 25, "total": 131, "filtered": 100}
}
```

`total` cuenta solo registros accesibles; `filtered` aplica además la búsqueda. Repetir con `start += length` hasta alcanzar filtered. Los permisos se comprueban de nuevo en cada petición. No hay una instantánea común entre distintas peticiones de paginación: altas/cambios concurrentes pueden desplazar resultados.

Se mantiene la comprobación de identidad, activación, rol y asesoría. Los listados de conversaciones no marcan lectura. El superadmin no obtiene acceso a documentos, trimestres ni conversaciones. Las búsquedas no interpretan expresiones regulares.

Los presupuestos se comparten entre web y API: lecturas 180/min por cuenta y 1.800 por asesoría; otras escrituras 60/min y 600; subidas y creación/respuesta de mensajes 10/min y 120. Una operación rechazada devuelve **429 resource_limit_exceeded**, `Retry-After` con segundos de espera y no-store; no consume cuota mensual ni guarda cambios. Las peticiones inválidas contabilizan si han alcanzado el controlador. Los límites de login/recuperación y su código `too_many_attempts` se mantienen separados.

Las exportaciones web CSV/XLSX usan todos los resultados filtrados y se generan por bloques; no se debe pedir una página ilimitada para exportar. [Funcionamiento, presupuestos y límites](../security/resource-limits.md). Las operaciones de trimestre conservan sus representaciones y cifras completas; esta paginación no recorta sus snapshots ni sus cálculos.
