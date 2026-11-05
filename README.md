# Aselto

Tus finanzas bajo control. Plataforma web para que una asesoría y sus clientes organicen ingresos, gastos y documentos.

La documentación está en [docs](docs/README.md): memoria, producto, diseño, marca y fichas de cada tarea. El trabajo se integra en `develop`. `main` está protegida.

## Entorno local

Hace falta Docker Desktop.

```bash
docker compose up --build
```

- Web: <http://localhost:8080>
- Comprobación: <http://localhost:8080/salud>
- MySQL en el puerto 3306, base `aselto`, usuario `aselto`, contraseña `aselto`
- Mercure en el puerto 9080

Credenciales solo de desarrollo. La aplicación de producto todavía no está construida: `/salud` confirma que PHP, Nginx y MySQL responden.
