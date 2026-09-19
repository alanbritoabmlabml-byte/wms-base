# Modelo de datos — WMS Base v0.1

Identificación de stock elegida: **(almacén, ubicación, ítem, lote, estado)**.
No hay LPN/pallet en v0.1, pero **todo el diseño lo admite sin migración destructiva**:
cuando llegue, se agrega `lpn_id` nullable a `stock_balances` y `stock_movements`
y pasa a formar parte de la clave de saldo. Ver `docs/04-roadmap.md`.

Motor: MySQL 8 / MariaDB 10.6+, `utf8mb4_unicode_ci`, InnoDB.
Todas las tablas llevan `created_at`, `updated_at`. Los borrados son lógicos
(`deleted_at`) en maestros; **nunca** en movimientos.

---

## 1. Topología física

```
company (empresa)
└── branch (sucursal: Santa Cruz, La Paz…)
    └── warehouse (almacén: PT-BOLSAS, MP, REPUESTOS…)
        └── zone (zona: RACKS, PISO, CUARENTENA, PICKING)
            └── location (ubicación: E1A-C01-N03)
```

### `companies`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| code | varchar(20) UNIQUE | `PC` |
| name | varchar(120) | Plásticos Carmen S.R.L. |
| tax_id | varchar(30) null | NIT |

### `branches`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | |
| code | varchar(20) | `SCZ`, `LPZ` — UNIQUE(company_id, code) |
| name | varchar(120) | |
| timezone | varchar(60) | `America/La_Paz` |

### `warehouses`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| branch_id | FK branches | |
| code | varchar(20) | `BOLSAS` — UNIQUE(branch_id, code) |
| name | varchar(120) | |
| type | enum | `PT`,`MP`,`REPUESTOS`,`TRANSITO`,`GENERAL` |
| allows_negative_stock | bool | default false |
| requires_putaway | bool | recepción en 2 pasos |
| is_active | bool | |

> **Multi-almacén real**: un operador solo ve los almacenes que tiene asignados
> (`user_warehouse`), y **toda** consulta de stock filtra por `warehouse_id`.
> No existe consulta de saldos sin almacén.

### `zones`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| warehouse_id | FK | |
| code | varchar(20) | UNIQUE(warehouse_id, code) |
| name | varchar(120) | |
| type | enum | `ALMACENAJE`,`PICKING`,`RECEPCION`,`DESPACHO`,`CUARENTENA`,`DEVOLUCION` |
| picking_priority | smallint | orden de barrido para sugerencias |

### `locations`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| warehouse_id | FK | denormalizado a propósito (evita join en cada scan) |
| zone_id | FK zones | |
| code | varchar(30) | `E1A-C01-N03` — **UNIQUE(warehouse_id, code)** |
| barcode | varchar(64) | lo que se imprime en la etiqueta; default = code |
| aisle / rack / level / position | varchar(10) null | para ordenar rutas de picking |
| sort_seq | int | secuencia de recorrido (serpentín). Se calcula al sembrar. |
| max_weight_kg / max_volume_m3 | decimal null | validación de capacidad (v0.3) |
| is_mixing_allowed | bool | ¿admite más de un ítem? |
| is_active | bool | |

Índices: `(warehouse_id, barcode)`, `(zone_id, sort_seq)`.

**Convención de código sugerida** (la de SGLA es compatible):
`{ZONA}{PASILLO}-C{COLUMNA}-N{NIVEL}` → `E1A-C01-N03`.
Etiquetar con Code 128. Ver `docs/03-codigos-de-barras.md`.

---

## 2. Maestros de producto

### `uoms` (unidades de medida)
| campo | tipo | notas |
|---|---|---|
| code | varchar(10) UNIQUE | `KG`, `BUL`, `UND`, `ROLLO` |
| name | varchar(60) | |
| decimals | tinyint | 0 para BUL/UND, 3 para KG |

### `items`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| sku | varchar(40) UNIQUE | `5T2010201559` (código interno actual) |
| name | varchar(200) | `Bolsa 28 X 50 CAFE GAVIOTA` |
| base_uom_id | FK uoms | unidad en la que se guarda **siempre** el saldo |
| category | varchar(60) null | |
| tracks_lot | bool | si false, el lote es `'-'` |
| tracks_expiry | bool | habilita FEFO |
| shelf_life_days | int null | |
| min_stock / max_stock | decimal(18,4) null | por ítem; el de almacén va en `item_warehouse` |
| is_active | bool | |

### `item_barcodes`
Un ítem tiene **N** códigos (EAN del proveedor, código interno, código de bulto).
| campo | tipo | notas |
|---|---|---|
| item_id | FK | |
| barcode | varchar(64) UNIQUE | |
| uom_id | FK uoms | **qué unidad representa ese código** |
| qty_per_scan | decimal(18,4) | 1 scan de este código = N unidades base. Ej.: caja de 12 → 12 |
| type | enum | `INTERNO`,`EAN13`,`GS1_128`,`PROVEEDOR` |

> Esto es lo que permite que escanear la caja sume 12 y escanear la unidad sume 1,
> sin que el operador piense.

### `item_warehouse` (parámetros por almacén)
`item_id`, `warehouse_id`, `min_stock`, `max_stock`, `default_location_id` (ubicación fija sugerida), `abc_class` enum(A,B,C).

### `lots`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| item_id | FK | |
| code | varchar(40) | UNIQUE(item_id, code); `'-'` si el ítem no lleva lote |
| manufactured_at / expires_at | date null | |
| supplier_lot | varchar(40) null | |

---

## 3. Núcleo de stock — saldo + kardex

> **Regla de oro del sistema**: `stock_balances` es una *proyección*.
> La verdad es `stock_movements`. Cualquier saldo se puede reconstruir sumando
> el kardex. Esto es lo que te permite auditar diferencias contra WorkCorp/SIMEC.

### `stock_balances`
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| warehouse_id | FK | |
| location_id | FK | |
| item_id | FK | |
| lot_id | FK | |
| status | enum | `BUENO`,`OBSERVADO`,`CUARENTENA`,`DANADO` |
| qty | decimal(18,4) | saldo disponible físico |
| qty_allocated | decimal(18,4) | reservado por picking abierto |
| qty_available | generated | `qty - qty_allocated` (columna generada) |
| last_movement_at | datetime | |

**UNIQUE(warehouse_id, location_id, item_id, lot_id, status)** ← esta clave es el corazón.
Índices: `(warehouse_id, item_id)`, `(location_id)`.

Filas con `qty = 0 AND qty_allocated = 0` se purgan por job nocturno.

### `stock_movements` (kardex — append-only)
| campo | tipo | notas |
|---|---|---|
| id | bigint PK | |
| uuid | char(36) UNIQUE | |
| warehouse_id | FK | |
| type | enum | ver tabla abajo |
| item_id, lot_id | FK | |
| from_location_id | FK null | null = entra desde fuera del almacén |
| to_location_id | FK null | null = sale del almacén |
| from_status / to_status | enum | permite cambio de estado (BUENO→DANADO) |
| qty | decimal(18,4) | **siempre positivo**; el sentido lo da from/to |
| uom_id | FK | unidad base del ítem |
| qty_scanned / scanned_barcode / scanned_uom_id | | lo que el operador realmente escaneó (auditoría) |
| document_type / document_id / document_line_id | | `RECEPCION`,`PICKING`,`CONTEO`,`AJUSTE`,`TRASPASO` |
| reason_code_id | FK null | obligatorio en AJUSTE |
| user_id | FK | |
| device_id | varchar(64) null | serial del colector |
| client_uuid | char(36) UNIQUE | **clave de idempotencia** generada en el colector |
| occurred_at | datetime | hora real del escaneo (puede ser offline, anterior) |
| created_at | datetime | hora de llegada al servidor |
| posted_batch_id | FK null | lote de sincronización |

**Tipos de movimiento**
| type | from_location | to_location | uso |
|---|---|---|---|
| `RECEPCION` | null | ubicación | entrada de producción o compra |
| `PUTAWAY` | recepción | almacenaje | acomodo |
| `REUBICACION` | ubicación | ubicación | movimiento interno |
| `PICKING` | ubicación | staging/null | preparación de pedido |
| `DESPACHO` | staging | null | salida definitiva |
| `AJUSTE_POS` / `AJUSTE_NEG` | null/ubic | ubic/null | conteo o corrección, exige motivo |
| `TRASPASO_OUT` / `TRASPASO_IN` | | | entre almacenes/sucursales (par de asientos + tránsito) |
| `CAMBIO_ESTADO` | ubic | misma ubic | BUENO → DANADO |

### `reason_codes`
`code`, `name`, `movement_type`, `requires_approval`, `affects_cost`.

### Invariante transaccional
Todo posteo ocurre dentro de **una** transacción:

```sql
START TRANSACTION;
  SELECT ... FROM stock_balances
   WHERE warehouse_id=? AND location_id=? AND item_id=? AND lot_id=? AND status=?
   FOR UPDATE;                       -- bloqueo pesimista de la fila de saldo
  -- valida stock suficiente si es salida
  INSERT INTO stock_movements (...); -- el UNIQUE(client_uuid) mata el duplicado
  UPDATE stock_balances ... ;        -- origen y destino
COMMIT;
```

El bloqueo es **por fila de saldo**, no por tabla: dos operadores en ubicaciones
distintas no se estorban. Es el punto donde un WMS mal hecho se cae con 20 colectores.

---

## 4. Documentos operativos (v0.1: solo recepción)

### `receipts` (nota de ingreso)
`id`, `warehouse_id`, `number` (UNIQUE por almacén), `type` (`PRODUCCION`,`COMPRA`,`DEVOLUCION`,`TRASPASO`),
`status` (`ABIERTA`,`EN_PROCESO`,`CERRADA`,`ANULADA`), `external_ref` (doc de WorkCorp/SIMEC),
`supplier_name`, `expected_at`, `closed_at`, `closed_by`.

### `receipt_lines`
`receipt_id`, `line_no`, `item_id`, `lot_code`, `qty_expected`, `qty_received` (decimal),
`uom_id`, `status` (`PENDIENTE`,`PARCIAL`,`COMPLETA`,`EXCEDIDA`).

Cada registro del colector inserta en `receipt_line_scans`
(`receipt_line_id`, `location_id`, `qty`, `client_uuid`, `user_id`, `occurred_at`)
y **genera un `stock_movements` tipo `RECEPCION`**. Nunca se actualiza `qty_received` a mano:
es `SUM(receipt_line_scans.qty)`.

---

## 5. Seguridad y operación

### `users`
`name`, `username` (login corto para el colector, sin email), `password`, `pin` (6 dígitos para
re-autenticación rápida en piso), `is_active`, `last_login_at`.

### `user_warehouse`
`user_id`, `warehouse_id`, `role` (`OPERADOR`,`SUPERVISOR`,`ADMIN`).
**Un operador sin fila aquí no ve el almacén. Punto.**

### `permissions` / `role_permissions`
Granularidad por proceso: `receipt.scan`, `receipt.close`, `count.adjust`,
`move.relocate`, `stock.view_all_warehouses`.
El colector recibe la lista de permisos en el login y **oculta** los botones,
pero el servidor **revalida siempre**. La UI no es la seguridad.

### `devices`
`serial`, `model` (`TC57`, `MC3300`…), `warehouse_id`, `last_seen_at`, `app_version`.
Sirve para saber qué colector quedó desincronizado.

### `sync_batches`
`id`, `device_id`, `user_id`, `received_at`, `movements_count`, `status`, `error_payload`.
Trazabilidad de la cola offline.

### `audit_logs`
Cualquier cambio en maestros y toda anulación: `user_id`, `model`, `model_id`,
`action`, `before` (json), `after` (json), `ip`, `device_id`.

---

## 6. Diagrama de relaciones (resumen)

```
companies ─< branches ─< warehouses ─< zones ─< locations
                             │                     │
                             │                     ├──< stock_balances >── items ─< item_barcodes
                             │                     │           │              └─< item_warehouse
                             │                     └──< stock_movements >── lots
                             │                                 │
                             ├──< receipts ─< receipt_lines ─< receipt_line_scans
                             └──< user_warehouse >── users ─< devices ─< sync_batches
```
