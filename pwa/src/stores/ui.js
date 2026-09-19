import { defineStore } from 'pinia'

let seq = 0

export const useUi = defineStore('ui', {
  state: () => ({ toasts: [] }),
  actions: {
    push(text, tone = 'ok', ms = 2600) {
      const id = ++seq
      this.toasts.push({ id, text, tone })
      setTimeout(() => this.dismiss(id), ms)
      return id
    },
    ok(text) { return this.push(text, 'ok') },
    warn(text) { return this.push(text, 'warn', 3400) },
    error(text) { return this.push(text, 'danger', 4200) },
    dismiss(id) { this.toasts = this.toasts.filter((t) => t.id !== id) },
  },
})
