import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

export const useMyStartupsStore = defineStore('myStartups', {
  state: () => ({
    list: [],
    initialized: false,
    loading: false,
    error: null,

    currentListing: null,
    saving: false,
    saveError: null,
  }),
  getters: {
    hasListings: (state) => state.list.length > 0,
    canEditCurrent: (state) => state.currentListing?.approval_status !== 'approved',
  },
  actions: {
    async loadList({ force = false, signal } = {}) {
      if (this.initialized && !force) {
        return this.list
      }

      this.loading = true
      this.error = null

      try {
        const response = await getJson('/api/startup-listings', { auth: true, signal })
        this.list = Array.isArray(response?.data) ? response.data : []
        this.initialized = true
        return this.list
      } catch (error) {
        this.error = error
        this.initialized = true
        throw error
      } finally {
        this.loading = false
      }
    },

    selectListing(listing) {
      this.currentListing = listing ?? null
      this.saveError = null
    },

    startNew() {
      this.currentListing = null
      this.saveError = null
    },

    async save(payload, { signal } = {}) {
      this.saving = true
      this.saveError = null

      const hasExisting = this.currentListing?.id
      const path = hasExisting
        ? `/api/startup-listings/${this.currentListing.id}`
        : '/api/startup-listings'
      const method = hasExisting ? 'PATCH' : 'POST'

      try {
        const response = await sendJson(path, {
          method,
          body: payload,
          auth: true,
          signal,
        })
        const saved = response?.data ?? null
        this.currentListing = saved
        this.initialized = true

        if (saved) {
          const index = this.list.findIndex((item) => Number(item.id) === Number(saved.id))
          if (index === -1) {
            this.list = [saved, ...this.list]
          } else {
            this.list.splice(index, 1, saved)
          }
        }

        return saved
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    clear() {
      this.list = []
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentListing = null
      this.saving = false
      this.saveError = null
    },
  },
})
