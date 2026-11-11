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
13. Añadir un documento, revisar los datos y confirmarlo. Hecha.
14. Lectura del documento por IA, con revisión humana. Hecha.
15. Alta de clientes y de asesorías con NIF o CIF, teléfono y dirección. Validar el formato del NIF, CIF o NIE. Hecha.
16. Perfil editable con los datos fiscales. Cada persona edita los suyos. El admin de la asesoría edita los de sus clientes. El gestor los ve y no los cambia. Hecha.
17. El territorio sale de la provincia. Península y Baleares usan IVA. Canarias usa IGIC: 0, 3, 7, 9,5, 15 y 20. Ceuta y Melilla usan IPSI con porcentaje manual. El documento usa el impuesto de ese territorio. Hecha.
18. Recargo de equivalencia opcional en los gastos con IVA, con cuota y total calculados. Hecha.
19. Retención de IRPF opcional en los ingresos de un autónomo, con porcentaje manual, cuota y total calculados. Hecha.
20. Bandeja de la asesoría: validar con correcciones, rechazar con motivo y reenvío o pedir justificante adicional, conservando historial y original. Hecha.
21. Trimestre: cifras, cierre, reapertura y consulta del histórico. Hecha.
22. Mensajes y solicitudes entre cliente y asesoría, con adjuntos y lectura individual. Hecha.
23. Actualización en vivo del trimestre. Hecha.
24. API `/api/v1` con los mismos casos de uso.
25. Datos de demostración de dos asesorías.
26. Maquetar toda la plataforma con Bootstrap 5, incluida la home comercial, manteniendo la marca y las reglas visuales.
27. Una sola zona para añadir el documento: foto, archivo o arrastrar y soltar.
28. Entrega del máster: README, despliegue, usuarios de prueba, presentación y vídeo.
