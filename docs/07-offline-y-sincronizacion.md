# Trabajo sin red y sincronización

El requisito no negociable: **el operador nunca se queda esperando al servidor**.
Una nave con racks metálicos llenos tiene zonas muertas de WiFi, y un sistema
que se congela ahí se abandona en dos semanas.

---

## 1. Qué se resuelve local y qué necesita servidor

| Operación | Dónde ocurre | Por qué |
|---|---|---|
| Reconocer un código escaneado | **local** | latencia cero; es lo que hace que la app se sienta instantánea |
| Validar que la ubicación existe y es del almacén | **local** | está en la caché de maestros |
| Consultar el saldo de una ubicación | servidor (con caché) | el saldo cambia por acción de otros operadores |
| Registrar un movimiento | **cola local → servidor** | se acepta de inmediato, se envía cuando hay red |
| Cerrar una nota de ingreso | servidor | es una decisión que necesita el estado real |

La caché de maestros (ítems, códigos de barras, ubicaciones, unidades, motivos)
se descarga al seleccionar el almacén y vive en IndexedDB. Con ~40 ítems y ~300
ubicaciones ocupa unos pocos cientos de KB; con 10.000 ítems sigue siendo
manejable, y en ese punto se pasa a sincronización incremental por `since`
—que el contrato de API ya contempla.

---

## 2. La cola de salida (outbox)

Cada operación que mueve stock:

1. Recibe un **`client_uuid`** generado en el colector.
2. Se guarda en el store `outbox` de IndexedDB con estado `PENDIENTE`.
3. Se intenta enviar de inmediato.
   - Éxito → `ENVIADO`.
   - Error de red → vuelve a `PENDIENTE` y se reintenta solo.
   - Error de negocio (stock insuficiente, ubicación de otro almacén) → `ERROR`,
     **visible para el operador**, con botón de reintentar.

La distinción entre los dos tipos de error es deliberada: un problema de red no
es culpa del operador y no debe interrumpirlo; un problema de negocio sí necesita
que alguien decida.

```
escaneo → enqueue(client_uuid) → intento inmediato
                                   ├── 201/200 → ENVIADO
                                   ├── red caída → PENDIENTE (reintento automático)
                                   └── 422 dominio → ERROR (requiere decisión)
```

El reintento automático se dispara con el evento `online` del navegador y desde
la pantalla de sincronización. No hay backoff exponencial agresivo: el colector
está en manos de una persona que quiere terminar su turno.

---

## 3. Idempotencia: por qué reintentar es seguro

Es el mecanismo central y vale la pena entenderlo bien.

El `client_uuid` viaja con la operación y el servidor lo guarda en una columna
con **restricción UNIQUE** en `stock_movements`. Al recibir un posteo:

```
¿existe un movimiento con este client_uuid?
  sí  → devolver el resultado original, SIN tocar saldos   (200)
  no  → crear el movimiento y actualizar los saldos        (201)
```

Esto cubre el caso que arruina los WMS caseros: el colector envía, el servidor
procesa y guarda, la respuesta se pierde en el camino, el colector reintenta.
Sin idempotencia, ese pallet entra dos veces. Con ella, el segundo envío es un
no-op que devuelve lo mismo que el primero.

La misma lógica está implementada en el backend simulado del modo demo, para que
el comportamiento sea idéntico con y sin servidor.

---

## 4. Orden y conflictos

Las operaciones se envían **en orden de creación**, una por una, cada una en su
propia transacción. Un fallo en la número 3 no bloquea la 4: se marca y se sigue.

¿Puede haber conflicto real? Sí, y hay que aceptarlo: si el operador A saca de
una ubicación mientras el operador B —offline— también sacó de la misma, el
segundo posteo puede fallar por stock insuficiente. Ese es el comportamiento
correcto. La alternativa (aceptar el movimiento y dejar el saldo negativo) crea
una diferencia fantasma que aparece semanas después en un conteo.

La configuración `allows_negative_stock` por almacén existe para los casos donde
la operación prefiere continuidad sobre exactitud, pero el valor por defecto es
`false` y conviene dejarlo así.

`occurred_at` (hora del escaneo en el colector) se guarda además de `created_at`
(hora de llegada al servidor). La diferencia entre ambos es, de paso, la mejor
medición de la cobertura WiFi real de la nave.

---

## 5. Límites honestos de una PWA

Hay que conocerlos antes de prometer nada:

- **Cuota de almacenamiento**: el navegador puede desalojar IndexedDB si el
  dispositivo se queda sin espacio. Mitigación: pedir almacenamiento persistente
  (`navigator.storage.persist()`) y no acumular operaciones enviadas — la app
  purga las de más de 48 h.
- **El service worker no corre en segundo plano indefinidamente.** La cola se
  vacía cuando la app está abierta o al reabrirla, no con la pantalla apagada.
  `Background Sync` ayuda pero no está disponible en todos los WebView.
- **Sin app abierta no hay sincronización.** Si el operador cierra la app con la
  cola llena, los datos siguen ahí (persisten), pero no salen hasta que la
  vuelva a abrir. Por eso la cantidad pendiente está siempre visible en la
  cabecera, en el encabezado, y no escondida en un menú.
- **Borrar datos de navegación borra la cola.** En el colector hay que dejar
  documentado que no se limpian los datos de Chrome. Con la app en modo kiosco
  (Enterprise Home Screen) el riesgo desaparece.

Cuando cualquiera de estos límites moleste de verdad, es el momento de empaquetar
como APK (v0.7 del roadmap), no antes.

---

## 6. Prueba que conviene hacer antes de producción

En el almacén piloto, con un colector real:

1. Poner el equipo en modo avión.
2. Registrar 20 recepciones seguidas.
3. Verificar que la cabecera muestra 20 pendientes y que ninguna se perdió.
4. Cerrar la app por completo y volver a abrirla → las 20 siguen ahí.
5. Reactivar el WiFi → la cola se vacía sola.
6. Comprobar en el kardex que hay **20** movimientos, no 21 ni 19.

Si ese ciclo pasa, el sistema aguanta la nave. Si no, no hay funcionalidad que
lo compense.
