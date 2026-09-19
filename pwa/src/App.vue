<script setup>
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/AppHeader.vue'
import ToastHost from './components/ToastHost.vue'
import { useSession } from './stores/session.js'

const session = useSession()
const route = useRoute()

const showHeader = computed(() => route.path !== '/login' && session.isAuthenticated)

onMounted(async () => {
  if (!session.ready) await session.boot()
  setInterval(() => session.refreshCounters(), 5000)
})
</script>

<template>
  <div class="app">
    <AppHeader v-if="showHeader" />
    <router-view v-slot="{ Component }">
      <component :is="Component" />
    </router-view>
    <ToastHost />
  </div>
</template>
