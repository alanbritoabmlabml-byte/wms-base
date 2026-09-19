/**
 * Cliente HTTP contra la API Laravel (docs/05-contrato-api.md).
 * No contiene lógica de negocio: traduce llamadas y errores.
 */

import { ApiError } from './demoApi.js'

let baseUrl = ''
let token = null

export function configure({ url, authToken }) {
  if (url !== undefined) baseUrl = String(url || '').replace(/\/+$/, '')
  if (authToken !== undefined) token = authToken
}

export function currentBaseUrl() {
  return baseUrl
}

async function request(method, path, body, { timeout = 12000 } = {}) {
  if (!baseUrl) throw new ApiError('NO_SERVER', 'No hay servidor configurado', {}, 0)
  const ctrl = new AbortController()
  const timer = setTimeout(() => ctrl.abort(), timeout)
  let res
  try {
    res = await fetch(`${baseUrl}/api/v1${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
      signal: ctrl.signal,
    })
  } catch (e) {
    clearTimeout(timer)
    // Sin red: quien llama decide si encola o usa caché.
    throw new ApiError('NETWORK', 'Sin conexión con el servidor', { cause: String(e) }, 0)
  }
  clearTimeout(timer)

  if (res.status === 204) return null
  let json = null
  try {
    json = await res.json()
  } catch {
    /* respuesta vacía */
  }

  if (!res.ok) {
    const err = json?.error || {}
    throw new ApiError(err.code || `HTTP_${res.status}`, err.message || `Error ${res.status}`, err.details || {}, res.status)
  }
  return json
}

export const http = {
  get: (p, o) => request('GET', p, null, o),
  post: (p, b, o) => request('POST', p, b, o),
  del: (p, o) => request('DELETE', p, null, o),
}

export const login = (payload) => http.post('/auth/login', payload)
export const me = () => http.get('/auth/me')
export const logout = () => http.post('/auth/logout')
export const catalog = ({ warehouse_id, since }) =>
  http.get(`/sync/catalog?warehouse_id=${warehouse_id}${since ? `&since=${encodeURIComponent(since)}` : ''}`, { timeout: 30000 })
export const resolveScan = (payload) => http.post('/scan/resolve', payload)
export const stockByLocation = (id) => http.get(`/stock/by-location/${id}`)
export const stockByItem = (id, wh) => http.get(`/stock/by-item/${id}?warehouse_id=${wh}`)
export const kardex = (q) => http.get(`/stock/kardex?item_id=${q.item_id}&warehouse_id=${q.warehouse_id}`)
export const receipts = ({ warehouse_id }) => http.get(`/receipts?warehouse_id=${warehouse_id}`)
export const receipt = (id) => http.get(`/receipts/${id}`)
export const postReceiptScan = (id, payload) => http.post(`/receipts/${id}/scans`, payload)
export const closeReceipt = (id, payload) => http.post(`/receipts/${id}/close`, payload)
export const postMovement = (payload) => http.post('/movements', payload)
export const health = () => http.get('/health', { timeout: 5000 })
