<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSession } from '../stores/session.js'

const session = useSession()
const route = useRouter()
const current = useRoute()

const canGoBack = computed(() => current.path !== '/menu')
const pendiente = computed(() => session.outbox.pending + session.outbox.failed)
</script>

<template>
  <header class="hdr">
    <button v-if="canGoBack" class="hdr__back" aria-label="Volver" @click="route.back()">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>
    <div v-else class="hdr__mark" aria-hidden="true">WMS</div>

    <div class="hdr__ctx grow truncate">
      <span class="hdr__wh mono">{{ session.warehouse?.code || '—' }}</span>
      <span class="hdr__user truncate">{{ session.user?.name }}</span>
    </div>

    <router-link to="/cola" class="hdr__status" :class="{ 'hdr__status--alert': pendiente > 0 }">
      <span class="hdr__dot" :class="session.online ? 'is-online' : 'is-offline'" aria-hidden="true"></span>
      <span class="num">{{ pendiente }}</span>
      <span class="sr">operaciones en cola</span>
    </router-link>
  </header>
</template>

<style scoped>
.hdr {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  height: var(--header-h);
  padding: 0 8px 0 4px;
  padding-top: env(safe-area-inset-top, 0px);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}

.hdr__back,
.hdr__mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border: 0;
  background: transparent;
  color: var(--text);
  border-radius: var(--r-md);
}

.hdr__mark {
  font-family: var(--font-ui);
  font-weight: 700;
  font-size: 13px;
  letter-spacing: 0.08em;
  color: var(--brand);
}

.hdr__back:active {
  background: var(--surface-2);
}

.hdr__ctx {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}

.hdr__wh {
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
}

.hdr__user {
  font-size: 12px;
  color: var(--text-muted);
}

.hdr__status {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 52px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface-2);
  color: var(--text-muted);
  font-size: 13px;
  text-decoration: none;
}

.hdr__status--alert {
  border-color: var(--warn);
  color: var(--warn);
  background: var(--warn-soft);
}

.hdr__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.is-online {
  background: var(--ok);
}

.is-offline {
  background: var(--danger);
}

.sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
