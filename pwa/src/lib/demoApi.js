/**
 * Backend simulado.
 *
 * Implementa el mismo contrato que la API Laravel (docs/05-contrato-api.md)
 * contra IndexedDB, incluyendo el comportamiento que de verdad importa:
 * idempotencia por client_uuid, validación de stock y kardex append-only.
 *
 * Existe para que puedas abrir la app en el colector y operar de punta a punta
 * antes de que el servidor esté desplegado — y para poder demostrar el flujo
 * en una reunión sin depender de la red de la planta.
 */

import { db, getKv, setKv, plain } from './db.js'
import * as seed from './seed.js'

const nowIso = () => new Date().toISOString()
const balanceKey = (b) => `${b.warehouse_id}|${b.location_id}|${b.item_id}|${b.lot_id}|${b.status}`

export class ApiError extends Error {
  constructor(code, message, details = {}, status = 422) {
    super(message)
    this.code = code
    this.details = details
    this.status = status
  }
}

/* ------------------------------------------------------------------ *
 * Siembra
 * ------------------------------------------------------------------ */
export async function ensureSeeded(force = false) {
  const version = await getKv('demo_seed_version')
  if (version === 3 && !force) return

  const d = await db()
  for (const s of ['demo_users', 'demo_balances', 'demo_movements', 'demo_receipts', 'demo_receipt_lines', 'demo_lots', 'catalog_items', 'catalog_barcodes', 'catalog_locations', 'catalog_uoms', 'catalog_reasons']) {
    await d.clear(s)
  }

  const { items, barcodes } = seed.buildItems()
  const locations = seed.buildLocations()
  const lots = seed.buildLots(items)
  const balances = seed.buildBalances(items, locations, lots)
  const receipts = seed.buildReceipts(items)

  const tx = d.transaction(
    ['demo_users', 'demo_lots', 'demo_balances', 'demo_receipts', 'demo_receipt_lines', 'catalog_items', 'catalog_barcodes', 'catalog_locations', 'catalog_uoms', 'catalog_reasons'],
    'readwrite'
  )
  for (const u of seed.USERS) tx.objectStore('demo_users').put(u)
  for (const l of lots) tx.objectStore('demo_lots').put(l)
  for (const i of items) tx.objectStore('catalog_items').put(i)
  for (const b of barcodes) tx.objectStore('catalog_barcodes').put(b)
  for (const l of locations) tx.objectStore('catalog_locations').put(l)
  for (const u of seed.UOMS) tx.objectStore('catalog_uoms').put(u)
  for (const r of seed.REASON_CODES) tx.objectStore('catalog_reasons').put(r)
  for (const b of balances) {
    tx.objectStore('demo_balances').put({ ...b, key: balanceKey(b), last_movement_at: nowIso() })
  }
  for (const { receipt, lines } of receipts) {
    tx.objectStore('demo_receipts').put(receipt)
    for (const line of lines) tx.objectStore('demo_receipt_lines').put(line)
  }
  await tx.done

  await setKv('demo_seed_version', 3)
}

export async function resetDemo() {
  await setKv('demo_seed_version', null)
  await ensureSeeded(true)
}

/* ------------------------------------------------------------------ *
 * Auth
 * ------------------------------------------------------------------ */
export async function login({ username, password }) {
  await ensureSeeded()
  const user = await (await db()).get('demo_users', String(username || '').toLowerCase().trim())
  if (!user || user.password !== password) {
    throw new ApiError('UNAUTHENTICATED', 'Usuario o contraseña incorrectos', {}, 401)
  }
  return {
    token: `demo-${user.username}`,
    user: { id: user.username, name: user.name, username: user.username },
    warehouses: seed.WAREHOUSES.filter((w) => user.warehouse_ids.includes(w.id)).map((w) => ({
      ...w,
      role: user.role,
    })),
    permissions: user.permissions,
    server_time: nowIso(),
  }
}

/* ------------------------------------------------------------------ *
 * Maestros
 * ------------------------------------------------------------------ */
export async function catalog({ warehouse_id }) {
  await ensureSeeded()
  const d = await db()
  const [items, barcodes, locations, uoms, reasons] = await Promise.all([
    d.getAll('catalog_items'),
    d.getAll('catalog_barcodes'),
    d.getAll('catalog_locations'),
    d.getAll('catalog_uoms'),
    d.getAll('catalog_reasons'),
  ])
  return {
    full: true,
    server_time: nowIso(),
    items: items.map((i) => ({ ...i, barcodes: barcodes.filter((b) => b.item_id === i.id) })),
    locations: locations.filter((l) => !warehouse_id || l.warehouse_id === warehouse_id),
    uoms,
    reason_codes: reasons,
  }
}

/* ------------------------------------------------------------------ *
 * Stock
 * ------------------------------------------------------------------ */
async function balancesOf(filter) {
  const rows = await (await db()).getAll('demo_balances')
  return rows.filter(filter).filter((b) => Number(b.qty) !== 0)
}

export async function stockByLocation(locationId) {
  await ensureSeeded()
  const d = await db()
  const [items, lots] = await Promise.all([d.getAll('catalog_items'), d.getAll('demo_lots')])
  const rows = await balancesOf((b) => b.location_id === Number(locationId))
  return rows.map((b) => ({
    ...b,
    item: items.find((i) => i.id === b.item_id) || null,
    lot: lots.find((l) => l.id === b.lot_id) || null,
  }))
}

export async function stockByItem(itemId, warehouseId) {
  await ensureSeeded()
  const d = await db()
  const [locations, lots] = await Promise.all([d.getAll('catalog_locations'), d.getAll('demo_lots')])
  const rows = await balancesOf(
    (b) => b.item_id === Number(itemId) && (!warehouseId || b.warehouse_id === Number(warehouseId))
  )
  return rows
    .map((b) => ({
      ...b,
      location: locations.find((l) => l.id === b.location_id) || null,
      lot: lots.find((l) => l.id === b.lot_id) || null,
    }))
    // FEFO: primero lo que vence antes; a igual vencimiento, la ruta más corta.
    .sort((a, b) => {
      const ea = a.lot?.expires_at || '9999-12-31'
      const eb = b.lot?.expires_at || '9999-12-31'
      if (ea !== eb) return ea.localeCompare(eb)
      return (a.location?.sort_seq || 0) - (b.location?.sort_seq || 0)
    })
}

export async function kardex({ item_id, warehouse_id, limit = 100 }) {
  await ensureSeeded()
  const d = await db()
  const [movs, locations, lots] = await Promise.all([
    d.getAll('demo_movements'),
    d.getAll('catalog_locations'),
    d.getAll('demo_lots'),
  ])
  const rows = movs
    .filter((m) => m.item_id === Number(item_id) && (!warehouse_id || m.warehouse_id === Number(warehouse_id)))
    .sort((a, b) => b.occurred_at.localeCompare(a.occurred_at))
    .slice(0, limit)
  return rows.map((m) => ({
    ...m,
    from_location: locations.find((l) => l.id === m.from_location_id) || null,
    to_location: locations.find((l) => l.id === m.to_location_id) || null,
    lot: lots.find((l) => l.id === m.lot_id) || null,
  }))
}

/* ------------------------------------------------------------------ *
 * El ledger: único punto por donde se mueve stock
 * ------------------------------------------------------------------ */
export async function postMovement(payload) {
  await ensureSeeded()
  const d = await db()

  // 1) Idempotencia. Un reintento de la cola no puede duplicar el stock.
  const existing = await d.getFromIndex('demo_movements', 'client_uuid', payload.client_uuid)
  if (existing) return { movement: existing, idempotent: true }

  const {
    type,
    warehouse_id,
    item_id,
    lot_id,
    from_location_id = null,
    to_location_id = null,
    qty,
    status = 'BUENO',
  } = payload

  const q = Number(qty)
  if (!(q > 0)) throw new ApiError('VALIDATION_FAILED', 'La cantidad debe ser mayor a cero', { field: 'qty' })

  // 2) Validaciones de ubicación
  for (const [locId, label] of [
    [from_location_id, 'origen'],
    [to_location_id, 'destino'],
  ]) {
    if (!locId) continue
    const loc = await d.get('catalog_locations', locId)
    if (!loc) throw new ApiError('NOT_FOUND', `Ubicación ${label} inexistente`)
    if (loc.warehouse_id !== warehouse_id) {
      throw new ApiError('LOCATION_NOT_IN_WAREHOUSE', `La ubicación ${loc.code} es de otro almacén`)
    }
    if (!loc.is_active) throw new ApiError('LOCATION_INACTIVE', `La ubicación ${loc.code} está inactiva`)
  }

  // 3) Stock suficiente en el origen
  if (from_location_id) {
    const key = balanceKey({ warehouse_id, location_id: from_location_id, item_id, lot_id, status })
    const bal = await d.get('demo_balances', key)
    const available = Number(bal?.qty || 0)
    if (available < q) {
      const loc = await d.get('catalog_locations', from_location_id)
      throw new ApiError(
        'INSUFFICIENT_STOCK',
        `Stock insuficiente en ${loc?.code || from_location_id}`,
        { available, requested: q }
      )
    }
  }

  // 4) No mezclar ítems donde no está permitido
  if (to_location_id) {
    const loc = await d.get('catalog_locations', to_location_id)
    if (loc && !loc.is_mixing_allowed) {
      const rows = await d.getAllFromIndex('demo_balances', 'location_id', to_location_id)
      const other = rows.find((r) => Number(r.qty) > 0 && r.item_id !== item_id)
      if (other) {
        throw new ApiError('MIXING_NOT_ALLOWED', `${loc.code} ya tiene otro ítem y no admite mezcla`)
      }
    }
  }

  // 5) Asiento + saldos, en una sola transacción
  const tx = d.transaction(['demo_movements', 'demo_balances'], 'readwrite')
  const movement = {
    uuid: payload.client_uuid,
    client_uuid: payload.client_uuid,
    type,
    warehouse_id,
    item_id,
    lot_id,
    from_location_id,
    to_location_id,
    qty: q,
    status,
    scanned_barcode: payload.scanned_barcode || null,
    reason_code_id: payload.reason_code_id || null,
    document_type: payload.document_type || null,
    document_id: payload.document_id || null,
    user: payload.user || null,
    device_serial: payload.device_serial || null,
    occurred_at: payload.occurred_at || nowIso(),
    created_at: nowIso(),
  }
  const movId = await tx.objectStore('demo_movements').add(plain(movement))
  movement.id = movId

  const applyDelta = async (locationId, delta) => {
    if (!locationId) return
    const store = tx.objectStore('demo_balances')
    const key = balanceKey({ warehouse_id, location_id: locationId, item_id, lot_id, status })
    const row = (await store.get(key)) || {
      key,
      warehouse_id,
      location_id: locationId,
      item_id,
      lot_id,
      status,
      qty: 0,
    }
    row.qty = Number((Number(row.qty) + delta).toFixed(4))
    row.last_movement_at = nowIso()
    await store.put(row)
  }

  await applyDelta(from_location_id, -q)
  await applyDelta(to_location_id, q)
  await tx.done

  return { movement, idempotent: false }
}

/* ------------------------------------------------------------------ *
 * Recepción
 * ------------------------------------------------------------------ */
export async function receipts({ warehouse_id }) {
  await ensureSeeded()
  const d = await db()
  const [recs, lines] = await Promise.all([d.getAll('demo_receipts'), d.getAll('demo_receipt_lines')])
  return recs
    .filter((r) => !warehouse_id || r.warehouse_id === Number(warehouse_id))
    .map((r) => {
      const own = lines.filter((l) => l.receipt_id === r.id)
      return {
        ...r,
        lines_total: own.length,
        lines_done: own.filter((l) => l.status === 'COMPLETA' || l.status === 'EXCEDIDA').length,
      }
    })
    .sort((a, b) => (a.status === 'CERRADA' ? 1 : -1))
}

export async function receipt(id) {
  await ensureSeeded()
  const d = await db()
  const rec = await d.get('demo_receipts', Number(id))
  if (!rec) throw new ApiError('NOT_FOUND', 'Nota de ingreso inexistente', {}, 404)
  const [allLines, items] = await Promise.all([
    d.getAllFromIndex('demo_receipt_lines', 'receipt_id', Number(id)),
    d.getAll('catalog_items'),
  ])
  const lines = allLines
    .sort((a, b) => a.line_no - b.line_no)
    .map((l) => ({ ...l, item: items.find((i) => i.id === l.item_id) || null }))
  return { ...rec, lines }
}

/** Ubicaciones sugeridas: donde ya está el ítem, luego cercanas y vacías. */
export async function suggestLocations({ item_id, warehouse_id, limit = 5 }) {
  await ensureSeeded()
  const d = await db()
  const locations = (await d.getAll('catalog_locations')).filter(
    (l) => l.warehouse_id === Number(warehouse_id) && l.zone_type === 'ALMACENAJE' && l.is_active
  )
  const balances = await d.getAll('demo_balances')
  const withItem = new Set(
    balances.filter((b) => b.item_id === Number(item_id) && Number(b.qty) > 0).map((b) => b.location_id)
  )
  const occupied = new Set(balances.filter((b) => Number(b.qty) > 0).map((b) => b.location_id))

  const scored = locations.map((l) => ({
    location: l,
    reason: withItem.has(l.id) ? 'MISMO_ITEM' : occupied.has(l.id) ? 'OCUPADA' : 'VACIA_CERCANA',
    score: withItem.has(l.id) ? 0 : occupied.has(l.id) ? 2 : 1,
  }))
  return scored
    .sort((a, b) => a.score - b.score || a.location.sort_seq - b.location.sort_seq)
    .slice(0, limit)
}

export async function postReceiptScan(receiptId, payload) {
  await ensureSeeded()
  const d = await db()
  const rec = await d.get('demo_receipts', Number(receiptId))
  if (!rec) throw new ApiError('NOT_FOUND', 'Nota de ingreso inexistente', {}, 404)
  if (rec.status === 'CERRADA' || rec.status === 'ANULADA') {
    throw new ApiError('RECEIPT_CLOSED', `La nota ${rec.number} ya está ${rec.status.toLowerCase()}`)
  }

  const line = await d.get('demo_receipt_lines', Number(payload.receipt_line_id))
  if (!line) throw new ApiError('NOT_FOUND', 'Línea inexistente', {}, 404)

  const lotId = await resolveLot(payload.item_id, payload.lot_code)

  const { movement, idempotent } = await postMovement({
    ...payload,
    type: 'RECEPCION',
    warehouse_id: rec.warehouse_id,
    lot_id: lotId,
    from_location_id: null,
    to_location_id: payload.location_id,
    document_type: 'RECEPCION',
    document_id: rec.id,
  })

  if (!idempotent) {
    line.qty_received = Number((Number(line.qty_received) + Number(payload.qty)).toFixed(4))
    line.status =
      line.qty_received >= line.qty_expected
        ? line.qty_received > line.qty_expected
          ? 'EXCEDIDA'
          : 'COMPLETA'
        : line.qty_received > 0
          ? 'PARCIAL'
          : 'PENDIENTE'
    await d.put('demo_receipt_lines', line)

    if (rec.status === 'ABIERTA') {
      rec.status = 'EN_PROCESO'
      await d.put('demo_receipts', rec)
    }
  }

  return { movement, line, idempotent }
}

export async function closeReceipt(receiptId, { force = false } = {}) {
  const d = await db()
  const rec = await d.get('demo_receipts', Number(receiptId))
  if (!rec) throw new ApiError('NOT_FOUND', 'Nota de ingreso inexistente', {}, 404)
  const lines = await d.getAllFromIndex('demo_receipt_lines', 'receipt_id', Number(receiptId))
  const pending = lines.filter((l) => l.status === 'PENDIENTE' || l.status === 'PARCIAL')
  if (pending.length && !force) {
    throw new ApiError(
      'RECEIPT_LINES_PENDING',
      `Quedan ${pending.length} línea(s) sin completar`,
      { pending: pending.length },
      409
    )
  }
  rec.status = 'CERRADA'
  rec.closed_at = nowIso()
  await d.put('demo_receipts', rec)
  return rec
}

async function resolveLot(itemId, lotCode) {
  const d = await db()
  const code = lotCode || '-'
  const lots = await d.getAll('demo_lots')
  const found = lots.find((l) => l.item_id === Number(itemId) && l.code === code)
  if (found) return found.id
  const id = Math.max(0, ...lots.map((l) => l.id)) + 1
  await d.put('demo_lots', { id, item_id: Number(itemId), code, expires_at: null })
  return id
}

export { resolveLot }
