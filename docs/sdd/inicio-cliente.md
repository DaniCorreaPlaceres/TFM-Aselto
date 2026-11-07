# Inicio del cliente

## Objetivo

El cliente entra y ve su inicio: la marca, el menú y el resumen del periodo, aunque todavía no haya movimientos ni documentos.

## Fuera de alcance

Añadir un documento, calcular ingresos y gastos reales, la bandeja de la asesoría y la ayuda con mensajes.

## Criterios de aceptación

- Al entrar, el cliente llega a su inicio, con el logotipo oficial.
- El menú es Inicio, Ingresos y gastos, Documentos, Mi asesoría y Ayuda, en ese orden.
- El saludo usa su nombre. La única acción principal es «Añadir documento».
- Los importes del periodo salen a cero y no hay nada pendiente de revisión. Los gastos no van en rojo.
- No se dice que todo está al día.
- Si tiene asesoría, Mi asesoría muestra el nombre de la suya. Si no tiene, lo dice.
- Un admin o un gestor no ve este inicio.

## Comprobado

- `cliente@example.test` entra y llega a `/inicio`. El saludo es «Hola, Cliente de prueba». El logotipo oficial está en la cabecera.
- El menú abre Ingresos y gastos, Documentos, Mi asesoría y Ayuda. «Añadir documento» abre Documentos, que está vacío.
- Los importes salen a 0,00 € y el texto de los gastos usa el color del texto, no el rojo.
- Quien no tiene asesoría ve que puede usar Aselto por su cuenta. Mesa ve «Tu asesoría es Asesoría de prueba».
- El admin, en `/inicio`, recibe 403. Su acceso sigue en `/cuenta`.
- En ancho estrecho el menú queda abajo y no aparece un desplazamiento horizontal.
- PHPUnit: 34 pruebas. PHPStan, nivel 6, sin errores en este cambio.
