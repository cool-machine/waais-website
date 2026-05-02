import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

export const useMembershipApplicationStore = defineStore('membershipApplication', {
  state: () => ({
    application: null,
    initialized: false,
    loading: false,
    saving: false,
    error: null,
    saveError: null,
  }),
  getters: {
    hasApplication: (state) => state.application !== null,
    status: (state) => state.application?.approval_status ?? null,
    canEdit: (state) => state.application?.approval_status !== 'approved',
    mustReapply: (state) => state.application?.approval_status === 'rejected',
    needsMoreInfo: (state) => state.application?.approval_status === 'needs_more_info',
  },
  actions: {
    async load({ force = false, signal } = {}) {
      if (this.initialized && !force) {
        return this.application
      }

      this.loading = true
      this.error = null

      try {
        const response = await getJson('/api/membership-application', { auth: true, signal })
        this.application = response?.data ?? null
        this.initialized = true
        return this.application
      } catch (error) {
        this.application = null
        this.initialized = true
        this.error = error
        throw error
      } finally {
        this.loading = false
      }
    },

    async save(payload, { signal } = {}) {
      this.saving = true
      this.saveError = null

      const hasExisting = this.application !== null
      const path = this.mustReapply
        ? '/api/membership-application/reapply'
        : '/api/membership-application'
      const method = this.mustReapply || !hasExisting ? 'POST' : 'PATCH'

      try {
        const response = await sendJson(path, {
          method,
          body: payload,
          auth: true,
          signal,
        })
        this.application = response?.data ?? null
        this.initialized = true
        return this.application
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    clear() {
      this.application = null
      this.initialized = false
      this.loading = false
      this.saving = false
      this.error = null
      this.saveError = null
    },
  },
})
