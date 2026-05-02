import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

const DEFAULT_META = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
}

function paginationMeta(response) {
  return {
    currentPage: response?.current_page ?? 1,
    lastPage: response?.last_page ?? 1,
    perPage: response?.per_page ?? 25,
    total: response?.total ?? 0,
  }
}

export const useAdminStartupListingsStore = defineStore('adminStartupListings', {
  state: () => ({
    list: [],
    listMeta: { ...DEFAULT_META },
    listStatus: 'submitted',
    initialized: false,
    loading: false,
    error: null,

    currentListing: null,
    currentLoading: false,
    currentError: null,

    saving: false,
    saveError: null,
  }),
  getters: {
    hasListings: (state) => state.list.length > 0,
    selectedListingName: (state) => state.currentListing?.name || 'Startup listing',
  },
  actions: {
    async loadList({ status = this.listStatus, page = 1, perPage = 25, force = false, signal } = {}) {
      if (
        this.initialized
        && !force
        && status === this.listStatus
        && page === this.listMeta.currentPage
        && perPage === this.listMeta.perPage
      ) {
        return this.list
      }

      this.loading = true
      this.error = null
      this.listStatus = status

      try {
        const response = await getJson('/api/admin/startup-listings', {
          auth: true,
          signal,
          query: {
            status: status || undefined,
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
        this.initialized = true

        if (!this.currentListing && this.list.length > 0) {
          this.currentListing = this.list[0]
        }

        return this.list
      } catch (error) {
        this.error = error
        this.initialized = true
        throw error
      } finally {
        this.loading = false
      }
    },

    async loadOne(id, { signal } = {}) {
      if (!id) {
        this.currentListing = null
        return null
      }

      const cached = this.list.find((listing) => Number(listing.id) === Number(id))
      if (cached) {
        this.currentListing = cached
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/admin/startup-listings/${id}`, { auth: true, signal })
        this.currentListing = response?.data ?? null
        return this.currentListing
      } catch (error) {
        this.currentError = error
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    selectListing(listing) {
      this.currentListing = listing ?? null
      this.currentError = null
      this.saveError = null
    },

    async transition(action, payload = {}, { signal } = {}) {
      if (!this.currentListing?.id) {
        return null
      }

      const paths = {
        approve: `/api/admin/startup-listings/${this.currentListing.id}/approve`,
        reject: `/api/admin/startup-listings/${this.currentListing.id}/reject`,
        requestInfo: `/api/admin/startup-listings/${this.currentListing.id}/request-info`,
      }
      const path = paths[action]
      if (!path) {
        throw new Error(`Unknown startup listing transition: ${action}`)
      }

      this.saving = true
      this.saveError = null

      try {
        const response = await sendJson(path, {
          auth: true,
          signal,
          body: payload,
        })
        const updated = response?.data ?? null

        if (updated) {
          this.currentListing = updated
          const index = this.list.findIndex((listing) => Number(listing.id) === Number(updated.id))

          if (updated.approval_status === this.listStatus) {
            if (index === -1) {
              this.list = [updated, ...this.list]
            } else {
              this.list.splice(index, 1, updated)
            }
            this.currentListing = updated
          } else if (index !== -1) {
            this.list.splice(index, 1)
            this.listMeta.total = Math.max(0, this.listMeta.total - 1)
            this.currentListing = this.list[0] ?? null
          } else {
            this.currentListing = null
          }
        }

        return updated
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    approve(reviewNotes = '', options) {
      return this.transition('approve', {
        review_notes: reviewNotes.trim() || null,
      }, options)
    },

    reject(reviewNotes, sendEmail = false, options) {
      return this.transition('reject', {
        review_notes: reviewNotes,
        send_email: sendEmail,
      }, options)
    },

    requestInfo(reviewNotes, options) {
      return this.transition('requestInfo', {
        review_notes: reviewNotes,
      }, options)
    },

    clear() {
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.listStatus = 'submitted'
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentListing = null
      this.currentLoading = false
      this.currentError = null
      this.saving = false
      this.saveError = null
    },
  },
})
