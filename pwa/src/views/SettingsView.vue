<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import * as client from '../lib/client.js'
import { getKv } from '../lib/db.js'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'
import { setScannerEnabled } from '../lib/scanner.js'

const router = useRouter()
const session = useSession()
const ui = useUi()

const url = ref('')
const serial = ref('')
const theme = ref('auto')
const busy = ref(false)

onMounted(async () => {
  setScannerEnabled(false)
  url.value = await getKv('server_url', '')
  serial.value = await client.deviceSerial()
  theme.value = localStorage.getItem('wms-theme') || 'auto'
  applyTheme()
})

function applyTheme() {
  const root = document.documentElement
  if (theme.value === 'auto') root.removeAttribute('data-theme')
  else root.setAttribute('data-theme', theme.value)
  try {
    localStorage.setItem('wms-theme', theme.value)
  } catch {
    /* almacenamiento bloqueado: el tema vuelve a automático */
  }
}

async function guardar() {
  busy.value = true
  try {
    await client.setServerUrl(url.value)
    await session.logout()
    ui.ok(url.value ? 'Servidor configurado. Vuelva a iniciar sesión.' : 'Modo demostración activado')
    router.replace('/login')
  } finally {
    busy.value = false
  }
}

async function reiniciarDemo() {
  if (!confirm('Se borran los movimientos de la demo y vuelven los datos iniciales. ¿Continuar?')) return
  await client.resetDemoData()
  ui.ok('Datos de demostración reiniciados')
}
</script>

<template>
  <div class="app__body">
    <h1 class="title">Ajustes</h1>

    <div class="card stack">
      <p class="eyebrow">Servidor</p>
      <p class="muted note">
        Déjelo vacío para trabajar en modo demostración con datos locales. Con una URL
        (por ejemplo <code class="mono">http://192.0.0.26</code>) la app usa la API Laravel.
      </p>
      <input v-model="url" class="input" placeholder="http://192.0.0.26" inputmode="url" autocapitalize="none" spellcheck="false" />
      <button class="btn btn--primary btn--block" type="button" :disabled="busy" @click="guardar">Guardar y reiniciar sesión</button>
    </div>

    <div class="card stack">
      <p class="eyebrow">Dispositivo</p>
      <div class="row row--between"><span class="muted">Serie</span><span class="mono">{{ serial }}</span></div>
      <div class="row row--between"><span class="muted">Modo</span><span class="chip" :class="session.demo ? 'chip--brand' : 'chip--ok'">{{ session.demo ? 'DEMO' : 'SERVIDOR' }}</span></div>
      <div class="row row--between"><span class="muted">Maestros</span><span>{{ session.catalog.items }} ítems · {{ session.catalog.locations }} ubic.</span></div>
    </div>

    <div class="card stack">
      <p class="eyebrow">Apariencia</p>
      <div class="seg">
        <button v-for="t in ['auto', 'light', 'dark']" :key="t" type="button" class="seg__btn" :class="{ 'is-on': theme === t }" @click="theme = t; applyTheme()">
          {{ t === 'auto' ? 'Automático' : t === 'light' ? 'Claro' : 'Oscuro' }}
        </button>
      </div>
      <p class="muted note">El tema oscuro cansa menos la vista en pasillos con poca luz y alarga la batería del colector.</p>
    </div>

    <button v-if="session.demo" class="btn btn--danger btn--block" type="button" @click="reiniciarDemo">
      Reiniciar datos de demostración
    </button>
  </div>
</template>

<style scoped>
.title { font-size: 22px; font-weight: 600; }
.note { font-size: 13px; margin: 0; }
.note code { background: var(--surface-2); padding: 1px 5px; border-radius: 3px; }
.seg { display: flex; border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden; }
.seg__btn { flex: 1; min-height: 44px; border: 0; background: var(--surface); font-family: var(--font-ui); font-weight: 600; }
.seg__btn.is-on { background: var(--brand); color: var(--brand-ink); }
</style>
