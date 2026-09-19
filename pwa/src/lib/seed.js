/**
 * Datos de demostración.
 *
 * Reproducen la realidad de una planta de plásticos: dos sucursales, cinco
 * almacenes, racks de cuatro niveles, bolsas medidas en bultos y materia prima
 * en kilos. Los SKU siguen el formato interno de 12 dígitos que ya usan los
 * operadores, para que la demo se sienta como el sistema real y no como un
 * ejemplo de laboratorio.
 *
 * El mismo juego de datos está en el seeder de Laravel (api/database/seeders).
 */

export const COMPANY = { id: 1, code: 'PC', name: 'Plásticos Carmen S.R.L.' }

export const BRANCHES = [
  { id: 1, code: 'SCZ', name: 'Santa Cruz' },
  { id: 2, code: 'LPZ', name: 'La Paz' },
]

export const WAREHOUSES = [
  { id: 1, branch_id: 1, branch: 'Santa Cruz', code: 'BOLSAS', name: 'Producto terminado — Bolsas', type: 'PT' },
  { id: 2, branch_id: 1, branch: 'Santa Cruz', code: 'MP', name: 'Materia prima', type: 'MP' },
  { id: 3, branch_id: 1, branch: 'Santa Cruz', code: 'REPUESTOS', name: 'Repuestos y mantenimiento', type: 'REPUESTOS' },
  { id: 4, branch_id: 2, branch: 'La Paz', code: 'PT-LPZ', name: 'Producto terminado La Paz', type: 'PT' },
  { id: 5, branch_id: 2, branch: 'La Paz', code: 'MP-LPZ', name: 'Materia prima La Paz', type: 'MP' },
]

export const UOMS = [
  { code: 'BUL', name: 'Bulto', decimals: 0 },
  { code: 'KG', name: 'Kilogramo', decimals: 3 },
  { code: 'UND', name: 'Unidad', decimals: 0 },
  { code: 'ROLLO', name: 'Rollo', decimals: 0 },
  { code: 'MIL', name: 'Millar', decimals: 0 },
]

export const REASON_CODES = [
  { id: 1, code: 'MERMA', name: 'Merma de producción', movement_type: 'AJUSTE_NEG' },
  { id: 2, code: 'ROTURA', name: 'Rotura / daño en manipuleo', movement_type: 'AJUSTE_NEG' },
  { id: 3, code: 'CONTEO-', name: 'Diferencia de conteo (faltante)', movement_type: 'AJUSTE_NEG' },
  { id: 4, code: 'CONTEO+', name: 'Diferencia de conteo (sobrante)', movement_type: 'AJUSTE_POS' },
  { id: 5, code: 'REPROC', name: 'Devolución a reproceso', movement_type: 'AJUSTE_NEG' },
]

export const USERS = [
  {
    username: 'amoscoso',
    password: 'wms1234',
    name: 'Alan Moscoso',
    role: 'SUPERVISOR',
    warehouse_ids: [1, 2, 3],
    permissions: ['receipt.scan', 'receipt.close', 'move.relocate', 'stock.view', 'adjust.post'],
  },
  {
    username: 'operador1',
    password: 'wms1234',
    name: 'Juan Pérez',
    role: 'OPERADOR',
    warehouse_ids: [1],
    permissions: ['receipt.scan', 'move.relocate', 'stock.view'],
  },
  {
    username: 'admin',
    password: 'wms1234',
    name: 'Administrador',
    role: 'ADMIN',
    warehouse_ids: [1, 2, 3, 4, 5],
    permissions: ['receipt.scan', 'receipt.close', 'move.relocate', 'stock.view', 'adjust.post', 'stock.view_all_warehouses'],
  },
]

const BAGS = [
  ['5T2010201559', 'Bolsa 28 X 50 CAFE GAVIOTA'],
  ['5T2010201551', 'Bolsa 24 X 42 RAYADA PC'],
  ['5T2010101110', 'Bolsa 8 X 12 COMUN COLORES'],
  ['5T2010101086', 'Bolsa 65 X 80 CHINITA NEGRA'],
  ['5T2010201242', 'Bolsa 35 X 65 N/MOTITA NUEVA'],
  ['5T2010201133', 'Bolsa 17 X 34 BLANCA ECO P-90'],
  ['5T2010101102', 'Bolsa 10 X 15 ROLLO PHARMANDINA'],
  ['5T2010101059', 'Bolsa 30 X 40 ROLLO TRAVERSO'],
  ['5T2010101039', 'Bolsa 30 X 40 ROLLO TRAVERSO REF'],
  ['5T2010201211', 'Bolsa 35 X 65 NC/D MOTA'],
  ['5T2010201598', 'Bolsa 34 X 62 ECOLOGICA COLORES'],
  ['5T2010201477', 'Bolsa 20 X 30 CRISTAL ALTA'],
  ['5T2010201488', 'Bolsa 40 X 60 NEGRA BASURA'],
  ['5T2010201502', 'Bolsa 50 X 70 NEGRA BASURA'],
  ['5T2010201515', 'Bolsa 60 X 90 NEGRA INDUSTRIAL'],
  ['5T2010301220', 'Bolsa 25 X 35 CAMISETA BLANCA'],
  ['5T2010301231', 'Bolsa 30 X 40 CAMISETA BLANCA'],
  ['5T2010301245', 'Bolsa 35 X 45 CAMISETA RAYADA'],
  ['5T2010401010', 'Manga 40 CM POLIETILENO NATURAL'],
  ['5T2010401022', 'Manga 60 CM POLIETILENO NATURAL'],
  ['5T2030401120', 'Film Stretch 50 CM 23 MIC'],
  ['5T2030401131', 'Film Stretch 50 CM 17 MIC'],
]

const PLATES = [
  ['5T203040001', 'PLATILLOS 15 P-10'],
  ['5T203040002', 'PLATILLOS 18 P-10'],
  ['5T203040003', 'VASOS 8 OZ P-50'],
]

const RAW = [
  ['5M1010100010', 'POLIETILENO ALTA DENSIDAD HDPE'],
  ['5M1010100021', 'POLIETILENO BAJA DENSIDAD LDPE'],
  ['5M1010100033', 'POLIPROPILENO HOMOPOLIMERO'],
  ['5M1020200011', 'MASTERBATCH NEGRO 4000'],
  ['5M1020200024', 'MASTERBATCH BLANCO TiO2'],
  ['5M1020200037', 'MASTERBATCH AZUL ULTRAMAR'],
  ['5M1030300015', 'CARBONATO DE CALCIO 80%'],
  ['5M1030300028', 'ADITIVO DESLIZANTE'],
  ['5M1040400012', 'TINTA FLEXOGRAFICA NEGRA'],
  ['5M1040400025', 'SOLVENTE ACETATO DE ETILO'],
]

const SPARES = [
  ['5R1050500011', 'RESISTENCIA CERAMICA 220V 800W'],
  ['5R1050500024', 'RODAMIENTO 6204 2RS'],
  ['5R1050500037', 'CORREA DENTADA HTD 8M-1200'],
  ['5R1050500040', 'CUCHILLA SELLADORA 60 CM'],
]

/** Construye el catálogo de ítems con sus códigos de barras. */
export function buildItems() {
  const items = []
  const barcodes = []
  let id = 1

  const push = (sku, name, uom, opts = {}) => {
    const item = {
      id: id++,
      sku,
      name,
      base_uom: uom,
      decimals: UOMS.find((u) => u.code === uom)?.decimals ?? 0,
      tracks_lot: opts.lot ?? true,
      tracks_expiry: opts.expiry ?? false,
      category: opts.category || 'GENERAL',
      warehouse_ids: opts.warehouses || [1],
    }
    items.push(item)
    barcodes.push({ barcode: sku, item_id: item.id, uom, qty_per_scan: 1, type: 'INTERNO' })
    // Muchos bultos traen además una etiqueta de "paquete" que vale N unidades:
    // escanearla suma N de una vez. Es el detalle que ahorra minutos por pallet.
    if (opts.pack) {
      barcodes.push({
        barcode: `P${sku}`,
        item_id: item.id,
        uom,
        qty_per_scan: opts.pack,
        type: 'INTERNO',
      })
    }
    return item
  }

  BAGS.forEach(([sku, name], i) => push(sku, name, 'BUL', { category: 'BOLSAS', pack: i % 3 === 0 ? 10 : null, warehouses: [1, 4] }))
  PLATES.forEach(([sku, name]) => push(sku, name, 'BUL', { category: 'DESCARTABLES', warehouses: [1] }))
  RAW.forEach(([sku, name]) => push(sku, name, 'KG', { category: 'MATERIA PRIMA', expiry: true, warehouses: [2, 5] }))
  SPARES.forEach(([sku, name]) => push(sku, name, 'UND', { category: 'REPUESTOS', lot: false, warehouses: [3] }))

  return { items, barcodes }
}

/**
 * Ubicaciones. Convención `{ZONA}-C{COL}-N{NIVEL}`, con `sort_seq` en
 * serpentín para que la ruta de picking no obligue a cruzar el pasillo.
 */
export function buildLocations() {
  const locations = []
  let id = 1

  const rackZones = [
    { wh: 1, zone: 'RACKS', type: 'ALMACENAJE', prefixes: ['E1A', 'E1B', 'E2A', 'E2B', 'E3A'], cols: 12, levels: 4 },
    { wh: 1, zone: 'PICKING', type: 'PICKING', prefixes: ['E6B'], cols: 20, levels: 1 },
    { wh: 2, zone: 'MP-RACKS', type: 'ALMACENAJE', prefixes: ['M1A', 'M1B'], cols: 10, levels: 3 },
    { wh: 3, zone: 'REP', type: 'ALMACENAJE', prefixes: ['R1A'], cols: 8, levels: 4 },
    { wh: 4, zone: 'RACKS-LP', type: 'ALMACENAJE', prefixes: ['L1A', 'L1B'], cols: 10, levels: 3 },
    { wh: 5, zone: 'MP-LP', type: 'ALMACENAJE', prefixes: ['LM1'], cols: 8, levels: 3 },
  ]

  for (const z of rackZones) {
    z.prefixes.forEach((prefix, pi) => {
      for (let c = 1; c <= z.cols; c++) {
        // serpentín: los pasillos pares se recorren en sentido inverso
        const colOrder = pi % 2 === 0 ? c : z.cols - c + 1
        for (let n = 1; n <= z.levels; n++) {
          const code = `${prefix}-C${String(colOrder).padStart(2, '0')}-N${String(n).padStart(2, '0')}`
          locations.push({
            id: id++,
            warehouse_id: z.wh,
            zone: z.zone,
            zone_type: z.type,
            code,
            barcode: code,
            aisle: prefix,
            rack: String(colOrder),
            level: String(n),
            sort_seq: pi * 10000 + c * 100 + n,
            is_mixing_allowed: z.type !== 'PICKING',
            is_active: true,
          })
        }
      }
    })
  }

  // Zonas operativas de cada almacén (piso, no rack)
  for (const wh of WAREHOUSES) {
    for (const [code, zone, type] of [
      ['RECEP-01', 'RECEPCION', 'RECEPCION'],
      ['RECEP-02', 'RECEPCION', 'RECEPCION'],
      ['DESP-01', 'DESPACHO', 'DESPACHO'],
      ['CUAR-01', 'CUARENTENA', 'CUARENTENA'],
    ]) {
      locations.push({
        id: id++,
        warehouse_id: wh.id,
        zone,
        zone_type: type,
        code: `${code}`,
        barcode: `${code}`,
        aisle: zone,
        rack: null,
        level: null,
        sort_seq: 90000 + id,
        is_mixing_allowed: true,
        is_active: true,
      })
    }
  }

  return locations
}

export function buildLots(items) {
  const lots = []
  let id = 1
  const today = new Date()
  for (const item of items) {
    if (!item.tracks_lot) {
      lots.push({ id: id++, item_id: item.id, code: '-', expires_at: null })
      continue
    }
    const n = item.tracks_expiry ? 2 : 1
    for (let k = 0; k < n; k++) {
      const made = new Date(today.getTime() - (30 + k * 45) * 86400000)
      const yy = String(made.getFullYear()).slice(2)
      const mm = String(made.getMonth() + 1).padStart(2, '0')
      lots.push({
        id: id++,
        item_id: item.id,
        code: `L${yy}${mm}${String.fromCharCode(65 + k)}`,
        manufactured_at: made.toISOString().slice(0, 10),
        expires_at: item.tracks_expiry
          ? new Date(made.getTime() + 365 * 86400000).toISOString().slice(0, 10)
          : null,
      })
    }
  }
  return lots
}

/** Saldos iniciales: reparte cada ítem en 2-4 ubicaciones de su almacén. */
export function buildBalances(items, locations, lots) {
  const balances = []
  let rnd = 20260919 // semilla fija: la demo siempre arranca igual
  const next = () => {
    rnd = (rnd * 1103515245 + 12345) & 0x7fffffff
    return rnd / 0x7fffffff
  }

  for (const item of items) {
    for (const whId of item.warehouse_ids) {
      const pool = locations.filter((l) => l.warehouse_id === whId && l.zone_type === 'ALMACENAJE')
      if (!pool.length) continue
      const itemLots = lots.filter((l) => l.item_id === item.id)
      const spread = 2 + Math.floor(next() * 3)
      for (let k = 0; k < spread; k++) {
        const loc = pool[Math.floor(next() * pool.length)]
        const lot = itemLots[Math.floor(next() * itemLots.length)]
        if (!loc || !lot) continue
        const qty =
          item.base_uom === 'KG'
            ? Math.round(next() * 800 + 50)
            : Math.round(next() * 40 + 5)
        balances.push({
          warehouse_id: whId,
          location_id: loc.id,
          item_id: item.id,
          lot_id: lot.id,
          status: 'BUENO',
          qty,
        })
      }
    }
  }
  return balances
}

/** Notas de ingreso abiertas, incluida la Y8232752 que el operador reconoce. */
export function buildReceipts(items) {
  const bags = items.filter((i) => i.category === 'BOLSAS')
  const raw = items.filter((i) => i.category === 'MATERIA PRIMA')
  const today = new Date().toISOString().slice(0, 10)

  const mk = (id, number, type, warehouse_id, pool, count, supplier) => {
    const lines = []
    for (let k = 0; k < count; k++) {
      const item = pool[(k * 3 + id) % pool.length]
      lines.push({
        id: id * 100 + k + 1,
        receipt_id: id,
        line_no: k + 1,
        item_id: item.id,
        lot_code: item.tracks_lot ? `L2609${String.fromCharCode(65 + (k % 3))}` : '-',
        qty_expected: item.base_uom === 'KG' ? 500 : 5 * (k + 2),
        qty_received: 0,
        uom: item.base_uom,
        status: 'PENDIENTE',
      })
    }
    return {
      receipt: {
        id,
        number,
        type,
        warehouse_id,
        status: 'ABIERTA',
        supplier_name: supplier,
        expected_at: today,
        external_ref: null,
      },
      lines,
    }
  }

  return [
    mk(1, 'Y8232752', 'PRODUCCION', 1, bags, 6, 'Extrusión — Turno A'),
    mk(2, 'Y8232761', 'PRODUCCION', 1, bags, 4, 'Extrusión — Turno B'),
    mk(3, 'C0044120', 'COMPRA', 2, raw, 3, 'Braskem Bolivia S.A.'),
  ]
}
