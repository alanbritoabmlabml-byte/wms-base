# Cómo se construye un WMS corporativo

Investigación de base para el proyecto. Contexto: empresa manufacturera con
**más de 4 almacenes en varias sucursales**, operación en piso con **colectores
Zebra**, y un ERP (WorkCorp) con el que el WMS tendrá que convivir.

---

## 1. Qué es —y qué no es— un WMS

Un ERP sabe **cuánto** hay de un ítem. Un WMS sabe **dónde**, **en qué estado**,
**desde cuándo** y **quién lo tocó por última vez**. Esa diferencia es toda la
razón de existir del proyecto.

La propuesta de integración que ya redactaste (módulo de almacén en WorkCorp)
identifica exactamente el síntoma clásico de tener las dos cosas separadas:
el stock del mismo ítem difiere entre sistemas, y el personal hace pasos dobles
para que cuadren. La causa no es que SGLA esté mal hecho: es que **hay dos
dueños de la verdad del inventario**. Un WMS bien construido asume que el dueño
de la ubicación y el movimiento físico es él, y que el ERP es el dueño del
documento comercial y del costo. Cada capa manda en lo suyo y hay un solo punto
de sincronización.

Tres cosas que un WMS debe hacer y que un módulo de inventario de ERP casi
nunca hace bien:

1. **Dirigir al operador.** No mostrarle una lista: decirle a dónde ir, qué
   tomar y confirmarlo con un escaneo. El sistema propone, el escaneo verifica.
2. **Registrar el movimiento, no el resultado.** Cada toque físico es un asiento
   inmutable. El saldo es una consecuencia, nunca un dato que se edita.
3. **Funcionar sin red.** La nave tiene zonas muertas de WiFi. Un WMS que se
   detiene cuando cae la señal se deja de usar en dos semanas.

---

## 2. Los procesos que hay que cubrir (y en qué orden)

### 2.1 Recepción
Entrada desde producción, compra, devolución o traspaso. En tu caso el grueso es
**producción propia** (lotes de extrusión), lo que simplifica mucho: no hay ASN
de proveedor que conciliar, pero sí hay que capturar **lote y turno** desde el
primer momento, porque es lo único que permite rastrear un reclamo de calidad
seis meses después.

Buenas prácticas:
- Recepción **ciega** cuando se audita al proveedor (el operador no ve la
  cantidad esperada, para que no la copie sin contar). Para producción propia no
  aplica: ahí la cantidad esperada ayuda.
- Diferencia entre **recibir** y **ubicar**. Recibir deja el pallet en la zona de
  recepción; ubicar lo lleva al rack. Separarlos permite descargar el camión
  rápido y acomodar después.
- La nota de ingreso no se cierra sola. Cerrarla es una decisión con permiso,
  y deja constancia de quién asumió las diferencias.

### 2.2 Almacenaje y putaway (ubicación)
El WMS sugiere dónde dejar el pallet. Reglas típicas, en orden de prioridad:
- **Ubicación fija** del ítem, si la tiene (repuestos, productos A).
- **Consolidación**: donde ya hay ese mismo ítem y lote, para no fragmentar.
- **Cercanía a picking** para clase A (rotación alta), fondo de nave para clase C.
- **Capacidad**: peso y volumen de la ubicación.
- **Compatibilidad**: no mezclar materia prima con producto terminado, ni
  químicos con alimentos.

El operador puede desviarse de la sugerencia, pero el sistema lo registra. Esa
diferencia entre lo sugerido y lo hecho es, con el tiempo, el mejor diagnóstico
del layout de tu nave.

### 2.3 Picking
Es donde se gana o se pierde la productividad. Estrategias, de menor a mayor
complejidad:

| Estrategia | Cómo funciona | Cuándo conviene |
|---|---|---|
| **Discreto** | un operador, un pedido, de principio a fin | pocos pedidos grandes; es lo que casi siempre hay al empezar |
| **Por lotes (batch)** | un operador recorre una vez y toma para N pedidos | muchos pedidos que comparten ítems |
| **Por zonas** | cada operador cubre una zona; el pedido se arma pasando de zona en zona | nave grande, pedidos multi-familia |
| **Por olas (wave)** | se liberan grupos de pedidos por horario de despacho o transportista | cuando el cuello de botella es el muelle, no el pasillo |

Empieza por **discreto con ruta ordenada** (`sort_seq` de la ubicación). Solo ese
cambio —que la lista venga en orden de recorrido en vez de en orden de captura—
suele dar la primera mejora grande, sin tocar nada más.

Reglas de asignación de stock:
- **PEPS/FIFO** por fecha de ingreso — el mínimo exigible.
- **FEFO** por fecha de vencimiento — obligatorio si hay caducidad (tus materias
  primas con vida útil, tintas, aditivos).
- **Reserva** (`qty_allocated`): al liberar un picking, el stock queda apartado.
  Sin esto, dos operadores se pelean por el mismo pallet.

### 2.4 Despacho
El eslabón que el ERP de la propuesta no cubre. Necesita: consolidación en
muelle, verificación contra el pedido, asociación con transportista y vehículo,
y constancia de entrega. Es también donde conviene imprimir la etiqueta
logística (ver `docs/03-codigos-de-barras.md`).

### 2.5 Inventario cíclico
La alternativa sana al inventario general anual que paraliza la planta.
Se cuenta un subconjunto cada día, sin parar la operación:
- Clase **A** (≈20 % de ítems, ≈80 % del valor): cada 1–3 meses.
- Clase **B**: cada 6 meses.
- Clase **C**: una vez al año.
- Además, conteos **por excepción**: ubicación que quedó en cero, diferencia
  detectada en picking, ítem con movimiento anómalo.

La métrica objetivo es **IRA (Inventory Record Accuracy)** medida por ubicación,
no por total. Un almacén puede tener el total exacto y estar completamente
desordenado: si el total cuadra pero la ubicación no, el picking igual falla.

### 2.6 Traspasos entre almacenes y sucursales
Con Santa Cruz y La Paz de por medio, un traspaso **no es un movimiento**: son
dos, con un **almacén de tránsito** en medio. Sale de origen (entra a tránsito),
y días después entra en destino (sale de tránsito). Si se modela como un solo
movimiento instantáneo, la mercadería "desaparece" mientras viaja y nadie sabe
quién responde por ella. Este es el error de diseño más caro de corregir después.

---

## 3. Arquitectura para múltiples almacenes y sucursales

### 3.1 Una instancia, muchos almacenes
Para 4–10 almacenes en un país, la respuesta es **una sola instancia
multi-almacén**, no una instalación por sitio. Razones:
- Los maestros (ítems, unidades, usuarios) se mantienen una vez.
- Los traspasos entre sucursales son transacciones internas, no integraciones.
- La visibilidad consolidada —"¿hay stock de esto en alguna sucursal?"— sale
  gratis; con instancias separadas cuesta un proyecto entero.

El precio es que **el almacén tiene que estar en todo**: en cada tabla de stock,
en cada consulta, en cada permiso. Si `warehouse_id` es opcional en alguna
consulta, tarde o temprano alguien ve o mueve stock de otra sucursal. En este
proyecto la regla es dura: no existe consulta de saldos sin almacén, y el
middleware valida contra `user_warehouse` en cada request.

### 3.2 Latencia y la nave de La Paz
Una instancia central implica que el colector de La Paz habla con un servidor
que puede estar en Santa Cruz. A 600 km y con enlaces bolivianos, eso se nota.
Tres mitigaciones, en orden de costo:
1. **Caché de maestros en el colector** (ya implementado): la resolución de un
   escaneo es local, 0 ms, y no depende del enlace.
2. **Cola de escritura asíncrona** (ya implementado): el operador no espera la
   confirmación del servidor para seguir escaneando.
3. **Réplica de lectura por sucursal** si el volumen lo justifica. No antes.

### 3.3 Gobierno de maestros
Con varias sucursales aparece el problema humano: dos personas crean el mismo
ítem con códigos distintos. Reglas que hay que fijar desde el día uno:
- Los ítems se crean **solo** en el ERP y bajan al WMS. El WMS nunca inventa un
  SKU.
- Las ubicaciones se crean **solo** en el WMS; el ERP no las conoce.
- Los usuarios y sus permisos por almacén se administran en un único lugar.

---

## 4. Convivencia con WorkCorp y SIMEC

Tu propuesta plantea absorber el WMS dentro del ERP. La alternativa —un WMS
propio que se integra— tiene una ventaja concreta para tu caso: **te deja
evolucionar la operación de almacén al ritmo del almacén**, sin depender del
calendario del proveedor del ERP ni de sus versiones. A cambio exige una
integración disciplinada. El patrón que funciona:

| Dato | Dueño | Dirección | Frecuencia |
|---|---|---|---|
| Ítems, unidades, clientes, proveedores | ERP | ERP → WMS | al cambiar (webhook) o cada hora |
| Documentos a atender (notas de ingreso, pedidos) | ERP | ERP → WMS | cada 1–5 min |
| Ubicación, lote, estado físico | **WMS** | — | no sale del WMS |
| Movimiento confirmado (recibido, despachado) | WMS | WMS → ERP | inmediato, con reintento |
| Costo, contabilidad, facturación | ERP | — | no entra al WMS |

Dos reglas que evitan el 90 % de los descuadres:
1. **Una sola dirección por dato.** Si un campo se puede editar en los dos
   sistemas, va a diferir. Sin excepciones.
2. **Idempotencia en ambos sentidos.** Cada mensaje lleva identificador único;
   reenviar no duplica. Es lo que permite reintentar sin miedo cuando el enlace
   se corta a medio envío.

Para la conciliación diaria: un job que compara `SUM(stock_balances)` por ítem
contra el stock del ERP y publica las diferencias. Que exista un reporte de
diferencias no es señal de que algo está mal; **no tenerlo** sí lo es.

---

## 5. Los colectores mandan en el diseño

Esto es lo que más se subestima. La aplicación no es "la web en chiquito".

**Lo que impone el dispositivo**
- Pantalla de 4"–6", usada con **guantes**, a veces a contraluz en el muelle.
  Objetivos táctiles de 48 px mínimo, contraste alto, tema oscuro disponible.
- El operador trabaja **mirando el pallet**, no la pantalla. La confirmación
  tiene que ser sonora y vibratoria, no solo visual.
- **Una mano ocupada.** Todo flujo debe completarse con el pulgar de una mano y
  el gatillo de la otra. Nada de arrastrar, hacer pinch o escribir textos largos.
- El gatillo físico es el botón principal de la aplicación. La pantalla es
  secundaria.

**Lo que impone la jornada**
- Turnos de 8–12 h con una sola batería. Evitar polling agresivo, animación
  continua y GPS.
- Caídas al piso de concreto: por eso son equipos industriales y no celulares.
- Rotación de personal alta: la app tiene que ser aprendible en 15 minutos.
  Si necesita capacitación formal, el diseño falló.

**Decisión de plataforma** (detalle completo en `docs/06-integracion-zebra.md`):
PWA sobre Chrome con DataWedge en modo *keystroke* es lo que elegimos para el
módulo base — se despliega actualizando una URL, se prueba hoy en cualquier
teléfono, y cubre bien recepción, reubicación y consultas. El APK nativo (o un
WebView envolviendo esta misma PWA) se vuelve necesario cuando haga falta
control fino del gatillo, impresión Bluetooth directa o trabajo prolongado sin
red. El camino está previsto y no obliga a reescribir.

---

## 6. Codificación y etiquetado

Resumen; el detalle está en `docs/03-codigos-de-barras.md`.

- **Ubicaciones**: código propio, Code 128, etiqueta grande y legible a 2–3 m
  para el montacarguista. Convención jerárquica (`E1A-C01-N03`) y **nunca**
  reutilizar un código de ubicación eliminada.
- **Ítems**: el SKU interno de 12 dígitos que ya usan funciona. Vale la pena
  añadir una etiqueta de "paquete" que valga N unidades: escanear una en vez de
  diez ahorra minutos por pallet.
- **Pallets**: cuando llegue el momento, **SSCC** (GS1, AI 00). Es lo que
  convierte "mover 40 bultos" en "mover un pallet": un escaneo en vez de
  cuarenta.
- **Lotes y vencimiento**: GS1-128 con AI 10 y 17 en la etiqueta de producción.
  El parser ya está implementado en la app.

---

## 7. Qué medir

Sin métricas, el WMS es un gasto; con ellas, es un argumento presupuestario.
Cinco bastan para empezar:

| Indicador | Cómo se calcula | Por qué importa |
|---|---|---|
| **IRA por ubicación** | ubicaciones correctas ÷ ubicaciones contadas | es *el* indicador de salud del almacén |
| **Exactitud de picking** | líneas correctas ÷ líneas preparadas | cada error es un reclamo de cliente |
| **Líneas por hora/operador** | líneas ÷ horas efectivas | mide el efecto de las rutas y del layout |
| **Tiempo de ciclo de recepción** | llegada del camión → stock disponible | el inventario que no está ubicado no existe |
| **% de operaciones en cola** | movimientos con `occurred_at` ≠ hora de servidor | mide la cobertura WiFi real, no la teórica |

El quinto es el que nadie mide y el que más discusiones evita: dice si el
problema es el sistema o la red.

---

## 8. Implantación: por qué fracasan estos proyectos

Casi nunca por la tecnología.

1. **Los datos maestros.** Arrancar con ubicaciones mal definidas o ítems
   duplicados garantiza el fracaso. Antes de la primera línea de código de
   producción: definir el layout, etiquetar físicamente, y hacer un inventario
   general de arranque. Es trabajo de piso, no de programación, y es el que más
   tiempo toma.
2. **El big bang.** Nunca arrancar los 5 almacenes a la vez. Un almacén piloto
   —idealmente el de bolsas en Santa Cruz, que es donde está el volumen y el
   dolor— durante 4–8 semanas, y recién después replicar.
3. **Ignorar al operador.** La persona que carga el montacargas sabe por qué la
   ubicación E3B nunca se usa. Si el sistema la sigue sugiriendo, dejarán de
   confiar en él y volverán al papel. Vale más una semana caminando la nave que
   un mes de reuniones.
4. **No dejar salida manual.** Siempre debe existir una forma —con permiso y
   registro— de corregir. Un sistema sin válvula de escape se rompe el primer
   día que la realidad no coincide con el modelo, y ese día llega.
5. **Convivencia sin fecha de corte.** Si el WMS y el sistema anterior conviven
   "por un tiempo", conviven para siempre y se descuadran. Fecha de apagado
   definida desde el inicio.

---

## Fuentes

- [Intent Output — Zebra TechDocs](https://techdocs.zebra.com/datawedge/latest/guide/output/intent/)
- [Tutorial: recibir datos escaneados — Zebra TechDocs](https://techdocs.zebra.com/datawedge/8-0/guide/samples/tutorial-receivescanneddata/)
- [Progressive Web Applications in the Enterprise — Zebra Developer Portal](https://developer.zebra.com/community/home/blog/2016/11/14/progressive-web-applications-in-the-enterprise)
- [Enterprise Browser — Zebra Developer Portal](https://developer.zebra.com/products/enterprise-mobile-computing/enterprise-browser)
- [GS1 Logistic Label Guideline](https://www.gs1.org/standards/gs1-logistic-label-guideline/current-standard)
- [Oracle WMS — License Plate Numbers (apéndice)](https://docs.oracle.com/en/cloud/saas/warehouse-management/20c/owmsu/appendix.html)
- [Multi-Warehouse WMS: Architecture Determines Scalability — Hardis](https://www.hardis-supplychain.com/en/blog/multi-warehouse-wms-architecture/)
- [Warehouse KPIs: 30+ Metrics That Drive Efficiency — Open Sky Group](https://openskygroup.com/warehouse-kpis/)
- [Best Practices for Cycle Counting — Midwest AWD](https://www.midwestawd.com/blog/best-practices-for-cycle-counting/)
- [openwms.org — WMS de código abierto (referencia de dominio)](https://github.com/openwms/org.openwms)
