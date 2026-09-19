# Contrato de API — WMS Base v0.1

Base URL: `/api/v1`
Auth: **Laravel Sanctum**, token Bearer de vida larga (el colector no debe re-loguearse
en medio de un turno). El token se emite por *device*, no por sesión de navegador.

Todas las respuestas: `Content-Type: application/json`.
Errores: RFC-7807 simplificado.

```json
{ "error": { "code": "INSUFFICIENT_STOCK", "message": "Stock insuficiente en E1A-C01-N03",
             "details": { "available": 12.5, "requested": 30 } } }
```

Códigos de error del dominio (el colector los muestra en español y los distingue por `code`):
`UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `VALIDATION_FAILED`, `INSUFFICIENT_STOCK`,
`LOCATION_INACTIVE`, `LOCATION_NOT_IN_WAREHOUSE`, `ITEM_NOT_FOUND`, `BARCODE_UNKNOWN`,
`RECEIPT_CLOSED`, `MIXING_NOT_ALLOWED`, `DUPLICATE_CLIENT_UUID` (no es error: devuelve 200 con el movimiento original).

---

## 1. Autenticación

### `POST /auth/login`
```json
{ "username": "amoscoso", "password": "...", "device_serial": "17245522504321", "app_version": "0.1.0" }
```
→ `200`
```json
{
  "token": "12|abc...",
  "user": { "id": 1, "name": "Alan Moscoso", "username": "amoscoso" },
  "warehouses": [
    { "id": 1, "code": "BOLSAS", "name": "PT Bolsas", "branch": "Santa Cruz", "role": "SUPERVISOR" }
  ],
  "permissions": ["receipt.scan", "receipt.close", "move.relocate", "stock.view"],
  "server_time": "2026-09-19T15:04:05-04:00"
}
```

### `POST /auth/logout` → `204`
### `GET /auth/me` → mismo payload que login sin `token`.

---

## 2. Sincronización de maestros (pull)

El colector baja los maestros **una vez** y luego solo deltas. Es lo que permite
trabajar sin señal dentro de la nave.

### `GET /sync/catalog?warehouse_id=1&since=2026-09-19T12:00:00Z`
→ `200`
```json
{
  "server_time": "2026-09-19T15:04:05-04:00",
  "full": false,
  "items":     [ { "id":10,"sku":"5T2010201559","name":"Bolsa 28 X 50 CAFE GAVIOTA",
                   "base_uom":"BUL","decimals":0,"tracks_lot":true,"tracks_expiry":false,
                   "barcodes":[{"barcode":"5T2010201559","uom":"BUL","qty_per_scan":1,"type":"INTERNO"}] } ],
  "locations": [ { "id":55,"code":"E1A-C01-N03","barcode":"E1A-C01-N03","zone":"RACKS",
                   "zone_type":"ALMACENAJE","sort_seq":1030,"is_active":true,
                   "is_mixing_allowed":true } ],
  "uoms":      [ { "code":"BUL","name":"Bulto","decimals":0 } ],
  "reason_codes": [ { "id":3,"code":"MERMA","name":"Merma de producción","movement_type":"AJUSTE_NEG" } ],
  "deleted":   { "items": [], "locations": [88] }
}
```
`since` ausente ⇒ `full: true` (carga inicial). El colector guarda `server_time`
y lo manda como `since` la próxima vez.

---

## 3. Resolución de escaneo (el endpoint más usado del sistema)

### `POST /scan/resolve`
```json
{ "warehouse_id": 1, "code": "E1A-C01-N03", "context": "RECEPCION" }
```
→ `200`
```json
{
  "kind": "LOCATION",
  "location": { "id":55, "code":"E1A-C01-N03", "zone":"RACKS", "is_active":true,
                "occupancy": { "items":2, "total_qty":45 } }
}
```
`kind` ∈ `LOCATION` | `ITEM` | `LOT` | `GS1` | `UNKNOWN`.

Para `ITEM`:
```json
{ "kind":"ITEM",
  "item": { "id":10,"sku":"5T2010201559","name":"Bolsa 28 X 50 CAFE GAVIOTA","base_uom":"BUL" },
  "scanned": { "barcode":"5T2010201559","uom":"BUL","qty_per_scan":1 } }
```

Para `GS1` (código GS1-128 parseado en servidor **y** en cliente):
```json
{ "kind":"GS1",
  "parsed": { "01":"7501234567890", "10":"L2609A", "17":"2027-03-31", "37":"12" },
  "item": { ... }, "lot_code":"L2609A", "expires_at":"2027-03-31", "qty": 12 }
```

> El colector resuelve primero **en local** contra su caché de maestros
> (latencia cero, funciona sin red) y solo llama a este endpoint cuando el
> código no está en caché o necesita ocupación en vivo.

---

## 4. Consultas de stock

### `GET /stock/by-location/{locationId}`
→ lista de `{ item, lot, status, qty, qty_available, last_movement_at }`

### `GET /stock/by-item/{itemId}?warehouse_id=1`
→ lista de `{ location{code,zone,sort_seq}, lot{code,expires_at}, status, qty, qty_available }`
ordenada por **FEFO** (`expires_at` asc, luego `sort_seq`) para que el operador vea
primero de dónde debería sacar.

### `GET /stock/kardex?item_id=10&warehouse_id=1&from=&to=&page=`
→ paginado de `stock_movements` con saldo corrido.

---

## 5. Recepción (proceso end-to-end de v0.1)

### `GET /receipts?warehouse_id=1&status=ABIERTA,EN_PROCESO`
→ `[ { "id":7,"number":"Y8232752","type":"PRODUCCION","status":"ABIERTA",
       "expected_at":"2026-09-19","lines_total":6,"lines_done":2 } ]`

### `GET /receipts/{id}`
→ cabecera + `lines[]` con `{ id, line_no, item{...}, lot_code, qty_expected,
qty_received, uom, status, suggested_locations:[{location_id,code,reason:"UBICACION_FIJA|MISMO_ITEM|VACIA_CERCANA"}] }`

### `POST /receipts/{id}/scans`  ← **el posteo**
```json
{
  "client_uuid": "0f2c...-uuid-generado-en-el-colector",
  "receipt_line_id": 31,
  "item_id": 10,
  "lot_code": "L2609A",
  "location_id": 55,
  "qty": 5,
  "status": "BUENO",
  "scanned_barcode": "5T2010201559",
  "scanned_uom": "BUL",
  "occurred_at": "2026-09-19T15:03:58-04:00",
  "device_serial": "17245522504321"
}
```
→ `201`
```json
{ "movement": { "id": 9012, "uuid": "...", "type": "RECEPCION", "qty": 5 },
  "line": { "id":31, "qty_received": 25, "status": "PARCIAL" },
  "balance": { "location":"E1A-C01-N03", "qty": 25 } }
```

**Idempotencia**: si `client_uuid` ya existe → `200` con el mismo cuerpo del posteo
original, sin duplicar stock. Esto es lo que hace segura la cola offline y los
reintentos por señal intermitente.

### `POST /receipts/{id}/close` → valida permisos y líneas pendientes; `409` si hay líneas sin confirmar salvo `force:true` con motivo.

---

## 6. Movimientos genéricos

### `POST /movements` (reubicación y ajuste)
```json
{ "client_uuid":"...", "type":"REUBICACION", "warehouse_id":1, "item_id":10,
  "lot_code":"L2609A", "from_location_id":55, "to_location_id":61,
  "qty":5, "status":"BUENO", "occurred_at":"...", "device_serial":"..." }
```
`AJUSTE_NEG`/`AJUSTE_POS` exigen `reason_code_id`.

### `POST /sync/batch` — cola offline
```json
{ "device_serial":"...", "operations": [ { "endpoint":"/receipts/7/scans", "payload": { ... } }, ... ] }
```
→ `207 Multi-Status`
```json
{ "batch_id": 44,
  "results": [ { "client_uuid":"0f2c...", "status":201, "body":{...} },
               { "client_uuid":"1a9d...", "status":422,
                 "body":{"error":{"code":"INSUFFICIENT_STOCK", ...}} } ] }
```
Se procesan **en orden** y cada una en su propia transacción: un error no tumba el lote.
Las que fallan vuelven al colector marcadas para revisión manual — nunca se descartan en silencio.

---

## 7. Notas de implementación

- **Todo POST que mueva stock exige `client_uuid`.** Sin él, `422`.
- `occurred_at` viene del colector; el servidor guarda además `created_at`. La diferencia
  entre ambos es tu métrica de cuánto se trabajó sin señal.
- Rate limit: 300 req/min por device (un colector escaneando rápido hace ~30/min).
- Versionado: el prefijo `/v1` es obligatorio. Cuando cambie el contrato, `/v2`
  convive con `/v1` al menos un release, porque **no vas a poder actualizar los 20
  colectores el mismo día**.
- `GET /health` → `{ "status":"ok", "version":"0.1.0", "db":"ok" }` para el monitoreo.
