import { defineStore } from 'pinia'
import * as client from '../lib/client.js'
import { getKv, setKv, delKv } from '../lib/db.js'

export const useSession = defineStore('session', {
  state: () => ({
    user: null,
    warehouses: [],
    warehouseId: null,
    permissions: [],
    ready: false,
    outbox: { pending: 0, failed: 0, done: 0 },
    catalog: { items: 0, locations: 0, syncedAt: null },
    online: true,
    demo: true,
  }),

  getters: {
    warehouse: (s) => s.warehouses.find((w) => w.id === s.warehouseId) || null,
    isAuthenticated: (s) => !!s.user,
    can: (s) => (perm) => s.permissions.includes(perm),
  },

  actions: {
    async boot() {
      await client.initClient()
      this.demo = client.isDemo()
      this.online = client.isOnline()
      const saved = await getKv('session', null)
      if (saved) {
        this.user = saved.user
        this.warehouses = saved.warehouses
        this.permissions = saved.permissions
        this.warehouseId = saved.warehouseId ?? null
      }
      await this.refreshCounters()
      this.ready = true
    },

    async login(credentials) {
      const res = await client.login(credentials)
      this.user = res.user
      this.warehouses = res.warehouses
      this.permissions = res.permissions
      this.warehouseId = res.warehouses.length === 1 ? res.warehouses[0].id : null
      await this.persist()
      return res
    },

    async selectWarehouse(id) {
      this.warehouseId = id
      await this.persist()
      try {
        await client.syncCatalog(id)
      } catch {
        /* sin señal: seguimos con la caché que ya haya */
      }
      await this.refreshCounters()
    },

    async persist() {
      await setKv('session', {
        user: this.user,
        warehouses: this.warehouses,
        permissions: this.permissions,
        warehouseId: this.warehouseId,
      })
    },

    async logout() {
      this.user = null
      this.warehouses = []
      this.warehouseId = null
      this.permissions = []
      await delKv('session')
      await client.setToken(null)
    },

    async refreshCounters() {
      const [outbox, catalog] = await Promise.all([client.outboxSummary(), client.catalogStats()])
      this.outbox = { pending: outbox.pending, failed: outbox.failed, done: outbox.done }
      this.catalog = catalog
      this.online = client.isOnline()
      this.demo = client.isDemo()
    },
  },
})
