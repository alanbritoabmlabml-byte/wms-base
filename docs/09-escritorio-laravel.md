# 09 · Carmen WMS en Laravel 13

> **v0.4 (23/09/2026) — sistema operativo completo.** Todos los botones del
> escritorio y del colector ejecutan la operación real y la guardan en la base:
>
> - **Frontend** (`public/carmen/`): `core.js` (datos, servidor, cola sin
>   conexión, modales, tablas paginadas, **autocompletado** `data-ac`, Excel,
>   impresión), `views-ops.js` (inicio, ingresos, pedidos, despachos),
>   `views-stock.js` (saldos, kardex, conteos, ajustes, ABC, mín/máx, mapa),
>   `views-master.js`, `views-config.js` (importar + plantillas, etiquetas,
>   usuarios, colectores, parámetros), `collector.js`, `boot.js`.
> - **Servidor** (`CarmenController`): `POST /carmen/api/movimientos` aplica
>   movimientos de stock en transacción (valida saldo, reserva/libera, kardex
>   inmutable), `PUT|DELETE /carmen/api/{DATASET}/{clave}` con permisos por rol
>   (matriz editable en Usuarios › Roles), `POST /carmen/api/lote/{DATASET}`,
>   `POST /carmen/api/usuarios` (crea cuentas con contraseña/PIN),
>   `POST /carmen/api/importar/{dataset}` (`App\Support\CarmenImport`, 12
>   plantillas), `POST /carmen/api/telemetria`, `GET /carmen/api/datos`.
> - **Multi-almacén real**: racks, documentos y kardex llevan `wh`
>   (migración `2026_09_24_000400_carmen_v2`).
> - **Colector**: ingreso con usuario + PIN (o escaneando la credencial QR),
>   campo de escaneo con autocompletado en cada pantalla (productos,
>   ubicaciones, pedidos, órdenes), GS1 (01)(10)(37), cola sin conexión que se
>   envía sola, enrolamiento por QR `/?colector=1&equipo=ID`.
> - Pruebas: `tests/Feature/CarmenOpsTest.php` + recorrido E2E con Playwright.

> **v0.3 (23/09/2026):** la aplicación principal (`/`) es ahora la interfaz
> exacta del artifact Carmen WMS —escritorio y modo colector— servida por
> Laravel: `CarmenController` entrega la página y un script de datos con las
> mismas constantes que usaba el artifact (`WAREHOUSES`, `PRODUCTS`, `STOCK`,
> `PEDIDOS`…), leídas de las tablas `cw_*`; `public/carmen/laravel.js` hace el
> login real y guarda en la BD los cambios de ingresos, pedidos e historial de
> importaciones. Las pantallas Blade descritas abajo quedaron en `/gestion`.


Cómo está construido el escritorio real (v0.2) que reemplaza al prototipo HTML
de `docs/prototipo/`, y qué conviene saber antes de tocarlo.

## Arquitectura

- **Una sola aplicación Laravel** (`api/`) sirve el escritorio (`routes/web.php`,
  sesión en base de datos) y la API del colector (`routes/api.php`, prefijo
  `/api/v1`, Sanctum). Comparten modelos, `StockLedger` y reglas.
- **Blade + Alpine.js 3** (`public/vendor/alpine.min.js`) sin Vite ni Node: se
  despliega copiando la carpeta y corriendo `composer install`. Los gráficos del
  tablero son SVG generados en PHP (`App\Support\Chart`).
- **Componentes anónimos** en `resources/views/components/`: `layouts.app`
  (shell con rail, topbar, selector de almacén, tema claro/oscuro), `card`,
  `kpi`, `page-head`, `pill`, `field`, `modal`, `empty`, `icon`.
- **Almacén de trabajo por sesión**: `ResolveWorkingWarehouse` resuelve el
  almacén (`session wms.warehouse_id`) entre los asignados al usuario y publica
  `$wh`, `$whList`, `$whRole` en todas las vistas. `EnsureWebRole`
  (`role:ADMIN,SUPERVISOR`) protege acciones sensibles; el módulo Configuración
  exige `role:ADMIN`.
- **Estilo**: `public/css/app.css` es el mismo del prototipo (variables
  `--navy #003080`, `--red #E00010`, modo oscuro por `data-theme`).

## Controladores (`App\Http\Controllers\Web`)

| Controlador | Pantallas | Notas |
|---|---|---|
| `AuthController` | login, logout, cambio de almacén | `username` + `is_active`; rechaza cuentas solo-colector |
| `DashboardController` | tablero, búsqueda global (`/`) | KPIs calculados sobre `stock_balances` y `stock_movements` |
| `ReceiptController` | ingresos | Cierre forzado con motivo → `AuditLog CIERRE_FORZADO` |
| `OutboundController` | kanban, pedidos, detalle, olas, despachos | Reserva con `qty_allocated` (FEFO/FIFO por parámetro); el stock se descuenta **una sola vez** al pasar a DESPACHADO vía `StockLedger` (tipo `DESPACHO`, `client_uuid` determinista por línea → idempotente) |
| `StockController` | saldos, kardex, ajustes, conteos, ABC, mín/máx | Ajustes con aprobación según rol/tolerancia; el cierre de conteo postea `AJUSTE_POS/NEG` con motivo `CONTEO_*` |
| `MapController` | mapa de racks | Alta de racks en serpentina; código según `codigos.formato_ubicacion` |
| `ItemController`, `CustomerController`, `TransportController` | maestros | Parámetros de ítem por almacén en `item_warehouse` |
| `ImportController` | importar datos | 4 pasos; archivos en `storage/app/private/imports`; lote en `import_batches` |
| `LabelTemplateController` | etiquetas QR | Plantillas en `label_templates`; muestras reales del almacén; impresión por lote abre una ventana lista para `Ctrl+P` |
| `UserController` | usuarios y roles | Contraseña/PIN se muestran **una sola vez**; un admin no puede quitarse su propio ADMIN |
| `DeviceController` | colectores | Telemetría la reporta la PWA en cada sync |
| `SettingController` | parámetros | Esquema en `SettingController::schema()`; valores en `settings` (global o por almacén) |

## Tablas nuevas (migración `2026_09_22_000200_create_desktop_tables`)

`customers`, `drivers`, `vehicles`, `settings`, `label_templates`,
`import_batches`, `stock_adjustments`, `cycle_counts`, `cycle_count_lines`,
`sales_orders`, `sales_order_lines`, `dispatches`, `dispatch_orders`, más
columnas en `items` (`factory_code`, `subcategory`, `weight_kg`, `price`),
`users` (`client_type`, `document_id`) y `devices` (`user_id`, `battery_pct`,
`pending_queue`, `os_version`, `is_active`).

## Importador CSV (`App\Services\Import`)

- `Datasets`: catálogo de tablas importables, campos, obligatorios y clave de
  upsert. Agregar un dataset = una entrada aquí + un `case` en `Importer::apply`.
- `CsvReader`: separador automático, BOM, Windows-1252, `autoMap()` por alias.
- `Importer::validate()` devuelve filas traducidas y errores por línea;
  `commit()` aplica en transacción con modos `UPSERT` / `INSERT` / `REPLACE`.

## Etiquetas

Formato del contenido del QR por plantilla: `PLAIN` (solo código, compatible con
SGLA), `GS1` (AI 01/10/17/37 o 00 para SSCC), `JSON` (`{t,id,lot,q,wh}`) o
`URL`. El dibujado es el mismo en la vista previa y en la hoja de impresión
(`wmsQrSvg`, `wmsBarcode` en `public/js/app.js`). Para GTIN/SSCC reales hay que
cargar el prefijo GS1 en Parámetros → Códigos.

## Pendiente / conocido

- No se ejecutó `composer install` ni `php artisan migrate` en el entorno donde
  se generó (sin acceso a Packagist): esperar ajustes menores en la primera
  instalación.
- Importación solo CSV (no `.xlsx`). Si se quiere Excel nativo, agregar
  `phpoffice/phpspreadsheet` y un lector alterno en `CsvReader`.
- La impresión va por el diálogo del navegador; la impresión directa ZPL a
  Zebra queda para v0.3 (`docs/06-integracion-zebra.md`).
- El colector (PWA) aún no consume `label_templates` ni `settings`; el contrato
  está en `docs/05-contrato-api.md` como extensión propuesta.
