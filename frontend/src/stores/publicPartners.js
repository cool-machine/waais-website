import { defineStore } from 'pinia'
import { getJson } from '../lib/api'

// Public, anonymous, read-only view of partner profiles.
// Backed by /api/public/partners, which serves only published,
// public-or-mixed partners with the projection documented in
// backend/README.md ("Public Partners API Shape").

const LIST_TTL_MS = 60_000

export const usePublicPartnersStore = defineStore('publicPartners', {
  state: () => ({
    list: [],
    listMeta: {
      currentPage: 1,
      lastPage: 1,
      perPage: 12,
      total: 0,
    },
    listFetchedAt: 0,
    listLoading: false,
    listError: null,

    currentPartner: null,
    currentPartnerId: null,
    currentLoading: false,
    currentError: null,
  }),
  getters: {
    isListFresh: (state) => (page, perPage) => {
      if (state.listFetchedAt === 0) return false
      if (state.listMeta.currentPage !== page) return false
      if (state.listMeta.perPage !== perPage) return false
      return Date.now() - state.listFetchedAt < LIST_TTL_MS
    },
  },
  actions: {
    async loadList({ page = 1, perPage = 12, force = false, signal } = {}) {
      if (!force && this.isListFresh(page, perPage)) {
        return this.list
      }

      this.listLoading = true
      this.listError = null

      try {
        const response = await getJson('/api/public/partners', {
          query: { page, per_page: perPage },
          signal,
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = {
          currentPage: response?.current_page ?? page,
          lastPage: response?.last_page ?? 1,
          perPage: response?.per_page ?? perPage,
          total: response?.total ?? this.list.length,
        }
        this.listFetchedAt = Date.now()
        return this.list
      } catch (error) {
        this.listError = error
        throw error
      } finally {
        this.listLoading = false
      }
    },

    async loadOne(id, { signal } = {}) {
      const numericId = Number(id)

      const cached = this.list.find((item) => Number(item.id) === numericId) ?? null
      if (cached) {
        this.currentPartner = cached
        this.currentPartnerId = numericId
      } else {
        this.currentPartner = null
        this.currentPartnerId = numericId
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/public/partners/${numericId}`, { signal })
        this.currentPartner = response?.data ?? null
        return this.currentPartner
      } catch (error) {
        this.currentError = error
        if (error?.status === 404) {
          this.currentPartner = null
        }
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    invalidate() {
      this.listFetchedAt = 0
    },
  },
})
