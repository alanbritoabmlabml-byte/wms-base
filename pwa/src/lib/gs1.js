/**
 * Parser GS1-128 / GS1 DataMatrix.
 *
 * Los códigos GS1 concatenan pares (AI, valor). Los AI de longitud fija se
 * cortan por tamaño; los de longitud variable terminan en el separador FNC1,
 * que DataWedge entrega como GS (0x1D) — y que algunos perfiles mal
 * configurados entregan como nada, por eso aceptamos también el formato con
 * paréntesis "(01)0750...(10)L26".
 *
 * Referencia: GS1 General Specifications, sección 3 (Application Identifiers).
 */

const FNC1 = '\u001d'

// AI de longitud fija: prefijo -> largo total del valor (sin el AI)
const FIXED = {
  '00': 18, // SSCC (matrícula logística / pallet)
  '01': 14, // GTIN
  '02': 14, // GTIN de los productos contenidos
  11: 6, // fecha de producción AAMMDD
  12: 6, // fecha de vencimiento de pago
  13: 6, // fecha de envasado
  15: 6, // consumo preferente
  16: 6, // fecha de venta
  17: 6, // fecha de caducidad
  20: 2, // variante de producto
  410: 13,
  411: 13,
  412: 13,
  413: 13,
  414: 13,
  415: 13,
  416: 13,
  422: 3,
}

// AI de longitud variable: prefijo -> largo máximo
const VARIABLE = {
  10: 20, // lote
  21: 20, // serie
  22: 20,
  30: 8, // cantidad de unidades
  37: 8, // cantidad en unidades comerciales
  240: 30,
  241: 30,
  250: 30,
  251: 30,
  400: 30,
  401: 30,
  402: 17,
  403: 30,
  420: 20,
  421: 12,
  423: 15,
  424: 3,
  425: 15,
  426: 3,
}

// AI decimales: 3nnd / 39nd, donde el último dígito es la posición del punto
const DECIMAL_PREFIXES = ['310', '311', '312', '313', '314', '315', '316', '320', '321', '330', '331', '332', '333', '334', '335', '336', '337', '340', '341', '350', '356', '357', '360', '361', '364', '365', '390', '391', '392', '393']

const AI_NAMES = {
  '00': 'SSCC',
  '01': 'GTIN',
  10: 'Lote',
  11: 'F. producción',
  15: 'Consumo preferente',
  17: 'F. vencimiento',
  21: 'Serie',
  30: 'Cantidad',
  37: 'Cantidad',
  310: 'Peso neto (kg)',
  320: 'Peso neto (lb)',
}

/** ¿Este código parece GS1? */
export function looksLikeGs1(raw) {
  if (!raw) return false
  if (raw.includes(FNC1)) return true
  if (/^\(\d{2,4}\)/.test(raw)) return true
  // 01 + 14 dígitos es el caso más común sin FNC1 visible
  return /^(01|00)\d{14,}/.test(raw)
}

/** AAMMDD -> 'AAAA-MM-DD'. Día 00 = último día del mes (regla GS1). */
export function gs1Date(v) {
  if (!/^\d{6}$/.test(v)) return null
  const yy = Number(v.slice(0, 2))
  const year = yy >= 51 ? 1900 + yy : 2000 + yy
  const month = Number(v.slice(2, 4))
  let day = Number(v.slice(4, 6))
  if (month < 1 || month > 12) return null
  if (day === 0) day = new Date(year, month, 0).getDate()
  const p = (n) => String(n).padStart(2, '0')
  return `${year}-${p(month)}-${p(day)}`
}

/**
 * Parsea un código GS1.
 * @returns {{ais: Object, gtin?: string, sscc?: string, lot?: string,
 *             serial?: string, expiresAt?: string, producedAt?: string,
 *             qty?: number, weightKg?: number, raw: string}}
 */
export function parseGs1(raw) {
  const out = { ais: {}, raw }
  if (!raw) return out

  // Formato con paréntesis: (01)07501234567890(10)L2609A
  if (/^\(\d{2,4}\)/.test(raw)) {
    const re = /\((\d{2,4})\)([^(]*)/g
    let m
    while ((m = re.exec(raw)) !== null) out.ais[m[1]] = m[2]
    return hydrate(out)
  }

  let s = raw.replace(/^\]C1/, '') // símbolo identificador AIM
  let i = 0
  let guard = 0
  while (i < s.length && guard++ < 50) {
    if (s[i] === FNC1) {
      i++
      continue
    }
    const ai = matchAi(s, i)
    if (!ai) break // código desconocido: paramos y devolvemos lo que se pudo
    i += ai.length
    let value
    if (ai.fixed != null) {
      value = s.slice(i, i + ai.fixed)
      i += ai.fixed
    } else {
      const stop = s.indexOf(FNC1, i)
      const end = stop === -1 ? Math.min(s.length, i + (ai.max || 30)) : stop
      value = s.slice(i, end)
      i = end
    }
    out.ais[ai.code] = value
  }
  return hydrate(out)
}

function matchAi(s, i) {
  for (const len of [2, 3, 4]) {
    const code = s.slice(i, i + len)
    if (code.length < len) continue
    if (Object.prototype.hasOwnProperty.call(FIXED, code)) {
      return { code, length: len, fixed: FIXED[code] }
    }
    if (Object.prototype.hasOwnProperty.call(VARIABLE, code)) {
      return { code, length: len, max: VARIABLE[code] }
    }
    // AI decimales 310n..393n: 4 caracteres, valor de 6 dígitos
    if (len === 4 && DECIMAL_PREFIXES.includes(code.slice(0, 3)) && /^\d$/.test(code[3])) {
      return { code, length: 4, fixed: 6 }
    }
  }
  return null
}

function hydrate(out) {
  const a = out.ais
  if (a['01']) out.gtin = a['01']
  if (a['00']) out.sscc = a['00']
  if (a['10']) out.lot = a['10']
  if (a['21']) out.serial = a['21']
  if (a['17']) out.expiresAt = gs1Date(a['17'])
  if (a['11']) out.producedAt = gs1Date(a['11'])
  if (a['30']) out.qty = Number(a['30'])
  if (a['37']) out.qty = Number(a['37'])
  for (const k of Object.keys(a)) {
    if (k.length === 4 && DECIMAL_PREFIXES.includes(k.slice(0, 3))) {
      const decimals = Number(k[3])
      const value = Number(a[k]) / Math.pow(10, decimals)
      if (k.startsWith('310')) out.weightKg = value
      out.measure = { ai: k, value }
    }
  }
  return out
}

export function aiLabel(code) {
  return AI_NAMES[code] || AI_NAMES[code.slice(0, 3)] || `AI ${code}`
}
