/**
 * Fachada única que usan las vistas.
 *
 * Decide entre el backend real y el simulado, y —lo importante— hace que
 * **toda escritura pase por la cola**. Así el camino feliz y el camino sin
 * señal son el mismo código: si el envío falla, la operación queda pendiente
 * y se reintenta; nunca se pierde y nunca se duplica (client_uuid).
 */

import * as demo from './demoApi.js'
import * as api from './api.js'
import { ApiError } from './demoApi.js'
import {
  enqueue,
  updateOutbox,
  outboxPending,
  outboxAll,
  OUTBOX,
  uuid,
  getKv,
  setKv,
  findItemByBarcode,
  findLocationByBarcode,
  findItemBySku,
  allOf,
  putCatalog,
} from './db.js'
import { looksLikeGs1, parseGs1 } from './gs1.js'

export { ApiError }

const state = {
  mode: 'demo', // 'demo' | 'server'
  serverUrl: '',
  token: null,
  online: navigator.onLine,
}

export function mode() {
  return state.mode
}
export function isDemo() {
  return state.mode === 'demo'
}
export function isOnline() {
  return state.mode === 'demo' ? true : state.online
}

export async function initClient() {
  const url = await getKv('server_url', '')
  const token = await getKv('token', null)
  state.serverUrl = url || ''
  state.token = token
  state.mode = url ? 'server' : 'demo'
  api.configure({ url: state.serverUrl, authToken: state.token })
  window.addEventListener('online', () => (state.online = true))
  window.addEventListener('offline', () => (state.online = false))
  if (state.mode === 'demo') await demo.ensureSeeded()
}

export async function setServerUrl(url) {
  state.serverUrl = String(url || '').trim().replace(/\/+$/, '')
  state.mode = state.serverUrl ? 'server' : 'demo'
  await setKv('server_url', state.serverUrl)
  api.configure({ url: state.serverUrl })
  if (state.mode === 'demo') await demo.ensureSeeded()
}

export async function setToken(token) {
  state.token = token
  await setKv('token', token)
  api.configure({ authToken: token })
}

export async function deviceSerial() {
  let s = await getKv('device_serial', null)
  if (!s) {
    s = `WEB-${uuid().slice(0, 8).toUpperCase()}`
    await setKv('device_serial', s)
  }
  return s
}

/* ------------------------------------------------------------------ *
 * Auth
 * ------------------------------------------------------------------ */
export async function login(credentials) {
  const payload = { ...credentials, device_serial: await deviceSerial(), app_version: __APP_VERSION__ }
  const res = isDemo() ? await demo.login(payload) : await api.login(payload)
  await setToken(res.token)
  return res
}

/* ------------------------------------------------------------------ *
 * Maestros (siempre se cachean: es lo que permite operar sin señal)
 * ------------------------------------------------------------------ */
export async function syncCatalog(warehouseId) {
  const res = isDemo()
    ? await demo.catalog({ warehouse_id: warehouseId })
    : await api.catalog({ warehouse_id: warehouseId })

  if (!isDemo()) {
    await putCatalog('catalog_items', res.items.map(({ barcodes, ...i }) => i))
    await putCatalog(
      'catalog_barcodes',
      res.items.flatMap((i) => (i.barcodes || []).map((b) => ({ ...b, item_id: i.id })))
    )
    await putCatalog('catalog_locations', res.locations)
    await putCatalog('catalog_uoms', res.uoms || [])
    await putCatalog('catalog_reasons', res.reason_codes || [])
  }
  await setKv('catalog_synced_at', new Date().toISOString())
  await setKv('catalog_warehouse_id', warehouseId)
  return res
}

export async function catalogStats() {
  const [items, locations] = await Promise.all([allOf('catalog_items'), allOf('catalog_locations')])
  return {
    items: items.length,
    locations: locations.length,
    syncedAt: await getKv('catalog_synced_at', null),
  }
}

/* ------------------------------------------------------------------ *
 * Resolución de escaneo — SIEMPRE local primero (latencia cero)
 * ------------------------------------------------------------------ */
export async function resolveScan(code, { warehouseId } = {}) {
  const raw = String(code || '').trim()
  if (!raw) return { kind: 'UNKNOWN', code: raw }

  // 1) ¿Es GS1? Extraemos GTIN/lote/vencimiento y seguimos resolviendo el ítem.
  if (looksLikeGs1(raw)) {
    const parsed = parseGs1(raw)
    const key = parsed.gtin || raw
    const hit = (await findItemByBarcode(key)) || (parsed.gtin ? await findItemByBarcode(stripGtin(parsed.gtin)) : null)
    return {
      kind: 'GS1',
      code: raw,
      parsed,
      item: hit?.item || null,
      barcode: hit?.barcode || null,
      lot_code: parsed.lot || null,
      expires_at: parsed.expiresAt || null,
      qty: parsed.qty || null,
    }
  }

  // 2) ¿Ubicación?
  const loc = await findLocationByBarcode(raw)
  if (loc && (!warehouseId || loc.warehouse_id === warehouseId)) {
    return { kind: 'LOCATION', code: raw, location: loc }
  }
  if (loc) {
    return { kind: 'LOCATION_OTHER_WAREHOUSE', code: raw, location: loc }
  }

  // 3) ¿Ítem por código de barras o por SKU?
  const hit = await findItemByBarcode(raw)
  if (hit) return { kind: 'ITEM', code: raw, item: hit.item, barcode: hit.barcode }

  const bySku = await findItemBySku(raw)
  if (bySku) {
    return { kind: 'ITEM', code: raw, item: bySku, barcode: { barcode: raw, uom: bySku.base_uom, qty_per_scan: 1 } }
  }

  return { kind: 'UNKNOWN', code: raw }
}

const stripGtin = (g) => String(g).replace(/^0+/, '')

/* ------------------------------------------------------------------ *
 * Lecturas
 * ------------------------------------------------------------------ */
export const stockByLocation = (id) => (isDemo() ? demo.stockByLocation(id) : api.stockByLocation(id))
export const stockByItem = (id, wh) => (isDemo() ? demo.stockByItem(id, wh) : api.stockByItem(id, wh))
export const kardex = (q) => (isDemo() ? demo.kardex(q) : api.kardex(q))
export const receipts = (q) => (isDemo() ? demo.receipts(q) : api.receipts(q))
export const receipt = (id) => (isDemo() ? demo.receipt(id) : api.receipt(id))
export const suggestLocations = (q) => (isDemo() ? demo.suggestLocations(q) : Promise.resolve([]))
export const closeReceipt = (id, p) => (isDemo() ? demo.closeReceipt(id, p) : api.closeReceipt(id, p))

/* ------------------------------------------------------------------ *
 * Escrituras: cola + envío inmediato
 * ------------------------------------------------------------------ */

/**
 * Registra una operación. Devuelve `{ sent, row, result, error }`.
 * `sent:false` NO es un fallo: significa que quedó en cola y el operador
 * puede seguir escaneando. Es el comportamiento correcto en una nave.
 */
export async function submit(op) {
  const row = await enqueue({
    client_uuid: op.client_uuid || uuid(),
    kind: op.kind, // 'RECEIPT_SCAN' | 'MOVEMENT'
    endpoint: op.endpoint,
    payload: op.payload,
    label: op.label || '',
  })
  return flushOne(row)
}

async function flushOne(row) {
  await updateOutbox(row.client_uuid, { status: OUTBOX.SENDING })
  try {
    const payload = { ...row.payload, client_uuid: row.client_uuid }
    let result
    if (row.kind === 'RECEIPT_SCAN') {
      result = isDemo()
        ? await demo.postReceiptScan(row.payload.receipt_id, payload)
        : await api.postReceiptScan(row.payload.receipt_id, payload)
    } else {
      result = isDemo() ? await demo.postMovement(payload) : await api.postMovement(payload)
    }
    await updateOutbox(row.client_uuid, { status: OUTBOX.DONE, result, error: null })
    return { sent: true, row, result }
  } catch (e) {
    const isNetwork = e.code === 'NETWORK' || e.code === 'NO_SERVER' || e.status === 0
    await updateOutbox(row.client_uuid, {
      status: isNetwork ? OUTBOX.PENDING : OUTBOX.FAILED,
      attempts: (row.attempts || 0) + 1,
      error: { code: e.code, message: e.message, details: e.details },
    })
    // Un error de red no se le muestra como fallo al operador: quedó encolado.
    if (isNetwork) return { sent: false, queued: true, row }
    return { sent: false, queued: false, row, error: e }
  }
}

/** Reintenta todo lo pendiente. Se llama al recuperar señal y desde la vista de cola. */
export async function flushOutbox() {
  const pending = await outboxPending()
  const results = []
  for (const row of pending) {
    results.push(await flushOne(row))
  }
  return results
}

export async function retryFailed(client_uuid) {
  const all = await outboxAll()
  const row = all.find((r) => r.client_uuid === client_uuid)
  if (!row) return null
  return flushOne(row)
}

export async function outboxSummary() {
  const rows = await outboxAll()
  return {
    pending: rows.filter((r) => r.status === OUTBOX.PENDING || r.status === OUTBOX.SENDING).length,
    failed: rows.filter((r) => r.status === OUTBOX.FAILED).length,
    done: rows.filter((r) => r.status === OUTBOX.DONE).length,
    rows,
  }
}

export const resetDemoData = () => demo.resetDemo()
