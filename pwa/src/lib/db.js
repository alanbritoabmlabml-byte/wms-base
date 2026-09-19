import { openDB } from 'idb'

/**
 * IndexedDB del colector.
 *
 * Tres bloques con propósitos distintos:
 *  - kv / catalog_*  → caché de maestros. Permite resolver un escaneo en 0 ms
 *                      y seguir trabajando cuando la nave se queda sin WiFi.
 *  - outbox          → cola de operaciones pendientes de enviar. Cada una lleva
 *                      su client_uuid, así el reintento nunca duplica stock.
 *  - demo_*          → backend simulado. Sirve para probar la app completa en
 *                      cualquier teléfono, sin levantar Laravel.
 */

const DB_NAME = 'wms-base'
const DB_VERSION = 1

let dbp = null

export function db() {
  if (!dbp) {
    dbp = openDB(DB_NAME, DB_VERSION, {
      upgrade(d) {
        d.createObjectStore('kv')

        const items = d.createObjectStore('catalog_items', { keyPath: 'id' })
        items.createIndex('sku', 'sku', { unique: false })

        const barcodes = d.createObjectStore('catalog_barcodes', { keyPath: 'barcode' })
        barcodes.createIndex('item_id', 'item_id')

        const locs = d.createObjectStore('catalog_locations', { keyPath: 'id' })
        locs.createIndex('barcode', 'barcode')
        locs.createIndex('warehouse_id', 'warehouse_id')

        d.createObjectStore('catalog_uoms', { keyPath: 'code' })
        d.createObjectStore('catalog_reasons', { keyPath: 'id' })

        const out = d.createObjectStore('outbox', { keyPath: 'client_uuid' })
        out.createIndex('status', 'status')
        out.createIndex('created_at', 'created_at')

        // --- backend simulado ---
        d.createObjectStore('demo_users', { keyPath: 'username' })
        const bal = d.createObjectStore('demo_balances', { keyPath: 'key' })
        bal.createIndex('location_id', 'location_id')
        bal.createIndex('item_id', 'item_id')
        bal.createIndex('warehouse_id', 'warehouse_id')
        const mov = d.createObjectStore('demo_movements', { keyPath: 'id', autoIncrement: true })
        mov.createIndex('client_uuid', 'client_uuid', { unique: true })
        mov.createIndex('item_id', 'item_id')
        mov.createIndex('warehouse_id', 'warehouse_id')
        const rec = d.createObjectStore('demo_receipts', { keyPath: 'id' })
        rec.createIndex('warehouse_id', 'warehouse_id')
        const lines = d.createObjectStore('demo_receipt_lines', { keyPath: 'id' })
        lines.createIndex('receipt_id', 'receipt_id')
        d.createObjectStore('demo_lots', { keyPath: 'id' })
      },
    })
  }
  return dbp
}

/** Vue entrega proxies reactivos; IndexedDB solo acepta objetos planos. */
export function plain(value) {
  if (value === null || value === undefined) return value
  return JSON.parse(JSON.stringify(value))
}

/* ---------------- kv ---------------- */
export async function getKv(key, fallback = null) {
  const v = await (await db()).get('kv', key)
  return v === undefined ? fallback : v
}
export async function setKv(key, value) {
  return (await db()).put('kv', plain(value), key)
}
export async function delKv(key) {
  return (await db()).delete('kv', key)
}

/* ---------------- maestros ---------------- */
export async function putCatalog(store, rows) {
  const d = await db()
  const tx = d.transaction(store, 'readwrite')
  await Promise.all(rows.map((r) => tx.store.put(r)))
  await tx.done
}

export async function allOf(store) {
  return (await db()).getAll(store)
}

export async function findLocationByBarcode(code) {
  const d = await db()
  const byBarcode = await d.getFromIndex('catalog_locations', 'barcode', code)
  if (byBarcode) return byBarcode
  // el código impreso puede diferir del "code" legible
  const all = await d.getAll('catalog_locations')
  return all.find((l) => l.code?.toUpperCase() === code.toUpperCase()) || null
}

export async function findItemByBarcode(code) {
  const d = await db()
  const bc = await d.get('catalog_barcodes', code)
  if (!bc) return null
  const item = await d.get('catalog_items', bc.item_id)
  return item ? { item, barcode: bc } : null
}

export async function findItemBySku(sku) {
  const d = await db()
  const all = await d.getAll('catalog_items')
  return all.find((i) => i.sku?.toUpperCase() === String(sku).toUpperCase()) || null
}

/* ---------------- outbox ---------------- */
export const OUTBOX = {
  PENDING: 'PENDIENTE',
  SENDING: 'ENVIANDO',
  DONE: 'ENVIADO',
  FAILED: 'ERROR',
}

export async function enqueue(op) {
  const d = await db()
  const row = plain({
    status: OUTBOX.PENDING,
    attempts: 0,
    created_at: new Date().toISOString(),
    ...op,
  })
  await d.put('outbox', row)
  return row
}

export async function outboxAll() {
  const rows = await (await db()).getAll('outbox')
  return rows.sort((a, b) => a.created_at.localeCompare(b.created_at))
}

export async function outboxPending() {
  return (await outboxAll()).filter((r) => r.status === OUTBOX.PENDING || r.status === OUTBOX.SENDING)
}

export async function outboxFailed() {
  return (await outboxAll()).filter((r) => r.status === OUTBOX.FAILED)
}

export async function updateOutbox(client_uuid, patch) {
  const d = await db()
  const row = await d.get('outbox', client_uuid)
  if (!row) return null
  const next = plain({ ...row, ...patch })
  await d.put('outbox', next)
  return next
}

export async function removeOutbox(client_uuid) {
  return (await db()).delete('outbox', client_uuid)
}

/** Limpia los enviados con más de 48 h; el resto se conserva para auditoría. */
export async function pruneOutbox() {
  const cutoff = Date.now() - 48 * 3600 * 1000
  const d = await db()
  for (const row of await d.getAll('outbox')) {
    if (row.status === OUTBOX.DONE && Date.parse(row.created_at) < cutoff) {
      await d.delete('outbox', row.client_uuid)
    }
  }
}

export function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID()
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}

export async function wipeAll() {
  const d = await db()
  for (const name of Array.from(d.objectStoreNames)) {
    await d.clear(name)
  }
}
