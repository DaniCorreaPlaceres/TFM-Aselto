# Perfil fiscal

## Objetivo

Cada persona puede ver y corregir sus datos fiscales. El admin de una asesoría corrige los de sus clientes. El gestor los consulta y no los cambia.

## Fuera de alcance

El impuesto del documento, el IGIC, el IPSI, el recargo de equivalencia y la retención de IRPF.

## Criterios de aceptación

- El cliente abre «Datos fiscales» y guarda NIF, teléfono, dirección, código postal, municipio y provincia.
- El admin de la asesoría guarda los datos de su asesoría y los de un cliente de la suya.
- El gestor abre la ficha de un cliente de su asesoría y no puede guardarla.
- Un cliente de otra asesoría no se abre. Quien no tiene asesoría solo edita su ficha.
- Un NIF mal formado no se guarda. El mensaje es «El NIF no es válido.»

## Comprobado

- Con `cliente@example.test`, en `/perfil` se guardaron NIF `00000000T`, teléfono `600000001`, Calle del Perfil 2, `28001` y Madrid. Los campos se rellenaron al volver.
- Con `admin@example.test`, `/asesoria/datos` abre los datos de «Asesoría de prueba».
- PHPUnit: 66 pruebas. PHPStan nivel 6, sin errores. El gestor puede abrir la ficha y no puede guardarla. Una asesoría no abre la de otra.
