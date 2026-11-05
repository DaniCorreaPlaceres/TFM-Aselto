# Aselto — Reglas visuales de diseño

Versión 0.1 · 18 de octubre de 2026

## Dirección aprobada

Nombre: **Aselto**. Eslogan: **Tus finanzas bajo control**. Dominio web: **aselto.com**. Estilo profesional y cercano, con mucho espacio, tipografía limpia y color usado con moderación. La aplicación debe resultar comprensible para autónomos con poca experiencia tecnológica, incluidas personas mayores. Web y app compartirán lenguaje, jerarquía y componentes, adaptando la disposición al dispositivo.

El primer boceto de inicio ha sido aceptado como base visual. Las medidas y colores siguientes son propuestas iniciales para implementar y verificar; una imagen no acredita accesibilidad.

## Claridad y jerarquía

- Una acción principal por pantalla o paso. Acciones secundarias con menor énfasis, pero legibles.
- Mostrar primero qué puede hacer la persona y qué requiere su atención.
- Preferir información esencial y espacio en blanco a paneles densos y gráficos decorativos.
- Agrupar campos relacionados con títulos claros. No depender solo de bordes o color para explicar relaciones.
- Mantener nombres y orden coherentes: «Inicio», «Ingresos y gastos», «Documentos», «Mi asesoría» y «Ayuda», según el espacio y los permisos disponibles.
- Evitar ocultar acciones frecuentes en menús. En móvil, adaptar la navegación sin reducir el tamaño de los controles.

## Paleta inicial

- Fondo general: `#F7F9FA`.
- Superficie: `#FFFFFF`.
- Texto principal: `#14243B`.
- Texto secundario: `#475569`.
- Acción principal: `#006B70`, con texto blanco.
- Selección suave: `#E3F1F1`, con texto oscuro.
- Borde funcional: `#64748B` cuando sea necesario para reconocer un control.
- Error: `#B42318`, acompañado de icono y explicación.
- Aviso: `#854D0E` sobre fondo claro, acompañado de icono y texto.
- Confirmación: `#166534`, acompañada de texto explícito.

Verificar cada combinación en sus estados reales. Los gastos habituales no se presentan como errores: evitar el rojo como su color predeterminado. Evitar degradados y efectos decorativos que resten claridad.

## Tipografía y espacio

- Usar una sans serif de alta legibilidad, con caracteres bien diferenciados y soporte de español y símbolo de euro. Fuente concreta pendiente de elección.
- Propuesta base: cuerpo de 18 px; texto auxiliar de 16 px; títulos de 24–32 px. En móvil, respetar la escala de texto del sistema.
- Interlineado aproximado de 1,5 para lectura. Evitar párrafos en mayúsculas y pesos demasiado finos.
- Escala de espaciado basada en 8 px, con ajustes de 4 px. Márgenes móviles iniciales de 16–24 px.
- No fijar alturas que recorten texto al ampliarlo. Las etiquetas pueden ocupar más de una línea.
- Importes con formato español, por ejemplo `1.250,00 €`, y cifras alineadas cuando se comparen.

## Controles e interacción

- Objetivo de área táctil: al menos 48 × 48 px lógicos en móvil, con separación suficiente. Evitar controles pequeños también en web.
- Botones con verbos claros: «Añadir documento», «Hacer una foto», «Subir archivo», «Guardar borrador» y «Confirmar registro».
- Iconos acompañados de etiquetas visibles en las acciones y la navegación principales.
- Etiquetas persistentes en formularios; los ejemplos dentro del campo no sustituyen a la etiqueta.
- Indicar campos obligatorios mediante texto y explicar formatos cuando sea necesario.
- Mostrar estados de carga, éxito, error y ausencia de datos con un siguiente paso comprensible.
- Conservar los datos introducidos ante errores. Permitir volver atrás sin pérdidas inesperadas.
- Evitar confirmaciones innecesarias; explicar las consecuencias cuando una acción sea irreversible.

## Captura y revisión documental

1. Elegir entre «Hacer una foto» y «Subir archivo», con una explicación breve.
2. Mostrar el progreso real del procesamiento. Si falla, ofrecer reintento y entrada manual según las funciones implementadas.
3. Presentar la vista del documento y los datos propuestos para revisión, juntos en web y mediante una disposición cómoda en móvil.
4. Explicar: «Hemos extraído estos datos. Revísalos antes de confirmar».
5. Identificar campos que requieren atención con texto e icono, sin depender del color ni inventar porcentajes de confianza.
6. Permitir corregir, guardar un borrador y confirmar de forma explícita.
7. Mostrar una confirmación inequívoca después de guardar, con acceso al documento.

La IA propone datos y nunca confirma registros automáticamente. No presentar el proceso como una garantía de deducibilidad o validez fiscal. No incluir emisión de facturas ni presentación de impuestos en los bocetos del MVP.

## Lenguaje

- Español sencillo, directo y respetuoso, sin infantilizar.
- Explicar los términos fiscales cuando sean necesarios; evitar jerga técnica interna.
- Distinguir «datos propuestos», «por revisar», «borrador» y «registro confirmado».
- No afirmar «Todo correcto» o «Todo al día» si hay tareas pendientes o el sistema no puede comprobarlo.
- Ayuda contextual cerca de la dificultad; no depender exclusivamente de un tutorial inicial.

## Objetivo de accesibilidad y comprobaciones

Objetivo de implementación: WCAG 2.2 nivel AA para web y criterios equivalentes aplicables a la app. Esta es una meta de diseño, no una declaración de conformidad.

- Medir contraste: al menos 4,5:1 en texto normal y 3:1 en texto grande; al menos 3:1 en información visual necesaria para reconocer controles y estados.
- Mantener foco visible, orden de teclado lógico y ausencia de trampas de foco. No ocultar el foco bajo elementos fijos.
- Proporcionar nombres accesibles, etiquetas asociadas, estructura semántica y anuncios de errores y cambios de estado.
- Comprobar ampliación de texto al 200 % y redistribución del contenido en web a 320 px CSS de ancho, sin pérdida de funciones ni desplazamiento horizontal innecesario.
- Respetar preferencias de movimiento reducido y no usar animaciones imprescindibles para comprender el contenido.
- Probar con teclado y lector de pantalla en web, y con TalkBack y tamaños de texto aumentados en Android.
- Probar los flujos con personas de distinta experiencia digital, incluidas personas mayores.

## Uso de los bocetos

Los bocetos son imágenes conceptuales con datos ficticios. No constituyen especificaciones exactas de píxeles ni evidencias de pruebas de accesibilidad. Ante diferencias entre un boceto y estas reglas, priorizar claridad, legibilidad y comportamiento accesible. Registrar nuevas decisiones en versiones sucesivas de este documento.
