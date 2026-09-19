<script setup>
import { useUi } from '../stores/ui.js'
const ui = useUi()
</script>

<template>
  <div class="toasts" role="status" aria-live="polite">
    <div v-for="t in ui.toasts" :key="t.id" class="toast" :class="`toast--${t.tone}`" @click="ui.dismiss(t.id)">
      {{ t.text }}
    </div>
  </div>
</template>

<style scoped>
.toasts {
  position: fixed;
  left: 50%;
  bottom: calc(80px + env(safe-area-inset-bottom, 0px));
  transform: translateX(-50%);
  z-index: 60;
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: min(92vw, 520px);
  pointer-events: none;
}
.toast {
  pointer-events: auto;
  padding: 12px 14px;
  border-radius: var(--r-md);
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow);
  font-family: var(--font-ui);
  font-size: 15px;
  font-weight: 600;
}
.toast--ok { border-color: var(--ok); color: var(--ok); }
.toast--warn { border-color: var(--warn); color: var(--warn); }
.toast--danger { border-color: var(--danger); color: var(--danger); }
</style>
