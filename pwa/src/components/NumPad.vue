<script setup>
/**
 * Teclado numérico propio.
 *
 * El teclado de Android tapa media pantalla, tarda en aparecer y en un colector
 * con guantes se falla mucho. Con teclas de 56 px el operador marca la cantidad
 * sin mirar. Los decimales solo se habilitan si la unidad los admite (KG sí,
 * BULTO no): así no se registran "2.5 bultos".
 */
import { ref, watch, computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  decimals: { type: Number, default: 0 },
  max: { type: Number, default: null },
})
const emit = defineEmits(['update:modelValue', 'submit'])

const raw = ref(String(props.modelValue ?? ''))
watch(
  () => props.modelValue,
  (v) => {
    if (String(v ?? '') !== raw.value) raw.value = String(v ?? '')
  }
)
watch(raw, (v) => emit('update:modelValue', v))

const overMax = computed(() => props.max != null && Number(raw.value || 0) > props.max)

function press(k) {
  if (k === '.') {
    if (props.decimals === 0 || raw.value.includes('.')) return
    raw.value = (raw.value || '0') + '.'
    return
  }
  if (raw.value === '0') raw.value = ''
  const next = raw.value + k
  const [, dec] = next.split('.')
  if (dec && dec.length > props.decimals) return
  if (next.replace('.', '').length > 9) return
  raw.value = next
}

function back() {
  raw.value = raw.value.slice(0, -1)
}
function clear() {
  raw.value = ''
}
</script>

<template>
  <div class="pad">
    <div class="pad__display" :class="{ 'pad__display--over': overMax }">
      <span class="num">{{ raw || '0' }}</span>
      <button class="pad__clear" type="button" aria-label="Borrar todo" @click="clear">C</button>
    </div>
    <p v-if="overMax" class="pad__warn">Supera lo esperado ({{ max }}). Se registrará como excedente.</p>
    <div class="pad__grid">
      <button v-for="k in ['1','2','3','4','5','6','7','8','9']" :key="k" type="button" class="pad__key" @click="press(k)">{{ k }}</button>
      <button type="button" class="pad__key" :disabled="decimals === 0" @click="press('.')">.</button>
      <button type="button" class="pad__key" @click="press('0')">0</button>
      <button type="button" class="pad__key pad__key--fn" aria-label="Borrar" @click="back">⌫</button>
    </div>
  </div>
</template>

<style scoped>
.pad { display: flex; flex-direction: column; gap: 8px; }
.pad__display {
  display: flex; align-items: center; justify-content: space-between;
  min-height: 60px; padding: 0 12px;
  border: 1px solid var(--border-strong); border-radius: var(--r-md);
  background: var(--surface);
}
.pad__display--over { border-color: var(--warn); background: var(--warn-soft); }
.pad__display .num { font-size: 32px; }
.pad__clear {
  min-width: 44px; height: 40px; border: 1px solid var(--border);
  border-radius: var(--r-sm); background: var(--surface-2);
  font-family: var(--font-ui); font-weight: 700;
}
.pad__warn { margin: 0; font-size: 13px; color: var(--warn); }
.pad__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.pad__key {
  min-height: 56px; border: 1px solid var(--border);
  border-radius: var(--r-md); background: var(--surface-2);
  font-family: var(--font-mono); font-size: 22px; font-weight: 700;
}
.pad__key:active { background: var(--surface-3); transform: scale(0.97); }
.pad__key:disabled { opacity: 0.3; }
.pad__key--fn { font-family: var(--font-body); font-size: 20px; }
</style>
