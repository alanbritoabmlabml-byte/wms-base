<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import * as client from '../lib/client.js'
import { onScan } from '../lib/scanner.js'
import { useUi } from '../stores/ui.js'

const props = defineProps({ id: [String, Number] })
const router = useRouter()
const ui = useUi()
const rec = ref(null)
const loading = ref(true)
let off = null

onMounted(async () => {
  await load()
  // Escanear el ítem salta directo a su línea: el operador no busca en la lista.
  off = onScan(async ({ code }) => {
    const r = await client.resolveScan(code)
    if (r.kind === 'ITEM' || (r.kind === 'GS1' && r.item)) {
      const line = rec.value?.lines.find((l) => l.item_id === r.item.id && l.status !== 'COMPLETA')
        || rec.value?.lines.find((l) => l.item_id === r.item.id)
      if (line) return router.push(`/recepciones/${props.id}/linea/${line.id}?code=${encodeURIComponent(code)}`)
      ui.warn(`${r.item.sku} no figura en esta nota`)
    } else {
      ui.warn(`Código no reconocido: ${code}`)
    }
  })
})

onBeforeUnmount(() => off?.())

async function load() {
  loading.value = true
  try {
    rec.value = await client.receipt(props.id)
  } catch (e) {
    ui.error(e.message)
  } finally {
    loading.value = false
  }
}

const pending = computed(() => rec.value?.lines.filter((l) => l.status !== 'COMPLETA' && l.status !== 'EXCEDIDA').length ?? 0)
const tone = (s) => ({ PENDIENTE: 'neutral', PARCIAL: 'warn', COMPLETA: 'ok', EXCEDIDA: 'danger' })[s] || 'neutral'

async function cerrar() {
  try {
    await client.closeReceipt(props.id, {})
    ui.ok('Nota cerrada')
    router.replace('/recepciones')
  } catch (e) {
    if (e.code === 'RECEIPT_LINES_PENDING') {
      if (confirm(`${e.message}. ¿Cerrar de todas formas?`)) {
        await client.closeReceipt(props.id, { force: true })
        ui.warn('Nota cerrada con líneas incompletas')
        router.replace('/recepciones')
      }
    } else ui.error(e.message)
  }
}
</script>

<template>
  <div class="app__body" v-if="rec">
    <div class="head card">
      <div class="row row--between">
        <span class="head__num mono">{{ rec.number }}</span>
        <span class="chip chip--neutral">{{ rec.status.replace('_', ' ') }}</span>
      </div>
      <p class="muted">{{ rec.supplier_name }} · {{ rec.type.toLowerCase() }}</p>
    </div>

    <p class="muted hint">Escanee el ítem para ir directo a su línea.</p>

    <router-link
      v-for="l in rec.lines"
      :key="l.id"
      class="line"
      :to="`/recepciones/${rec.id}/linea/${l.id}`"
    >
      <div class="row row--between">
        <span class="line__sku mono">{{ l.item?.sku }}</span>
        <span class="chip" :class="`chip--${tone(l.status)}`">{{ l.status }}</span>
      </div>
      <p class="line__name">{{ l.item?.name }}</p>
      <div class="row row--between">
        <span class="line__qty">
          <span class="num">{{ l.qty_received }}</span>
          <span class="muted"> / {{ l.qty_expected }} {{ l.uom }}</span>
        </span>
        <span v-if="l.lot_code !== '-'" class="line__lot mono">Lote {{ l.lot_code }}</span>
      </div>
    </router-link>
  </div>

  <div class="actionbar" v-if="rec && rec.status !== 'CERRADA'">
    <button class="btn grow" type="button" @click="load">Actualizar</button>
    <button class="btn btn--primary grow" type="button" @click="cerrar">
      Cerrar nota<span v-if="pending"> ({{ pending }})</span>
    </button>
  </div>
</template>

<style scoped>
.head { display: flex; flex-direction: column; gap: 4px; }
.head__num { font-size: 20px; font-weight: 700; }
.hint { font-size: 13px; margin: 0; }
.line {
  display: flex; flex-direction: column; gap: 4px;
  padding: 10px 12px; border: 1px solid var(--border);
  border-radius: var(--r-md); background: var(--surface);
  color: var(--text); text-decoration: none;
}
.line:active { background: var(--surface-2); }
.line__sku { font-size: 13px; color: var(--text-muted); }
.line__name { margin: 0; font-family: var(--font-ui); font-size: 16px; font-weight: 600; }
.line__qty .num { font-size: 18px; }
.line__lot { font-size: 12px; color: var(--text-faint); }
</style>
