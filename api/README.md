# WMS Base v0.1 — API

Backend Laravel 12 del sistema de gestión de almacenes de **Plásticos Carmen S.R.L.**

Implementa exactamente el contrato de `docs/05-contrato-api.md` sobre el modelo de
datos de `docs/02-modelo-de-datos.md`. Es una API pura: no hay vistas, ni Blade,
ni Livewire. El cliente es el colector (PWA / Zebra TC57, MC3300).

---

## 1. Requisitos

| Componente | Versión mínima | Nota |
|---|---|---|
| PHP | 8.2 (probado en 8.4) | extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` o `intl` |
| Composer | 2.x | |
| MySQL / MariaDB | MySQL 8.0 / MariaDB 10.6 | `utf8mb4_unicode_ci`, InnoDB |
| SQLite | 3.31+ | **solo para correr los tests**; no hace falta en producción |

> Las migraciones usan una **columna generada** (`stock_balances.qty_available`).
> Por eso el mínimo de MySQL es 8.0 y el de SQLite 3.31.

---

## 2. Instalación paso a paso

```bash
cd api

# 1. Dependencias
composer install

# 2. Archivo de entorno
cp .env.example .env
php artisan key:generate

# 3. Crear la base de datos en MySQL
#    CREATE DATABASE wms_base CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. Editar .env con los datos reales de conexión (ver más abajo)

# 5. Migrar y sembrar
php artisan migrate --seed

# 6. Levantar
php artisan serve
# -> http://127.0.0.1:8000/api/v1/health
```

### `.env` mínimo

```dotenv
APP_NAME="WMS Base"
APP_ENV=local
APP_KEY=base64:...        # lo genera `php artisan key:generate`
APP_DEBUG=true
APP_TIMEZONE=America/La_Paz
APP_URL=http://localhost:8000

WMS_VERSION=0.1.0

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wms_base
DB_USERNAME=wms
DB_PASSWORD=secret
```

> El repo **no versiona `.env`** (solo `.env.example`). `vendor/`, `.env` y
> `storage/logs` están en `.gitignore`.

### Reconstruir todo desde cero

```bash
php artisan migrate:fresh --seed
```

---

## 3. Qué siembra el seeder

- **1 empresa** (`PC` — Plásticos Carmen S.R.L.) y **2 sucursales**: Santa Cruz (`SCZ`) y La Paz (`LPZ`).
- **5 almacenes**: `BOLSAS` (PT), `MP`, `REPUESTOS` en SCZ; `PT-LPZ`, `MP-LPZ` en LPZ.
- **~328 ubicaciones** con códigos `E1A-C01-N03` y `sort_seq` en serpentín, más zonas de
  recepción (`REC-01`), despacho (`DES-01`) y cuarentena (`CUA-01`).
- **5 unidades**: `KG` (3 decimales), `BUL`, `UND`, `ROLLO`, `MIL` (0 decimales).
- **44 ítems** reales: bolsas de polietileno con SKU de 12 caracteres
  (`5T2010201559` — *Bolsa 28 X 50 CAFE GAVIOTA*), materias primas
  (`POLIETILENO ALTA DENSIDAD`, `MASTERBATCH NEGRO`, `CARBONATO DE CALCIO`) y repuestos.
- **Lotes** con vencimiento en los ítems que lo llevan (para que FEFO tenga qué ordenar).
- **Saldos iniciales posteados a través de `StockLedger`** como `AJUSTE_POS` con motivo
  `INV_INI`. No hay un solo `INSERT` directo a `stock_balances`: el kardex cuadra desde
  el día cero y `rebuildBalance()` reproduce cada saldo.
- **3 notas de ingreso abiertas**, una con número **`Y8232752`**.

### Usuarios sembrados

| usuario | contraseña | PIN | rol | almacenes |
|---|---|---|---|---|
| `admin` | `wms1234` | `123456` | ADMIN | todos |
| `amoscoso` | `wms1234` | `123456` | SUPERVISOR | `BOLSAS`, `MP` |
| `operador1` | `wms1234` | `123456` | OPERADOR | `BOLSAS` |

---

## 4. Correr los tests

Los tests usan **SQLite en memoria** (está fijado en `phpunit.xml`), así que **no
necesitas MySQL levantado** para ejecutarlos:

```bash
php artisan test

# solo un archivo
php artisan test --filter=StockLedgerTest

# con PHPUnit directo
./vendor/bin/phpunit
```

Qué cubren:

| Test | Qué garantiza |
|---|---|
| `StockLedgerTest` | idempotencia por `client_uuid`, stock insuficiente con rollback, reubicación sin crear stock, `rebuildBalance` == proyección, motivo obligatorio en ajustes, ubicación ajena/inactiva, `is_mixing_allowed` |
| `ReceiptFlowTest` | recepción end-to-end: login → GET nota → POST scan → sube saldo y `qty_received`; reintento idempotente; cierre con permisos |
| `WarehouseAccessTest` | 403 `FORBIDDEN` a todo almacén no asignado en `user_warehouse` |
| `ApiEndpointsTest` | catálogo, `scan/resolve`, FEFO, kardex con saldo corrido, movimientos, `sync/batch` 207 |
| `Gs1ParserTest` | AI 00, 01, 10, 11, 17, 21, 30, 37, 310n, FNC1 y forma con paréntesis |
| `SeederTest` | el seeder corre completo y el kardex reproduce cada saldo sembrado |

---

## 5. Endpoints

Base URL: **`/api/v1`**. Autenticación: **Laravel Sanctum**, `Authorization: Bearer <token>`.
Los tokens no expiran (el colector no debe re-loguearse en medio de un turno) y se
emiten **por device**: al reloguear el mismo serial se revoca el token anterior.

| Método | Ruta | Auth | Qué hace |
|---|---|---|---|
| `GET` | `/health` | no | `{status, version, db}` para monitoreo |
| `POST` | `/auth/login` | no | token + almacenes + permisos + `server_time` |
| `POST` | `/auth/logout` | sí | revoca el token actual → `204` |
| `GET` | `/auth/me` | sí | mismo payload del login sin `token` |
| `GET` | `/sync/catalog?warehouse_id=&since=` | sí | maestros completos (`full:true`) o solo deltas |
| `POST` | `/scan/resolve` | sí | `kind` ∈ `LOCATION` \| `ITEM` \| `LOT` \| `GS1` \| `UNKNOWN` |
| `GET` | `/stock/by-location/{locationId}` | sí | qué hay en la ubicación |
| `GET` | `/stock/by-item/{itemId}?warehouse_id=` | sí | dónde está el ítem, **ordenado FEFO** |
| `GET` | `/stock/kardex?item_id=&warehouse_id=&from=&to=&page=` | sí | kardex paginado con saldo corrido |
| `GET` | `/receipts?warehouse_id=&status=` | sí | notas de ingreso con `lines_total` / `lines_done` |
| `GET` | `/receipts/{id}` | sí | cabecera + líneas + `suggested_locations` |
| `POST` | `/receipts/{id}/scans` | sí | **el posteo**: `201`, o `200` si el `client_uuid` ya existía |
| `POST` | `/receipts/{id}/close` | sí | `409` si hay líneas pendientes salvo `force:true` + `reason` |
| `POST` | `/movements` | sí | reubicación, putaway, picking, despacho, ajustes, cambio de estado |
| `POST` | `/sync/batch` | sí | cola offline → **`207 Multi-Status`** |

### Reglas transversales

- **Todo POST que mueva stock exige `client_uuid`.** Sin él, `422`.
- Reenviar el mismo `client_uuid` **no duplica stock**: devuelve `200` con el resultado original.
- Toda consulta de stock filtra por `warehouse_id`. Un usuario sin fila en `user_warehouse`
  recibe `403 FORBIDDEN`, tanto para leer como para escribir.
- Rate limit: **300 req/min por device** (`WMS_RATE_LIMIT`), y 20/min en el login.

### Formato de error

```json
{ "error": { "code": "INSUFFICIENT_STOCK",
             "message": "Stock insuficiente en E1A-C01-N03",
             "details": { "available": 12.5, "requested": 30 } } }
```

Códigos: `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `VALIDATION_FAILED`,
`INSUFFICIENT_STOCK`, `LOCATION_INACTIVE`, `LOCATION_NOT_IN_WAREHOUSE`,
`ITEM_NOT_FOUND`, `BARCODE_UNKNOWN`, `RECEIPT_CLOSED`, `MIXING_NOT_ALLOWED`.

---

## 6. Ejemplos `curl`

```bash
BASE=http://127.0.0.1:8000/api/v1
```

### Salud

```bash
curl -s $BASE/health
# {"status":"ok","version":"0.1.0","db":"ok"}
```

### Login

```bash
TOKEN=$(curl -s -X POST $BASE/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"amoscoso","password":"wms1234",
       "device_serial":"17245522504321","app_version":"0.1.0"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"];')

echo $TOKEN
```

### Catálogo inicial

```bash
curl -s "$BASE/sync/catalog?warehouse_id=1" -H "Authorization: Bearer $TOKEN"
```

Luego solo deltas, mandando el `server_time` que devolvió la llamada anterior:

```bash
curl -s "$BASE/sync/catalog?warehouse_id=1&since=2026-09-19T12:00:00Z" \
  -H "Authorization: Bearer $TOKEN"
```

### Resolver un escaneo

```bash
# Una ubicación
curl -s -X POST $BASE/scan/resolve -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"warehouse_id":1,"code":"E1A-C01-N03","context":"RECEPCION"}'

# Un ítem
curl -s -X POST $BASE/scan/resolve -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"warehouse_id":1,"code":"5T2010201559"}'

# Un GS1-128 en forma legible
curl -s -X POST $BASE/scan/resolve -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"warehouse_id":1,"code":"(01)07501234567890(17)270331(10)L2609A"}'
```

### Consultas de stock

```bash
curl -s "$BASE/stock/by-location/55" -H "Authorization: Bearer $TOKEN"

curl -s "$BASE/stock/by-item/1?warehouse_id=1" -H "Authorization: Bearer $TOKEN"

curl -s "$BASE/stock/kardex?item_id=1&warehouse_id=1&per_page=20" \
  -H "Authorization: Bearer $TOKEN"
```

### Recepción

```bash
# Notas abiertas
curl -s "$BASE/receipts?warehouse_id=1&status=ABIERTA,EN_PROCESO" \
  -H "Authorization: Bearer $TOKEN"

# Detalle con sugerencias de ubicación
curl -s "$BASE/receipts/1" -H "Authorization: Bearer $TOKEN"

# El posteo
curl -s -X POST $BASE/receipts/1/scans -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"client_uuid":"0f2c1c9e-2222-4222-8222-222222222222",
       "receipt_line_id":1,"item_id":1,"lot_code":"L2609A",
       "location_id":55,"qty":5,"status":"BUENO",
       "scanned_barcode":"5T2010201559","scanned_uom":"BUL",
       "occurred_at":"2026-09-19T15:03:58-04:00",
       "device_serial":"17245522504321"}'
# 201 la primera vez, 200 (mismo cuerpo) si se reintenta

# Cerrar
curl -s -X POST $BASE/receipts/1/close -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"force":true,"reason":"Produccion cerro el turno con faltante declarado"}'
```

### Movimientos

```bash
# Reubicación
curl -s -X POST $BASE/movements -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"client_uuid":"11111111-1111-4111-8111-111111111111",
       "type":"REUBICACION","warehouse_id":1,"item_id":1,"lot_code":"L2609A",
       "from_location_id":55,"to_location_id":61,"qty":5,"status":"BUENO"}'

# Ajuste negativo (exige reason_code_id)
curl -s -X POST $BASE/movements -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"client_uuid":"22222222-2222-4222-8222-222222222222",
       "type":"AJUSTE_NEG","warehouse_id":1,"item_id":1,"lot_code":"L2609A",
       "from_location_id":55,"qty":2,"reason_code_id":4}'
```

### Cola offline

```bash
curl -s -X POST $BASE/sync/batch -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"device_serial":"17245522504321","operations":[
        {"endpoint":"/receipts/1/scans","payload":{
          "client_uuid":"33333333-3333-4333-8333-333333333333",
          "receipt_line_id":1,"item_id":1,"location_id":55,"qty":10}},
        {"endpoint":"/movements","payload":{
          "client_uuid":"44444444-4444-4444-8444-444444444444",
          "type":"REUBICACION","warehouse_id":1,"item_id":1,
          "from_location_id":55,"to_location_id":61,"qty":3}}]}'
# 207 Multi-Status: cada operación trae su propio status y body
```

---

## 7. Arquitectura

```
app/
├── Data/MovementData.php          DTO inmutable de un movimiento
├── Services/
│   ├── StockLedger.php            ← EL corazón: único punto de escritura de stock
│   ├── ReceiptScanService.php     posteo de recepción (scan + kardex + línea)
│   ├── MovementService.php        movimientos genéricos
│   ├── ScanResolver.php           /scan/resolve
│   ├── LotResolver.php            lote '-' cuando el ítem no rastrea lotes
│   └── PutawaySuggester.php       ubicación fija > mismo ítem > vacía cercana
├── Support/Gs1Parser.php          GS1-128 (AI 00,01,10,11,17,21,30,37,310n + FNC1)
├── Exceptions/
│   ├── Domain/*.php               excepciones con el `code` del doc 05
│   └── ApiExceptionRenderer.php   traduce TODO al formato de error del contrato
└── Http/
    ├── Middleware/EnsureWarehouseAccess.php   403 a almacén no asignado
    ├── Requests/                  validación
    ├── Resources/                 forma del JSON
    └── Controllers/Api/V1/
```

### `StockLedger::post()` — la invariante

Dentro de **una** transacción:

1. Si ya existe un `stock_movements` con ese `client_uuid`, lo devuelve tal cual
   **sin tocar saldos** (idempotencia).
2. Garantiza la existencia de las filas de saldo (`insertOrIgnore`, que respeta el
   `UNIQUE` de 5 columnas) y las bloquea con `lockForUpdate()` **siempre en orden
   ascendente de `id`** — dos colectores que tocan las mismas dos ubicaciones en
   sentido inverso no se bloquean mutuamente.
3. Valida: la ubicación pertenece al almacén y está activa, hay stock suficiente en
   el origen si el almacén no permite negativo, el destino admite mezcla, y los
   ajustes traen motivo.
4. Inserta el asiento en `stock_movements` (append-only).
5. Resta en el saldo de origen y suma en el de destino.

`StockLedger::rebuildBalance()` recalcula un saldo sumando el kardex. Es la
herramienta de auditoría contra WorkCorp / SIMEC: si no coincide con
`stock_balances`, alguien escribió saldos por fuera del ledger.

---

## 8. Decisiones de implementación

- **Permisos en `config/wms.php`, no en tablas.** El doc 02 menciona
  `permissions` / `role_permissions`, pero en v0.1 la matriz es fija y no tiene
  pantalla de mantenimiento. El login ya devuelve la lista al colector, así que
  moverla a BD más adelante es aditivo.
- **`REASON_REQUIRED` se reporta como `VALIDATION_FAILED`.** El doc 05 no define un
  `code` propio para "ajuste sin motivo"; se usa el que el colector ya sabe mostrar,
  con `details.field = reason_code_id`.
- **`qty_available` es columna generada** (`qty - qty_allocated`), nunca se escribe.
- **Los tests corren sobre SQLite en memoria** (`phpunit.xml`) y `.env.example` apunta
  a MySQL: las migraciones son válidas en los dos motores.
