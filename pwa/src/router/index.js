import { createRouter, createWebHashHistory } from 'vue-router'
import { useSession } from '../stores/session.js'

// Hash history: la app tiene que abrir igual desde un servidor estático,
// desde GitHub Pages o desde un subdirectorio de la intranet.
const routes = [
  { path: '/', redirect: '/menu' },
  { path: '/login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
  { path: '/almacen', component: () => import('../views/WarehouseView.vue') },
  { path: '/menu', component: () => import('../views/MenuView.vue') },
  { path: '/recepciones', component: () => import('../views/ReceiptsView.vue') },
  { path: '/recepciones/:id', component: () => import('../views/ReceiptDetailView.vue'), props: true },
  { path: '/recepciones/:id/linea/:lineId', component: () => import('../views/ReceiveLineView.vue'), props: true },
  { path: '/reubicar', component: () => import('../views/RelocateView.vue') },
  { path: '/consulta', component: () => import('../views/StockView.vue') },
  { path: '/kardex/:itemId?', component: () => import('../views/KardexView.vue'), props: true },
  { path: '/cola', component: () => import('../views/SyncView.vue') },
  { path: '/ajustes', component: () => import('../views/SettingsView.vue'), meta: { public: true } },
  { path: '/:pathMatch(.*)*', redirect: '/menu' },
]

const router = createRouter({ history: createWebHashHistory(), routes })

router.beforeEach(async (to) => {
  const session = useSession()
  if (!session.ready) await session.boot()
  if (to.meta.public) return true
  if (!session.isAuthenticated) return '/login'
  if (!session.warehouseId && to.path !== '/almacen') return '/almacen'
  return true
})

export default router
