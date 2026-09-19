<script setup>
/**
 * El componente que define la experiencia del colector.
 *
 * Siempre dice UNA cosa: qué se espera escanear ahora. El operador trabaja
 * mirando el pallet, no la pantalla; por eso el estado se comunica con color,
 * vibración y pitido, y el texto es corto y en mayúsculas.
 */
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { onScan, emitScan, refocus, cameraAvailable, startCamera, feedback } from '../lib/scanner.js'

const props = defineProps({
  label: { type: String, required: true }, // "UBICACIÓN", "ÍTEM"…
  hint: { type: String, default: '' },
  value: { type: String, default: '' }, // lo ya capturado
  tone: { type: String, default: 'scan' }, // scan | ok | danger
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['scan'])

const manual = ref(false)
const manualValue = ref('')
const manualInput = ref(null)
const camOpen = ref(false)
const videoEl = ref(null)
let stopCam = null
let off = null

const toneClass = computed(() => `scan--${props.tone}`)

onMounted(() => {
  off = onScan(({ code, source }) => {
    if (props.disabled) return
    emit('scan', { code, source })
  })
})

onBeforeUnmount(() => {
  off?.()
  stopCam?.()
})

function openManual() {
  manual.value = true
  manualValue.value = ''
  setTimeout(() => manualInput.value?.focus(), 50)
}

function submitManual() {
  const v = manualValue.value.trim()
  manual.value = false
  refocus()
  if (v) emitScan(v, 'MANUAL')
}

function cancelManual() {
  manual.value = false
  refocus()
}

async function openCamera() {
  camOpen.value = true
  await new Promise((r) => setTimeout(r, 60))
  try {
    stopCam = await startCamera(videoEl.value, (code) => {
      camOpen.value = false
      feedback(true)
      emitScan(code, 'CAMERA')
    })
  } catch (e) {
    camOpen.value = false
    alert(e.message)
  }
}

function closeCamera() {
  stopCam?.()
  camOpen.value = false
  refocus()
}
</script>

<template>
  <div class="scan" :class="[toneClass, { 'scan--filled': !!value, 'scan--off': disabled }]">
    <div class="scan__head">
      <span class="scan__beam" aria-hidden="true"></span>
      <span class="scan__label">{{ value ? label : `Escanee ${label}` }}</span>
    </div>

    <div class="scan__value mono">{{ value || '· · · · · ·' }}</div>
    <p v-if="hint" class="scan__hint">{{ hint }}</p>

    <div class="scan__tools">
      <button class="scan__tool" type="button" @click="openManual">Teclear</button>
      <button v-if="cameraAvailable()" class="scan__tool" type="button" @click="openCamera">Cámara</button>
    </div>

    <!-- Entrada manual: cuando la etiqueta está rota o ilegible -->
    <div v-if="manual" class="sheet" @click.self="cancelManual">
      <form class="sheet__panel" @submit.prevent="submitManual">
        <label class="eyebrow" for="manual-code">Ingrese el código de {{ label.toLowerCase() }}</label>
        <input
          id="manual-code"
          ref="manualInput"
          v-model="manualValue"
          class="input"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
        />
        <div class="row">
          <button type="button" class="btn grow" @click="cancelManual">Cancelar</button>
          <button type="submit" class="btn btn--primary grow">Aceptar</button>
        </div>
      </form>
    </div>

    <!-- Cámara: respaldo para probar en un celular sin lector -->
    <div v-if="camOpen" class="sheet sheet--full">
      <video ref="videoEl" class="cam" playsinline muted></video>
      <div class="cam__frame" aria-hidden="true"></div>
      <button class="btn btn--block cam__close" type="button" @click="closeCamera">Cerrar cámara</button>
    </div>
  </div>
</template>

<style scoped>
.scan {
  border: 2px dashed var(--scan);
  border-radius: var(--r-lg);
  background: var(--scan-soft);
  padding: 12px 14px;
  animation: pulse-scan 2.4s ease-in-out infinite;
}

.scan--filled {
  border-style: solid;
  animation: none;
}

.scan--ok {
  border-color: var(--ok);
  background: var(--ok-soft);
}

.scan--danger {
  border-color: var(--danger);
  background: var(--danger-soft);
}

.scan--off {
  opacity: 0.45;
  animation: none;
}

.scan__head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.scan__beam {
  width: 3px;
  height: 14px;
  border-radius: 2px;
  background: var(--scan);
}

.scan--ok .scan__beam {
  background: var(--ok);
}

.scan__label {
  font-family: var(--font-ui);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--scan);
}

.scan--ok .scan__label {
  color: var(--ok);
}

.scan__value {
  font-size: 22px;
  font-weight: 700;
  color: var(--text);
  margin-top: 2px;
  overflow-wrap: anywhere;
}

.scan__hint {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--text-muted);
}

.scan__tools {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

.scan__tool {
  flex: 1;
  min-height: 40px;
  border: 1px solid var(--border-strong);
  border-radius: var(--r-md);
  background: var(--surface);
  font-family: var(--font-ui);
  font-size: 14px;
  font-weight: 600;
}

/* Hojas modales */
.sheet {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(4, 10, 16, 0.6);
}

.sheet__panel {
  width: 100%;
  max-width: 560px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
  background: var(--surface);
  border-top-left-radius: var(--r-lg);
  border-top-right-radius: var(--r-lg);
}

.sheet--full {
  align-items: center;
  flex-direction: column;
  justify-content: center;
  padding: 16px;
  gap: 12px;
}

.cam {
  width: 100%;
  max-width: 480px;
  border-radius: var(--r-md);
  background: #000;
}

.cam__frame {
  position: fixed;
  top: 50%;
  left: 50%;
  width: min(70vw, 300px);
  height: 120px;
  transform: translate(-50%, -50%);
  border: 2px solid var(--scan);
  border-radius: 6px;
  pointer-events: none;
}

.cam__close {
  max-width: 480px;
  background: var(--surface);
}
</style>
