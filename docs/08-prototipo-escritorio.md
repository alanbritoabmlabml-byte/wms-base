# Prototipo navegable v0.2 — escritorio + colector

**Archivo:** [`docs/prototipo/index.html`](prototipo/index.html) (un solo HTML, sin
dependencias de servidor; las fuentes vienen de Google Fonts y los generadores de
QR y Code 128 van embebidos).
**Publicado en GitHub Pages:** `https://<usuario>.github.io/wms-base/prototipo/`
(se despliega junto con la PWA al hacer push a `main`).

El prototipo existe para **validar diseño, navegación y nomenclatura** antes de
programar las vistas reales en Laravel. Los datos son de ejemplo, pero usan los
códigos reales del SGLA que reemplaza (SKU `5T2010101001…`, racks E1–E6, A2, AJ,
DEV, ING, formato de ubicación `E1-C01-N1`).

Entrar con cualquier usuario y contraseña; elegir almacén de trabajo.

---

## Decisiones de esta fase

- **Escritorio = web en el navegador** sobre el mismo backend Laravel de la PWA.
  Sin instalador; la misma URL sirve escritorio y colector según el ancho de
  pantalla (`≤ 820 px` → layout móvil con barra inferior).
- **Estilo corporativo Plásticos Carmen**: navy `#003080` como base, rojo Carmen
  `#E00010` solo como acento y estados, tema claro y oscuro. Tipografía Carlito
  (métrica Calibri, la del kit de marca) + JetBrains Mono para códigos.
- **Nombre de trabajo:** Carmen WMS.
- **Configuración centralizada**: importador de maestros, diseñador de etiquetas
  QR, usuarios y roles unificados (escritorio + colector), inventario de
  colectores, parámetros de negocio, respaldos.

---

## Mapa de pantallas

| Módulo | Qué hay | Equivalente en SGLA v2.3.2 |
|---|---|---|
| Inicio | IRA por ubicación, pedidos en proceso, ocupación, líneas/hora, flujo semanal, alertas, ocupación por rack, bajo mínimo, colectores | — |
| Ingresos | filtros por estado, detalle con avance de recepción por línea, nueva orden, habilitar / cerrar con diferencias | Gestión Ingresos |
| Pedidos y despacho | tablero Recibido → Preparación → Validado → Embalado → Despachado; lista; despachos; **liberar ola** (reserva FIFO/FEFO, ruta por `sort_seq`, reparto entre operadores); armar despacho con vehículo y chofer | Gestión Salida (picking, packing, despacho) |
| Stock e inventario | saldos por ubicación/lote/estado, kardex, **inventario cíclico** (plan A/B/C, conteos, diferencias con aprobación), ajustes con aprobación de encargado, rotación ABC con sugerencia de reubicación, máximos y mínimos | Gestión de Almacén |
| Mapa de almacén | topología sucursal → bodega → rack → ubicación, mapa de calor por rack, ficha de ubicación, bloqueo, nuevo rack/ubicación con generación de códigos | Almacenes |
| Productos · Clientes · Choferes y camiones | listas con búsqueda, fichas en panel lateral, altas | Productos, Clientes, Distribuidores + Flotas |
| Configuración → Importar datos | asistente en 5 pasos: tabla → archivo (CSV real, arrastrar y soltar) → mapeo de columnas con auto-emparejado → validación (obligatorios, duplicados, UM) → confirmación; historial; estado de sincronización con el ERP | Importar productos |
| Configuración → Etiquetas QR | 4 plantillas (ubicación, ítem/bulto, pallet SSCC, bulto de despacho); QR y Code 128 reales; contenido Texto / GS1 / JSON / URL; campos activables; 50×30 a 100×150 mm; impresoras Zebra | Impresión de etiquetas |
| Configuración → Usuarios y roles | escritorio, colector, matriz de permisos por rol y por almacén | Usuarios SGLA / móvil |
| Configuración → Colectores | equipos Zebra, batería, cola offline, perfil DataWedge, enrolamiento por QR, cobertura WiFi real | — |
| Configuración → Parámetros | reglas de stock, ubicación sugerida, códigos y formatos, integración ERP, respaldos, empresa | Gestión de Backup |

## Modo colector (misma app)

Botón del teléfono en la barra superior abre la vista de colector; en un
teléfono real se muestra directamente. Flujos: **Picking** (ubicación → ítem →
cantidad, con faltante y salto de línea), **Recepción**, **Ubicar** (sugerencia
con desviación registrada), **Reubicar** (origen → ítem → destino), **Conteo
ciego**, **Consulta**. Objetivos táctiles ≥ 56 px, beep + vibración, cola offline
visible. El escaneo se simula con botones o escribiendo el código y Enter.

---

## Cómo se relaciona con el código

| Prototipo | Implementación real |
|---|---|
| `VIEWS.*` (render por ruta con hash) | vistas Blade/Livewire o Inertia en `api/` |
| `STOCK`, `KARDEX`, `LOCATIONS`… (datos de ejemplo) | tablas `stock_balances`, `stock_movements`, `locations` (ver `02-modelo-de-datos.md`) |
| tokens CSS en `:root` | hoja de estilos compartida con la PWA |
| `simScan()` | motor de escaneo de la PWA (`pwa/`) |
| importador (`parseCSV`, `autoMap`, `validateRows`) | job de importación con Laravel Excel y cola |
| `qrPayload()` GS1 | parser GS1-128 ya existente en la PWA |

## Pendiente de validar

1. Nomenclatura a conservar del SGLA (Bodega/Almacén, Distribuidor/Chofer).
2. Si el importador acepta `.xlsx` en servidor o solo CSV.
3. Ajustes de diseño que surjan de recorrer el prototipo con los encargados de almacén.
