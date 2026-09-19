<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSession } from '../stores/session.js'
import { useUi } from '../stores/ui.js'
import { setScannerEnabled } from '../lib/scanner.js'
import * as client from '../lib/client.js'

const router = useRouter()
const session = useSession()
const ui = useUi()

const username = ref('amoscoso')
const password = ref('wms1234')
const busy = ref(false)
const demo = ref(true)

onMounted(async () => {
  setScannerEnabled(false) // el lector no debe interferir con el teclado
  if (!session.ready) await session.boot()
  demo.value = session.demo
})

async function submit() {
  busy.value = true
  try {
    const res = await session.login({ username: username.value, password: password.value })
    setScannerEnabled(true)
    router.replace(res.warehouses.length === 1 ? '/menu' : '/almacen')
  } catch (e) {
    ui.error(e.message || 'No se pudo iniciar sesión')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="app__body login">
    <div class="login__brand">
      <div class="login__mark">WMS</div>
      <h1 class="login__title">Terminal de almacén</h1>
      <p class="login__sub muted">Plásticos Carmen · versión {{ '0.1.0' }}</p>
    </div>

    <form class="stack" @submit.prevent="submit">
      <div class="field">
        <label class="eyebrow" for="u">Usuario</label>
        <input id="u" v-model="username" class="input" autocomplete="username" autocapitalize="none" spellcheck="false" />
      </div>
      <div class="field">
        <label class="eyebrow" for="p">Contraseña</label>
        <input id="p" v-model="password" type="password" class="input" autocomplete="current-password" />
      </div>
      <button class="btn btn--go btn--block" type="submit" :disabled="busy">
        {{ busy ? 'Ingresando…' : 'Ingresar' }}
      </button>
    </form>

    <div v-if="demo" class="card login__demo">
      <p class="eyebrow">Modo demostración</p>
      <p class="muted">
        Sin servidor configurado: la app corre con datos de prueba guardados en el propio
        dispositivo. Usuarios <code class="mono">amoscoso</code>, <code class="mono">operador1</code>
        o <code class="mono">admin</code>, contraseña <code class="mono">wms1234</code>.
      </p>
      <router-link class="btn btn--ghost btn--block" to="/ajustes">Conectar a un servidor</router-link>
    </div>
  </div>
</template>

<style scoped>
.login { justify-content: center; gap: 20px; }
.login__brand { text-align: center; display: flex; flex-direction: column; gap: 4px; align-items: center; }
.login__mark {
  font-family: var(--font-ui); font-size: 15px; font-weight: 700; letter-spacing: 0.22em;
  color: var(--brand-ink); background: var(--brand);
  padding: 8px 14px; border-radius: var(--r-sm);
}
.login__title { font-size: 26px; font-weight: 600; margin-top: 8px; }
.login__sub { font-size: 13px; }
.login__demo { display: flex; flex-direction: column; gap: 8px; font-size: 13px; }
.login__demo code { background: var(--surface-2); padding: 1px 5px; border-radius: 3px; }
</style>
