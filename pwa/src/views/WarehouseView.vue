<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useSession } from '../stores/session.js'

const session = useSession()
const router = useRouter()
const busy = ref(null)

async function pick(w) {
  busy.value = w.id
  await session.selectWarehouse(w.id)
  busy.value = null
  router.replace('/menu')
}
</script>

<template>
  <div class="app__body">
    <h1 class="title">Seleccione almacén</h1>
    <p class="muted">Solo se muestran los almacenes asignados a su usuario.</p>
    <div class="stack">
      <button v-for="w in session.warehouses" :key="w.id" class="wh" type="button" :disabled="busy" @click="pick(w)">
        <span class="wh__code mono">{{ w.code }}</span>
        <span class="grow">
          <span class="wh__name truncate">{{ w.name }}</span>
          <span class="wh__branch">{{ w.branch }}</span>
        </span>
        <span class="chip chip--neutral">{{ w.role }}</span>
        <span v-if="busy === w.id" class="muted">…</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.wh {
  display: flex; align-items: center; gap: 10px; text-align: left;
  min-height: 64px; padding: 10px 12px;
  border: 1px solid var(--border); border-radius: var(--r-md);
  background: var(--surface);
}
.wh:active { background: var(--surface-2); }
.wh__code { font-size: 16px; font-weight: 700; color: var(--brand); min-width: 78px; }
.wh__name { display: block; font-family: var(--font-ui); font-size: 15px; font-weight: 600; }
.wh__branch { display: block; font-size: 12px; color: var(--text-muted); }
</style>
