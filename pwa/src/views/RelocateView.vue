<script setup>
/**
 * Reubicación: origen → ítem → cantidad → destino.
 * Es el segundo proceso más usado después de la recepción y comparte el mismo
 * motor (client.submit), así que también funciona sin señal.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import * as client from '../lib/client.js'
import { onScan, feedback } from '../lib/scanner.js'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'
import ScanTarget from '../components/ScanTarget.vue'
import NumPad from '../components/NumPad.vue'

const session = useSession()
const ui = useUi()
const step = ref('FROM')
const from = ref(null)
const to = ref(null)
const item = ref(null)
const lot = ref(null)
const qty = ref('')
const stock = ref([])
const posting = ref(false)
let off = null

const available = computed(() => {
  const r = stock.value.find((s) => s.item_id === item.value?.id && (!lot.value || s.lot_id === lot.value.id))
  return Number(r?.qty || 0)
})

onMounted(() => { off = onScan(({ code }) => handle(code)) })
onBeforeUnmount(() => off?.())

async function handle(code) {
  const res = await client.resolveScan(code, { warehouseId: session.warehouseId })

  if (step.value === 'FROM') {
    if (res.kind !== 'LOCATION') { feedback(false); return ui.warn('Escanee la ubicación de origen') }
    from.value = res.location
    stock.value = await client.stockByLocation(res.location.id)
    if (!stock.value.length) { feedback(false); from.value = null; return ui.warn(`${res.location.code} está vacía`) }
    step.value = 'ITEM'
    return
  }

  if (step.value === 'ITEM') {
    if (!res.item) { feedback(false); return ui.warn('Escanee el ítem a mover') }
    const row = stock.value.find((s) => s.item_id === res.item.id)
    if (!row) { feedback(false); return ui.error(`${res.item.sku} no está en ${from.value.code}`) }
    item.value = res.item
    lot.value = row.lot
    qty.value = String(row.qty)
    step.value = 'QTY'
    return
  }

  if (step.value === 'QTY' || step.value === 'TO') {
    if (res.kind !== 'LOCATION') { feedback(false); return ui.warn('Escanee la ubicación destino') }
    if (res.location.id === from.value.id) { feedback(false); return ui.warn('El destino es igual al origen') }
    to.value = res.location
    registrar()
  }
}

function pickItem(row) {
  item.value = row.item
  lot.value = row.lot
  qty.value = String(row.qty)
  step.value = 'QTY'
}

async function registrar() {
  const q = Number(qty.value)
  if (!(q > 0)) return ui.warn('Cantidad inválida')
  if (q > available.value) { feedback(false); return ui.error(`Solo hay ${available.value} en ${from.value.code}`) }
  posting.value = true
  try {
    const out = await client.submit({
      kind: 'MOVEMENT',
      label: `${item.value.sku}: ${from.value.code} → ${to.value.code}`,
      payload: {
        type: 'REUBICACION',
        warehouse_id: session.warehouseId,
        item_id: item.value.id,
        lot_id: lot.value?.id,
        lot_code: lot.value?.code || '-',
        from_location_id: from.value.id,
        to_location_id: to.value.id,
        qty: q,
        status: 'BUENO',
        occurred_at: new Date().toISOString(),
      },
    })
    if (out.error) { feedback(false); ui.error(out.error.message); to.value = null; step.value = 'TO'; return }
    feedback(true)
    if (out.queued) ui.warn('Sin señal: el movimiento quedó en cola')
    else ui.ok(`${q} movidos a ${to.value.code}`)
    await session.refreshCounters()
    reset()
  } finally { posting.value = false }
}

function reset() {
  step.value = 'FROM'; from.value = null; to.value = null
  item.value = null; lot.value = null; qty.value = ''; stock.value = []
}
</script>

<template>
  <div class="app__body">
    <h1 class="title">Reubicar</h1>

    <div class="trail">
      <span class="chip" :class="from ? 'chip--ok' : 'chip--neutral'">{{ from?.code || 'Origen' }}</span>
      <span class="trail__arrow">→</span>
      <span class="chip" :class="item ? 'chip--ok' : 'chip--neutral'">{{ item?.sku || 'Ítem' }}</span>
      <span class="trail__arrow">→</span>
      <span class="chip" :class="to ? 'chip--ok' : 'chip--neutral'">{{ to?.code || 'Destino' }}</span>
    </div>

    <ScanTarget v-if="step === 'FROM'" label="la ubicación ORIGEN" :value="from?.code || ''" @scan="({ code }) => handle(code)" />

    <template v-if="step === 'ITEM'">
      <ScanTarget label="el ÍTEM" value="" hint="O toque uno de los que hay en esta ubicación." @scan="({ code }) => handle(code)" />
      <button v-for="s in stock" :key="s.item_id + '-' + s.lot_id" class="pick" type="button" @click="pickItem(s)">
        <span class="mono grow truncate">{{ s.item?.sku }} · {{ s.item?.name }}</span>
        <span class="num">{{ s.qty }}</span>
      </button>
    </template>

    <template v-if="step === 'QTY'">
      <p class="eyebrow">Cantidad a mover — disponible {{ available }} {{ item?.base_uom }}</p>
      <NumPad v-model="qty" :decimals="item?.decimals || 0" :max="available" />
      <button class="btn btn--go btn--block" type="button" :disabled="!Number(qty)" @click="step = 'TO'">Continuar a destino</button>
    </template>

    <ScanTarget v-if="step === 'TO'" label="la ubicación DESTINO" :value="to?.code || ''" @scan="({ code }) => handle(code)" />

    <button v-if="step !== 'FROM'" class="btn btn--ghost btn--block" type="button" @click="reset">Reiniciar</button>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.trail { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.trail__arrow { color: var(--text-faint); }
.pick {
  display: flex; align-items: center; gap: 8px; min-height: 52px; padding: 0 12px;
  border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface); text-align: left;
}
.pick:active { background: var(--surface-2); }
.pick .mono { font-size: 13px; }
</style>
