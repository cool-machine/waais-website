import { defineStore } from 'pinia'
import { getJson } from '../lib/api'

const DEFAULT_META = {
  currentPage: 1,
  lastPage: 1,
  perPage: 12,
  total: 0,
}

function paginationMeta(response) {
  return {
    currentPage: response?.current_page ?? 1,
    lastPage: response?.last_page ?? 1,
    perPage: response?.per_page ?? 12,
    total: response?.total ?? 0,
  }
}

export const useMemberAnnouncementsStore = defineStore('memberAnnouncements', {
  state: () => ({
    list: [],
    listMeta: { ...DEFAULT_META },
    initialized: false,
    loading: false,
    error: null,
  }),
  getters: {
    hasAnnouncements: (state) => state.list.length > 0,
    latest: (state) => state.list[0] ?? null,
  },
  actions: {
    async loadList({ page = 1, perPage = 12, force = false, signal } = {}) {
      if (
        this.initialized
        && !force
        && page === this.listMeta.currentPage
        && perPage === this.listMeta.perPage
      ) {
        return this.list
      }

      this.loading = true
      this.error = null

      try {
        const response = await getJson('/api/announcements', {
          auth: true,
          signal,
          query: {
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
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

    clear() {
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.initialized = false
      this.loading = false
      this.error = null
    },
  },
})
