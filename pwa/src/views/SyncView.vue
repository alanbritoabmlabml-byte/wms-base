<script setup>
import { ref, onMounted } from 'vue'
import * as client from '../lib/client.js'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'

const session = useSession()
const ui = useUi()
const summary = ref({ pending: 0, failed: 0, done: 0, rows: [] })
const busy = ref(false)

onMounted(load)

async function load() {
  summary.value = await client.outboxSummary()
}

async function flush() {
  busy.value = true
  try {
    const res = await client.flushOutbox()
    const ok = res.filter((r) => r.sent).length
    ui.ok(`${ok} de ${res.length} enviadas`)
  } finally {
    busy.value = false
    await load()
    await session.refreshCounters()
  }
}

async function retry(uuid) {
  await client.retryFailed(uuid)
  await load()
  await session.refreshCounters()
}

const tone = (s) => ({ PENDIENTE: 'warn', ENVIANDO: 'brand', ENVIADO: 'ok', ERROR: 'danger' })[s] || 'neutral'
const when = (iso) => new Date(iso).toLocaleString('es-BO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div class="app__body">
    <h1 class="title">Cola de sincronización</h1>
    <p class="muted hint">
      Lo que se escanea sin señal queda aquí y se envía solo al recuperar la red.
      Cada operación lleva un identificador único: reintentar nunca duplica stock.
    </p>

    <div class="stats">
      <div class="stat"><span class="num">{{ summary.pending }}</span><span>pendientes</span></div>
      <div class="stat"><span class="num stat--bad">{{ summary.failed }}</span><span>con error</span></div>
      <div class="stat"><span class="num stat--ok">{{ summary.done }}</span><span>enviadas</span></div>
    </div>

    <button class="btn btn--primary btn--block" type="button" :disabled="busy || !summary.pending" @click="flush">
      {{ busy ? 'Enviando…' : 'Enviar pendientes' }}
    </button>

    <p v-if="!summary.rows.length" class="muted">No hay operaciones registradas.</p>

    <div v-for="r in summary.rows.slice().reverse()" :key="r.client_uuid" class="op">
      <div class="row row--between">
        <span class="op__label truncate">{{ r.label || r.kind }}</span>
        <span class="chip" :class="`chip--${tone(r.status)}`">{{ r.status }}</span>
      </div>
      <p class="op__meta mono">{{ when(r.created_at) }} · {{ r.client_uuid.slice(0, 8) }}</p>
      <p v-if="r.error" class="op__err">{{ r.error.message }}</p>
      <button v-if="r.status === 'ERROR'" class="btn btn--ghost" type="button" @click="retry(r.client_uuid)">Reintentar</button>
    </div>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.hint { font-size: 13px; margin: 0; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.stat {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 12px 6px; border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface);
  font-size: 12px; color: var(--text-muted);
}
.stat .num { font-size: 24px; color: var(--text); }
.stat--ok { color: var(--ok); }
.stat--bad { color: var(--danger); }
.op { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface); }
.op__label { font-family: var(--font-ui); font-size: 15px; font-weight: 600; }
.op__meta { margin: 0; font-size: 11px; color: var(--text-faint); }
.op__err { margin: 0; font-size: 13px; color: var(--danger); }
</style>
