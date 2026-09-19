<script setup>
import { onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useSession } from '../stores/session.js'
import { setScannerEnabled } from '../lib/scanner.js'

const session = useSession()
const router = useRouter()

onMounted(() => {
  setScannerEnabled(true)
  session.refreshCounters()
})

const tiles = computed(() =>
  [
    { to: '/recepciones', label: 'Recepciones', sub: 'Notas de ingreso', icon: 'inbox', perm: 'receipt.scan' },
    { to: '/reubicar', label: 'Reubicar', sub: 'Mover entre ubicaciones', icon: 'move', perm: 'move.relocate' },
    { to: '/consulta', label: 'Consulta', sub: 'Stock por ubicación o ítem', icon: 'search', perm: 'stock.view' },
    { to: '/kardex', label: 'Kardex', sub: 'Historial de movimientos', icon: 'list', perm: 'stock.view' },
    { to: '/cola', label: 'Sincronización', sub: 'Pendientes por enviar', icon: 'sync', perm: null, badge: session.outbox.pending + session.outbox.failed },
    { to: '/ajustes', label: 'Ajustes', sub: 'Servidor y dispositivo', icon: 'cog', perm: null },
  ].filter((t) => !t.perm || session.can(t.perm))
)

async function salir() {
  await session.logout()
  router.replace('/login')
}
</script>

<template>
  <div class="app__body">
    <div class="ctx card">
      <div class="row row--between">
        <div>
          <p class="eyebrow">Almacén activo</p>
          <p class="ctx__wh mono">{{ session.warehouse?.code }}</p>
          <p class="muted ctx__name">{{ session.warehouse?.name }}</p>
        </div>
        <router-link class="btn btn--ghost" to="/almacen">Cambiar</router-link>
      </div>
      <div class="ctx__meta">
        <span>{{ session.catalog.items }} ítems</span>
        <span>{{ session.catalog.locations }} ubicaciones</span>
        <span v-if="session.demo" class="chip chip--brand">demo</span>
      </div>
    </div>

    <div class="grid">
      <router-link v-for="t in tiles" :key="t.to" class="tile" :to="t.to">
        <span class="tile__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <template v-if="t.icon === 'inbox'"><path d="M3 12h5l2 3h4l2-3h5" /><path d="M5 5h14l2 7v7H3v-7z" /></template>
            <template v-else-if="t.icon === 'move'"><path d="M5 9l-3 3 3 3" /><path d="M19 9l3 3-3 3" /><path d="M2 12h20" /><path d="M12 2v6M12 16v6" /></template>
            <template v-else-if="t.icon === 'search'"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></template>
            <template v-else-if="t.icon === 'list'"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></template>
            <template v-else-if="t.icon === 'sync'"><path d="M21 12a9 9 0 0 1-15 6.7L3 16" /><path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 4v4h-4M3 20v-4h4" /></template>
            <template v-else><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1" /></template>
          </svg>
        </span>
        <span class="tile__label">{{ t.label }}</span>
        <span class="tile__sub">{{ t.sub }}</span>
        <span v-if="t.badge" class="tile__badge num">{{ t.badge }}</span>
      </router-link>
    </div>

    <button class="btn btn--danger btn--block" type="button" @click="salir">Cerrar sesión</button>
  </div>
</template>

<style scoped>
.ctx { display: flex; flex-direction: column; gap: 10px; }
.ctx__wh { font-size: 24px; font-weight: 700; line-height: 1.1; }
.ctx__name { font-size: 13px; }
.ctx__meta { display: flex; gap: 10px; align-items: center; font-size: 12px; color: var(--text-faint); }

.grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }

.tile {
  position: relative;
  display: flex; flex-direction: column; gap: 2px;
  min-height: 104px; padding: 12px;
  border: 1px solid var(--border); border-radius: var(--r-md);
  background: var(--surface); color: var(--text); text-decoration: none;
}
.tile:active { background: var(--surface-2); transform: scale(0.985); }
.tile__icon { color: var(--brand); margin-bottom: 4px; }
.tile__label { font-family: var(--font-ui); font-size: 17px; font-weight: 600; }
.tile__sub { font-size: 12px; color: var(--text-muted); line-height: 1.25; }
.tile__badge {
  position: absolute; top: 10px; right: 10px;
  min-width: 26px; height: 24px; padding: 0 7px;
  display: grid; place-items: center;
  border-radius: 999px; background: var(--warn); color: #241802;
  font-size: 13px;
}
</style>
