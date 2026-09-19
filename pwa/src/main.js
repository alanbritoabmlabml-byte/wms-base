import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import { installScanner } from './lib/scanner.js'
import './styles/base.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')

installScanner()

// Al recuperar señal, vaciamos la cola sin que el operador haga nada.
window.addEventListener('online', async () => {
  const { flushOutbox } = await import('./lib/client.js')
  flushOutbox().catch(() => {})
})
