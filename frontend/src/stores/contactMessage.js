import { defineStore } from 'pinia'
import { sendJson } from '../lib/api'

// Anonymous contact-form submissions. POSTs to /api/contact, which emails
// the WAAIS team and stores a database copy.
export const useContactMessageStore = defineStore('contactMessage', {
  state: () => ({
    sending: false,
    sent: false,
    error: null,
  }),
  actions: {
    async send({ name, email, topic, message }, { signal } = {}) {
      this.sending = true
      this.sent = false
      this.error = null

      try {
        await sendJson('/api/contact', {
          method: 'POST',
          auth: true,
          body: { name, email, topic, message },
          signal,
        })
        this.sent = true
      } catch (error) {
        this.error = error
        throw error
      } finally {
        this.sending = false
      }
    },

    clear() {
      this.sending = false
      this.sent = false
      this.error = null
    },
  },
})
