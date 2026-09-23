# Roadmap por versiones

La idea del módulo base es que cada versión sea **desplegable y usable**, no un
avance parcial hacia un gran lanzamiento. Cada una agrega un proceso completo.

---

## v0.1 — Módulo base ✅ (lo que está en este repositorio)

**Cimiento sobre el que se construye todo lo demás.**

- Topología: empresa → sucursal → almacén → zona → ubicación, con código de
  barras y secuencia de recorrido.
- Maestros: ítems con múltiples códigos de barras y factores por unidad, UOM con
  decimales, lotes con vencimiento.
- **Núcleo de stock**: saldo por (almacén, ubicación, ítem, lote, estado) y
  kardex inmutable. Todo movimiento pasa por un único servicio transaccional,
  con bloqueo por fila e idempotencia por `client_uuid`.
- Seguridad: usuarios, asignación por almacén, permisos por proceso.
- **Motor de escaneo**: DataWedge keystroke, parser GS1-128, cámara de respaldo,
  entrada manual, retroalimentación sonora y vibratoria.
- **Cola offline**: cada escaneo se encola y se envía solo; reintentar no
  duplica.
- Procesos operativos: **recepción de punta a punta**, reubicación, consulta de
  stock por ubicación y por ítem, kardex.
- Modo demostración autocontenido para probar sin servidor.

---

## v0.2 — Escritorio web + Salidas  *(en curso — código entregado, pendiente de verificación en instalación)*

Se adelantó a esta versión el **escritorio web completo** en Laravel 13 (Blade +
Alpine, sin build): tablero, ingresos, pedidos/despacho, stock e inventario,
mapa de almacén, maestros y el módulo de configuración (importador CSV,
diseñador de etiquetas QR, usuarios y roles, colectores, parámetros). Detalle
en [`09-escritorio-laravel.md`](09-escritorio-laravel.md). Con ello, parte de lo
previsto para v0.3 (conteos, ajustes con aprobación, IRA) ya tiene pantalla.

El proceso de salidas que hoy cubre SGLA y que WorkCorp no tiene completo.

- Pedidos de salida importados del ERP.
- **Picking dirigido**: ruta ordenada por `sort_seq`, asignación FEFO/PEPS,
  reserva de stock (`qty_allocated`) para que dos operadores no tomen el mismo
  pallet.
- Manejo de faltantes: corte de línea, sustitución de ubicación, "procesar sin
  stock" con motivo.
- **Filtro de pedidos anulados** — la funcionalidad que la propuesta de WorkCorp
  identifica como crítica: no permitir preparar una nota de venta anulada, y
  disparar la devolución automática de lo ya preparado.
- **Despacho**: consolidación en muelle, verificación contra pedido,
  transportista y vehículo, constancia de entrega.

## v0.3 — Inventario

- Conteo cíclico por clase ABC, con programación automática.
- Conteo por excepción (ubicación en cero, diferencia detectada en picking).
- Conteo ciego y doble conteo cuando hay diferencia.
- Ajustes con motivo obligatorio y flujo de aprobación por monto.
- Indicador **IRA por ubicación** en el tablero.

## v0.4 — Integración con WorkCorp / SIMEC

- API de entrada: maestros y documentos desde el ERP.
- API de salida: confirmaciones de recepción y despacho.
- **Job de conciliación diaria** con reporte de diferencias por ítem.
- Reintentos con backoff y bandeja de mensajes fallidos.

## v0.5 — Pallets (LPN / SSCC)

El cambio que más productividad agrega y por eso va después de tener la
operación estable.

Migración **aditiva**, sin romper nada:
```sql
ALTER TABLE stock_balances  ADD COLUMN lpn_id BIGINT NULL AFTER lot_id;
ALTER TABLE stock_movements ADD COLUMN lpn_id BIGINT NULL AFTER lot_id;
-- la clave única pasa a incluir lpn_id; las filas existentes quedan con NULL,
-- que significa "stock suelto, sin pallet" y sigue funcionando igual.
```
Más: tabla `lpns`, impresión de etiqueta SSCC, y movimiento de pallet completo
(un escaneo mueve todo su contenido).

## v0.6 — Productividad y tablero

- Métricas por operador y por turno: líneas/hora, exactitud, tiempo por proceso.
- Tablero web (no colector) para el jefe de almacén.
- Mapa de calor de ubicaciones: qué se mueve y qué lleva meses quieto.
- Reporte de cobertura WiFi real a partir de `occurred_at` vs. `created_at`.

## v0.7 — APK nativo

Cuando el cuello de botella sea el navegador y no el proceso.

- WebView que carga esta misma PWA + puente de DataWedge por Intent.
- Impresión Bluetooth directa (Link-OS SDK).
- Distribución por MDM con control de versión.
- El código de la PWA no cambia: el puente ya está previsto
  (`window.__wmsScan`).

---

## Cosas que deliberadamente NO están en el roadmap

Decir que no también es diseño:

- **Cross-docking, slotting automático, optimización por IA.** Requieren
  historia de datos que todavía no existe. Primero hay que generar la historia.
- **Voice picking, RFID, AGV.** Justificables recién con volúmenes muy
  superiores a los actuales.
- **Multi-empresa / multi-moneda.** El modelo lo permite, pero implementarlo
  antes de necesitarlo agrega complejidad a cada consulta.
- **App para clientes o proveedores.** Es otro producto, no una versión de este.

---

## Criterio para pasar de versión

Una versión se da por terminada cuando, en el almacén piloto:
1. El proceso nuevo se usa **sin volver al papel ni al sistema anterior**
   durante dos semanas seguidas.
2. La cola de sincronización termina vacía todos los días.
3. El reporte de conciliación con el ERP muestra diferencias explicables, no
   diferencias sorpresa.

Si alguno de los tres falla, la versión no está lista, por más funcionalidad que
tenga.
