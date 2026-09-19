<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useSession } from '../stores/session.js'
import * as client from '../lib/client.js'
import { onScan } from '../lib/scanner.js'
import { useRouter } from 'vue-router'
import { useUi } from '../stores/ui.js'

const session = useSession()
const router = useRouter()
const ui = useUi()
const rows = ref([])
const filter = ref('ABIERTAS')
const loading = ref(true)
let off = null

const visible = computed(() =>
  rows.value.filter((r) => (filter.value === 'ABIERTAS' ? r.status !== 'CERRADA' && r.status !== 'ANULADA' : true))
)

onMounted(async () => {
  await load()
  // Escanear el número de la nota la abre directo: un gesto en vez de tres.
  off = onScan(({ code }) => {
    const hit = rows.value.find((r) => r.number.toUpperCase() === code.toUpperCase())
    if (hit) router.push(`/recepciones/${hit.id}`)
    else ui.warn(`No hay nota ${code} en este almacén`)
  })
})

onBeforeUnmount(() => off?.())

async function load() {
  loading.value = true
  try {
    rows.value = await client.receipts({ warehouse_id: session.warehouseId })
  } catch (e) {
    ui.error(e.message)
  } finally {
    loading.value = false
  }
}

const progress = (r) => (r.lines_total ? Math.round((r.lines_done / r.lines_total) * 100) : 0)
const tone = (s) => (s === 'CERRADA' ? 'neutral' : s === 'EN_PROCESO' ? 'warn' : 'brand')
</script>

<template>
  <div class="app__body">
    <div class="row row--between">
      <h1 class="title">Notas de ingreso</h1>
      <button class="btn btn--ghost" type="button" @click="filter = filter === 'ABIERTAS' ? 'TODAS' : 'ABIERTAS'">
        {{ filter === 'ABIERTAS' ? 'Ver todas' : 'Solo abiertas' }}
      </button>
    </div>
    <p class="muted hint">Escanee el número de la nota o tóquela en la lista.</p>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="!visible.length" class="muted">No hay notas {{ filter === 'ABIERTAS' ? 'abiertas' : '' }} en este almacén.</p>

    <router-link v-for="r in visible" :key="r.id" class="rec" :to="`/recepciones/${r.id}`">
      <div class="row row--between">
        <span class="rec__num mono">{{ r.number }}</span>
        <span class="chip" :class="`chip--${tone(r.status)}`">{{ r.status.replace('_', ' ') }}</span>
      </div>
      <p class="rec__sup truncate">{{ r.supplier_name }}</p>
      <div class="rec__bar" :aria-label="`${r.lines_done} de ${r.lines_total} líneas`">
        <span :style="{ width: progress(r) + '%' }"></span>
      </div>
      <p class="rec__meta">{{ r.lines_done }} / {{ r.lines_total }} líneas · {{ r.type.toLowerCase() }}</p>
    </router-link>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.hint { font-size: 13px; margin: 0; }
.rec {
  display: flex; flex-direction: column; gap: 6px;
  padding: 12px; border: 1px solid var(--border); border-radius: var(--r-md);
  background: var(--surface); color: var(--text); text-decoration: none;
}
.rec:active { background: var(--surface-2); }
.rec__num { font-size: 18px; font-weight: 700; }
.rec__sup { margin: 0; font-size: 13px; color: var(--text-muted); }
.rec__bar { height: 4px; border-radius: 2px; background: var(--surface-3); overflow: hidden; }
.rec__bar span { display: block; height: 100%; background: var(--ok); }
.rec__meta { margin: 0; font-size: 12px; color: var(--text-faint); }
</style>
