/**
 * Motor de escaneo.
 *
 * En el colector Zebra, DataWedge está configurado en **modo Keystroke**: el
 * lector físico "teclea" el código y añade un ENTER (suffix \n). Para una PWA
 * esto es lo único que funciona sin envolver la app en un APK — el Intent
 * Output de DataWedge no llega a una página web.
 *
 * El truco: mantenemos un <input> invisible siempre enfocado. Los caracteres
 * de un escaneo llegan en ráfaga (< 30 ms entre teclas); los de un dedo humano,
 * no. Así distinguimos un escaneo real de una escritura manual y evitamos que
 * el teclado virtual robe el foco.
 *
 * Configuración requerida en DataWedge (perfil asociado a Chrome/la PWA):
 *   Keystroke output → Enabled
 *   Action key character → LF  (o CR)
 *   Basic data formatting → Send data: Enabled, Send ENTER key: Enabled
 *   Intent output → Disabled
 *   Ver docs/06-integracion-zebra.md
 */

const BURST_MS = 45 // separación máxima entre teclas para considerarlo ráfaga
const MIN_LENGTH = 2

let handlers = new Set()
let buffer = ''
let lastKeyAt = 0
let inputEl = null
let enabled = true
let audioCtx = null

/** Registra un callback para escaneos. Devuelve la función para quitarlo. */
export function onScan(fn) {
  handlers.add(fn)
  return () => handlers.delete(fn)
}

export function setScannerEnabled(v) {
  enabled = v
  if (v) refocus()
}

/** Emite un código como si viniera del lector (entrada manual o cámara). */
export function emitScan(code, source = 'MANUAL') {
  const value = String(code || '').trim()
  if (!value) return
  feedback(true)
  for (const fn of Array.from(handlers)) {
    try {
      fn({ code: value, source, at: new Date().toISOString() })
    } catch (e) {
      console.error('[scanner] handler falló', e)
    }
  }
}

/** Devuelve el foco al capturador. Llamar tras cerrar cualquier diálogo. */
export function refocus() {
  if (!inputEl || !enabled) return
  // Si el usuario está escribiendo en un campo real, no se lo quitamos.
  const active = document.activeElement
  if (active && active !== inputEl && active.matches?.('input,textarea,select')) return
  try {
    inputEl.focus({ preventScroll: true })
  } catch {
    /* noop */
  }
}

/** Instala el capturador global. Se llama una sola vez desde main.js. */
export function installScanner() {
  if (inputEl) return

  inputEl = document.createElement('input')
  inputEl.setAttribute('id', 'scan-sink')
  inputEl.setAttribute('aria-hidden', 'true')
  inputEl.setAttribute('autocomplete', 'off')
  inputEl.setAttribute('autocorrect', 'off')
  inputEl.setAttribute('autocapitalize', 'none')
  inputEl.setAttribute('spellcheck', 'false')
  // readonly evita que Android levante el teclado virtual, pero DataWedge
  // (que inyecta eventos de teclado a nivel de sistema) sigue llegando.
  inputEl.setAttribute('readonly', 'readonly')
  inputEl.setAttribute('inputmode', 'none')
  Object.assign(inputEl.style, {
    position: 'fixed',
    top: '-100px',
    left: '0',
    width: '1px',
    height: '1px',
    opacity: '0',
    border: '0',
    padding: '0',
    zIndex: '-1',
  })
  document.body.appendChild(inputEl)

  // Puente para un futuro APK/WebView: el código nativo que reciba el Intent
  // de DataWedge solo tiene que llamar a window.__wmsScan('<codigo>').
  window.__wmsScan = (code) => emitScan(code, 'NATIVE')

  document.addEventListener('keydown', onKeyDown, true)
  // Recuperamos el foco ante cualquier toque en la pantalla.
  document.addEventListener('click', () => setTimeout(refocus, 0), true)
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) setTimeout(refocus, 100)
  })
  window.addEventListener('focus', () => setTimeout(refocus, 100))
  setInterval(refocus, 1500) // red de seguridad: el colector pierde foco solo

  refocus()
}

function onKeyDown(e) {
  if (!enabled) return

  // Si el foco está en un campo real (cantidad manual, login), no interceptamos.
  const active = document.activeElement
  if (active && active !== inputEl && active.matches?.('input,textarea,select')) return

  const now = Date.now()
  const gap = now - lastKeyAt
  lastKeyAt = now

  if (e.key === 'Enter' || e.key === 'Tab') {
    const code = buffer
    buffer = ''
    if (code.length >= MIN_LENGTH) {
      e.preventDefault()
      e.stopPropagation()
      emitScan(code, 'SCANNER')
    }
    return
  }

  // Una pausa larga indica que empezó un código nuevo (o que es un humano).
  if (gap > 300) buffer = ''

  if (e.key.length === 1) {
    buffer += e.key
    e.preventDefault()
  } else if (e.key === 'Backspace') {
    buffer = buffer.slice(0, -1)
  }

  // Salvaguarda: algunos perfiles no mandan ENTER. Si la ráfaga se detiene
  // y hay algo en el buffer, lo emitimos igual.
  clearTimeout(onKeyDown._t)
  onKeyDown._t = setTimeout(() => {
    if (buffer.length >= MIN_LENGTH && Date.now() - lastKeyAt >= BURST_MS * 4) {
      const code = buffer
      buffer = ''
      emitScan(code, 'SCANNER')
    }
  }, BURST_MS * 5)
}

/* ------------------------------------------------------------------ *
 * Retroalimentación: pitido + vibración.
 * En una nave con ruido, el operador confía en la vibración más que en
 * la pantalla. Es la diferencia entre escanear mirando y escanear a ciegas.
 * ------------------------------------------------------------------ */
export function feedback(ok = true) {
  try {
    navigator.vibrate?.(ok ? 40 : [60, 50, 60])
  } catch {
    /* noop */
  }
  try {
    audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)()
    if (audioCtx.state === 'suspended') audioCtx.resume()
    const osc = audioCtx.createOscillator()
    const gain = audioCtx.createGain()
    osc.type = 'square'
    osc.frequency.value = ok ? 1400 : 320
    gain.gain.value = 0.04
    osc.connect(gain).connect(audioCtx.destination)
    osc.start()
    osc.stop(audioCtx.currentTime + (ok ? 0.06 : 0.22))
  } catch {
    /* el navegador puede bloquear audio hasta el primer toque */
  }
}

/* ------------------------------------------------------------------ *
 * Respaldo por cámara — para probar en un celular común (o si el lector
 * del colector falla). Usa la API nativa BarcodeDetector de Chrome Android;
 * si no existe, la opción simplemente no se ofrece.
 * ------------------------------------------------------------------ */
export function cameraAvailable() {
  return typeof window !== 'undefined' && 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia
}

export async function startCamera(videoEl, onCode) {
  if (!cameraAvailable()) throw new Error('Este navegador no tiene lector de cámara')
  const detector = new window.BarcodeDetector({
    formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'data_matrix', 'itf'],
  })
  const stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: 'environment', width: { ideal: 1280 } },
  })
  videoEl.srcObject = stream
  await videoEl.play()

  let stopped = false
  const tick = async () => {
    if (stopped) return
    try {
      const codes = await detector.detect(videoEl)
      if (codes.length) {
        onCode(codes[0].rawValue)
        stopped = true
        stop()
        return
      }
    } catch {
      /* frame no listo */
    }
    requestAnimationFrame(tick)
  }
  const stop = () => {
    stopped = true
    stream.getTracks().forEach((t) => t.stop())
    videoEl.srcObject = null
  }
  requestAnimationFrame(tick)
  return stop
}
