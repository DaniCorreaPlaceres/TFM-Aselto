# Tareas

Lista de lo que hay que hacer. El detalle de cada una se escribe en `docs/sdd/` antes de implementarla.

## Cimientos

1. Memoria de producto, stack, arquitectura y especificaciones. Hecha.
2. Ramas `main` y `develop`, con `main` protegida. Hecha.
3. Entorno local en Docker, comprobado. Hecha.
4. Estructura básica de Symfony. Hecha.

## Plataforma

5. Acceso y papeles: superadmin, admin, gestor y cliente. Hecha.
6. Home comercial pública en `/`. Hecha.
7. Alta de asesorías y de su equipo.
8. Alta de clientes, autónomo o sociedad.
9. Cuando un superadmin da de alta una asesoría, el alta se hace mediante un email que le llega y con un enlace tiene que crear la cuenta. El enlace va con token y tiene tiempo limitado. Hecha.
10. Cuando una asesoría da de alta a su equipo, email con enlace para registrarse. Token de tiempo limitado. Hecha.
11. Hay que añadir un sistema de recuperación de contraseña en el login. Hecha.
12. Inicio del cliente, con la marca y el menú. Hecha.
13. Añadir un documento, revisar los datos y confirmarlo.
14. Lectura del documento por IA, con revisión humana.
15. Bandeja de la asesoría: validar, rechazar o pedir otro documento.
16. Trimestre: cifras, cierre y consulta del histórico.
17. Mensajes y solicitudes entre cliente y asesoría.
18. Actualización en vivo del trimestre.
19. API `/api/v1` con los mismos casos de uso.
20. Datos de demostración de dos asesorías.
21. Entrega del máster: README, despliegue, usuarios de prueba, presentación y vídeo.
