<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import * as client from '../lib/client.js'
import { onScan } from '../lib/scanner.js'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'
import ScanTarget from '../components/ScanTarget.vue'

const props = defineProps({ itemId: [String, Number] })
const session = useSession()
const ui = useUi()
const item = ref(null)
const rows = ref([])
const scanned = ref('')
let off = null

onMounted(async () => {
  if (props.itemId) await loadById(Number(props.itemId))
  off = onScan(({ code }) => lookup(code))
})
onBeforeUnmount(() => off?.())

async function lookup(code) {
  scanned.value = code
  const res = await client.resolveScan(code, { warehouseId: session.warehouseId })
  if (res.item) await loadById(res.item.id)
  else ui.warn('Escanee un ítem')
}

async function loadById(id) {
  const all = await client.stockByItem(id, session.warehouseId)
  item.value = (await client.resolveScan(String(id))).item || all[0]?.item || null
  rows.value = await client.kardex({ item_id: id, warehouse_id: session.warehouseId })
  if (!item.value) {
    const cat = await client.stockByItem(id, session.warehouseId)
    item.value = cat[0]?.item || null
  }
}

const sign = (m) => (m.to_location_id && !m.from_location_id ? '+' : !m.to_location_id ? '−' : '↔')
const when = (iso) => new Date(iso).toLocaleString('es-BO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div class="app__body">
    <h1 class="title">Kardex</h1>
    <ScanTarget v-if="!item" label="un ÍTEM" :value="scanned" @scan="({ code }) => lookup(code)" />

    <template v-if="item">
      <div class="card">
        <p class="mono muted">{{ item.sku }}</p>
        <p class="name">{{ item.name }}</p>
      </div>
      <p v-if="!rows.length" class="muted">Sin movimientos registrados en este almacén.</p>
      <div v-for="m in rows" :key="m.id" class="mv">
        <span class="mv__sign" :class="sign(m) === '+' ? 'is-in' : sign(m) === '−' ? 'is-out' : 'is-mid'">{{ sign(m) }}</span>
        <div class="grow">
          <div class="row row--between">
            <span class="mv__type">{{ m.type.replace('_', ' ') }}</span>
            <span class="num">{{ m.qty }}</span>
          </div>
          <p class="mv__path mono">
            {{ m.from_location?.code || '—' }} → {{ m.to_location?.code || '—' }}
          </p>
          <p class="mv__meta">{{ when(m.occurred_at) }} · {{ m.user?.name || m.user || 'sistema' }}</p>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.name { margin: 2px 0 0; font-family: var(--font-ui); font-size: 16px; font-weight: 600; }
.mv { display: flex; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface); }
.mv__sign {
  width: 30px; height: 30px; flex-shrink: 0; display: grid; place-items: center;
  border-radius: 50%; font-family: var(--font-mono); font-weight: 700; font-size: 16px;
}
.is-in { background: var(--ok-soft); color: var(--ok); }
.is-out { background: var(--danger-soft); color: var(--danger); }
.is-mid { background: var(--surface-3); color: var(--text-muted); }
.mv__type { font-family: var(--font-ui); font-size: 14px; font-weight: 600; letter-spacing: 0.03em; }
.mv__path { margin: 2px 0 0; font-size: 12px; color: var(--text-muted); }
.mv__meta { margin: 0; font-size: 11px; color: var(--text-faint); }
</style>
