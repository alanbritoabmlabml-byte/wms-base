<script setup>
/**
 * Recepción de una línea, paso a paso.
 *
 * La secuencia es ítem → cantidad → ubicación, y cada paso solo acepta lo que
 * le corresponde: escanear una ubicación cuando toca el ítem no hace nada más
 * que avisar. Suena rígido, pero es lo que evita el error clásico de registrar
 * el producto correcto en la ubicación de otro pallet.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as client from '../lib/client.js'
import { onScan, feedback } from '../lib/scanner.js'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'
import ScanTarget from '../components/ScanTarget.vue'
import NumPad from '../components/NumPad.vue'

const props = defineProps({ id: [String, Number], lineId: [String, Number] })
const route = useRoute()
const router = useRouter()
const session = useSession()
const ui = useUi()

const rec = ref(null)
const line = ref(null)
const step = ref('ITEM') // ITEM → QTY → LOCATION
const itemCode = ref('')
const qty = ref('')
const location = ref(null)
const lotCode = ref('')
const suggestions = ref([])
const posting = ref(false)
let off = null

const item = computed(() => line.value?.item || null)
const remaining = computed(() => Math.max(0, Number(line.value?.qty_expected || 0) - Number(line.value?.qty_received || 0)))
const decimals = computed(() => item.value?.decimals ?? 0)

onMounted(async () => {
  rec.value = await client.receipt(props.id)
  line.value = rec.value.lines.find((l) => String(l.id) === String(props.lineId))
  if (!line.value) {
    ui.error('Línea no encontrada')
    return router.back()
  }
  lotCode.value = line.value.lot_code || '-'
  qty.value = String(remaining.value || '')
  suggestions.value = await client.suggestLocations({
    item_id: line.value.item_id,
    warehouse_id: session.warehouseId,
  })

  // Si llegamos escaneando el ítem desde la nota, el primer paso ya está hecho.
  const pre = route.query.code
  if (pre) await handleScan(String(pre))

  off = onScan(({ code }) => handleScan(code))
})

onBeforeUnmount(() => off?.())

async function handleScan(code) {
  const res = await client.resolveScan(code, { warehouseId: session.warehouseId })

  if (step.value === 'ITEM') {
    const scanned = res.item
    if (!scanned) {
      feedback(false)
      return ui.warn(`Código no reconocido: ${code}`)
    }
    if (scanned.id !== line.value.item_id) {
      feedback(false)
      return ui.error(`Ese es ${scanned.sku}. Esta línea espera ${item.value.sku}`)
    }
    itemCode.value = code
    // Un código de "paquete" trae su propia cantidad; el GS1 puede traer lote.
    if (res.barcode?.qty_per_scan > 1) qty.value = String(res.barcode.qty_per_scan)
    if (res.lot_code) lotCode.value = res.lot_code
    step.value = 'QTY'
    return
  }

  if (step.value === 'QTY') {
    // Escanear la ubicación estando en cantidad = aceptar la cantidad sugerida.
    if (res.kind === 'LOCATION') {
      if (!Number(qty.value)) return ui.warn('Indique la cantidad primero')
      step.value = 'LOCATION'
      return applyLocation(res)
    }
    return ui.warn('Confirme la cantidad para continuar')
  }

  if (step.value === 'LOCATION') return applyLocation(res)
}

function applyLocation(res) {
  if (res.kind === 'LOCATION_OTHER_WAREHOUSE') {
    feedback(false)
    return ui.error(`${res.location.code} pertenece a otro almacén`)
  }
  if (res.kind !== 'LOCATION') {
    feedback(false)
    return ui.warn('Se esperaba una ubicación')
  }
  location.value = res.location
  registrar()
}

function pickSuggestion(s) {
  location.value = s.location
  registrar()
}

async function registrar() {
  if (posting.value) return
  const q = Number(qty.value)
  if (!(q > 0)) return ui.warn('Cantidad inválida')
  if (!location.value) return ui.warn('Falta la ubicación')

  posting.value = true
  try {
    const out = await client.submit({
      kind: 'RECEIPT_SCAN',
      label: `${item.value.sku} → ${location.value.code}`,
      payload: {
        receipt_id: Number(props.id),
        receipt_line_id: line.value.id,
        item_id: line.value.item_id,
        lot_code: lotCode.value,
        location_id: location.value.id,
        qty: q,
        status: 'BUENO',
        scanned_barcode: itemCode.value || item.value.sku,
        scanned_uom: item.value.base_uom,
        occurred_at: new Date().toISOString(),
      },
    })

    if (out.error) {
      feedback(false)
      ui.error(out.error.message)
      reset()
      return
    }

    feedback(true)
    if (out.queued) ui.warn(`Sin señal: ${q} ${item.value.base_uom} quedaron en cola`)
    else ui.ok(`${q} ${item.value.base_uom} en ${location.value.code}`)

    rec.value = await client.receipt(props.id)
    line.value = rec.value.lines.find((l) => String(l.id) === String(props.lineId))
    await session.refreshCounters()

    if (remaining.value <= 0) {
      ui.ok('Línea completa')
      return router.replace(`/recepciones/${props.id}`)
    }
    reset()
  } finally {
    posting.value = false
  }
}

function reset() {
  step.value = 'ITEM'
  itemCode.value = ''
  location.value = null
  qty.value = String(remaining.value || '')
}

const reasonLabel = { MISMO_ITEM: 'ya tiene este ítem', VACIA_CERCANA: 'vacía', OCUPADA: 'ocupada' }
</script>

<template>
  <div class="app__body" v-if="line">
    <div class="item card">
      <p class="item__sku mono">{{ item.sku }}</p>
      <h1 class="item__name">{{ item.name }}</h1>
      <div class="item__nums">
        <span><span class="num">{{ line.qty_received }}</span> recibido</span>
        <span><span class="num">{{ line.qty_expected }}</span> esperado</span>
        <span class="item__rest"><span class="num">{{ remaining }}</span> {{ item.base_uom }} por recibir</span>
      </div>
      <p v-if="lotCode && lotCode !== '-'" class="item__lot mono">Lote {{ lotCode }}</p>
    </div>

    <ScanTarget
      v-if="step === 'ITEM'"
      label="el ÍTEM"
      :value="itemCode"
      hint="Confirme que el producto del pallet es el de esta línea."
      @scan="({ code }) => handleScan(code)"
    />

    <template v-if="step === 'QTY'">
      <div class="steps">
        <span class="chip chip--ok">✓ {{ item.sku }}</span>
      </div>
      <p class="eyebrow">Cantidad en {{ item.base_uom }}</p>
      <NumPad v-model="qty" :decimals="decimals" :max="remaining" />
      <button class="btn btn--go btn--block" type="button" :disabled="!Number(qty)" @click="step = 'LOCATION'">
        Continuar a ubicación
      </button>
    </template>

    <template v-if="step === 'LOCATION'">
      <div class="steps">
        <span class="chip chip--ok">✓ {{ item.sku }}</span>
        <span class="chip chip--ok">✓ {{ qty }} {{ item.base_uom }}</span>
      </div>
      <ScanTarget
        label="la UBICACIÓN"
        :value="location?.code || ''"
        hint="Escanee la etiqueta del rack donde deja el pallet."
        @scan="({ code }) => handleScan(code)"
      />
      <div v-if="suggestions.length" class="sug">
        <p class="eyebrow">Ubicaciones sugeridas</p>
        <button v-for="s in suggestions" :key="s.location.id" class="sug__row" type="button" @click="pickSuggestion(s)">
          <span class="mono grow">{{ s.location.code }}</span>
          <span class="muted">{{ reasonLabel[s.reason] }}</span>
        </button>
      </div>
      <button class="btn btn--ghost btn--block" type="button" @click="step = 'QTY'">Volver a cantidad</button>
    </template>
  </div>
</template>

<style scoped>
.item { display: flex; flex-direction: column; gap: 4px; }
.item__sku { font-size: 13px; color: var(--text-muted); }
.item__name { font-size: 19px; font-weight: 600; line-height: 1.2; }
.item__nums {
  display: flex; flex-wrap: wrap; gap: 4px 14px;
  margin-top: 6px; font-size: 13px; color: var(--text-muted);
}
.item__nums .num { font-size: 16px; color: var(--text); }
.item__rest .num { color: var(--ok); }
.item__lot { font-size: 12px; color: var(--text-faint); }
.steps { display: flex; gap: 6px; flex-wrap: wrap; }
.sug { display: flex; flex-direction: column; gap: 6px; }
.sug__row {
  display: flex; align-items: center; gap: 8px;
  min-height: 48px; padding: 0 12px;
  border: 1px solid var(--border); border-radius: var(--r-md);
  background: var(--surface); font-size: 15px; text-align: left;
}
.sug__row:active { background: var(--surface-2); }
.sug__row .muted { font-size: 12px; }
</style>
