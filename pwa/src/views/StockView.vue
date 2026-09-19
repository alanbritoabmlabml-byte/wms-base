<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import * as client from '../lib/client.js'
import { onScan } from '../lib/scanner.js'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'
import ScanTarget from '../components/ScanTarget.vue'

const session = useSession()
const router = useRouter()
const ui = useUi()
const scanned = ref('')
const kind = ref(null)
const subject = ref(null)
const rows = ref([])
let off = null

onMounted(() => { off = onScan(({ code }) => lookup(code)) })
onBeforeUnmount(() => off?.())

async function lookup(code) {
  scanned.value = code
  const res = await client.resolveScan(code, { warehouseId: session.warehouseId })
  if (res.kind === 'LOCATION') {
    kind.value = 'LOCATION'
    subject.value = res.location
    rows.value = await client.stockByLocation(res.location.id)
  } else if (res.item) {
    kind.value = 'ITEM'
    subject.value = res.item
    rows.value = await client.stockByItem(res.item.id, session.warehouseId)
  } else {
    kind.value = null
    subject.value = null
    rows.value = []
    ui.warn(`Código no reconocido: ${code}`)
  }
}

const total = () => rows.value.reduce((a, r) => a + Number(r.qty), 0)
</script>

<template>
  <div class="app__body">
    <h1 class="title">Consulta de stock</h1>
    <ScanTarget
      label="una UBICACIÓN o un ÍTEM"
      :value="scanned"
      hint="El sistema reconoce solo el código: no hay que elegir el tipo."
      @scan="({ code }) => lookup(code)"
    />

    <template v-if="kind === 'LOCATION'">
      <div class="card">
        <p class="eyebrow">Ubicación</p>
        <p class="subject mono">{{ subject.code }}</p>
        <p class="muted">{{ subject.zone }} · {{ rows.length }} ítem(s)</p>
      </div>
      <div v-for="r in rows" :key="r.key || r.item_id + '-' + r.lot_id" class="row-card">
        <div class="row row--between">
          <span class="mono sku">{{ r.item?.sku }}</span>
          <span class="num qty">{{ r.qty }} <span class="muted u">{{ r.item?.base_uom }}</span></span>
        </div>
        <p class="name">{{ r.item?.name }}</p>
        <p v-if="r.lot && r.lot.code !== '-'" class="lot mono">
          Lote {{ r.lot.code }}<span v-if="r.lot.expires_at"> · vence {{ r.lot.expires_at }}</span>
        </p>
      </div>
      <p v-if="!rows.length" class="muted">Ubicación vacía.</p>
    </template>

    <template v-else-if="kind === 'ITEM'">
      <div class="card">
        <p class="eyebrow">Ítem</p>
        <p class="subject mono">{{ subject.sku }}</p>
        <p class="name">{{ subject.name }}</p>
        <p class="muted">Total en almacén: <span class="num">{{ total() }}</span> {{ subject.base_uom }}</p>
        <router-link class="btn btn--ghost btn--block" :to="`/kardex/${subject.id}`">Ver kardex</router-link>
      </div>
      <p class="eyebrow">Ubicaciones (orden PEPS / vencimiento)</p>
      <div v-for="r in rows" :key="r.key || r.location_id + '-' + r.lot_id" class="row-card">
        <div class="row row--between">
          <span class="mono sku">{{ r.location?.code }}</span>
          <span class="num qty">{{ r.qty }}</span>
        </div>
        <p v-if="r.lot && r.lot.code !== '-'" class="lot mono">
          Lote {{ r.lot.code }}<span v-if="r.lot.expires_at"> · vence {{ r.lot.expires_at }}</span>
        </p>
      </div>
      <p v-if="!rows.length" class="muted">Sin existencias en este almacén.</p>
    </template>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.subject { font-size: 20px; font-weight: 700; }
.row-card { padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface); }
.sku { font-size: 14px; color: var(--text-muted); }
.qty { font-size: 18px; }
.qty .u { font-size: 12px; font-weight: 500; }
.name { margin: 2px 0 0; font-family: var(--font-ui); font-size: 15px; font-weight: 600; }
.lot { margin: 2px 0 0; font-size: 12px; color: var(--text-faint); }
</style>
