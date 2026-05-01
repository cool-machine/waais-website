import { defineStore } from 'pinia'
import { getJson } from '../lib/api'

// Public, anonymous, read-only homepage CMS cards.
// Backed by /api/public/homepage-cards, which serves only published,
// public-or-mixed records with the projection documented in backend/README.md.

const LIST_TTL_MS = 60_000

export const usePublicHomepageCardsStore = defineStore('publicHomepageCards', {
  state: () => ({
    list: [],
    listMeta: {
      currentPage: 1,
      lastPage: 1,
      perPage: 48,
      total: 0,
      section: '',
    },
    listFetchedAt: 0,
    listLoading: false,
    listError: null,
  }),
  getters: {
    isListFresh: (state) => (section, page, perPage) => {
      if (state.listFetchedAt === 0) return false
      if (state.listMeta.section !== section) return false
      if (state.listMeta.currentPage !== page) return false
      if (state.listMeta.perPage !== perPage) return false
      return Date.now() - state.listFetchedAt < LIST_TTL_MS
    },
    bySection: (state) => (section) => state.list.filter((card) => card.section === section),
  },
  actions: {
    async loadList({ section = '', page = 1, perPage = 48, force = false, signal } = {}) {
      if (!force && this.isListFresh(section, page, perPage)) {
        return this.list
      }

      this.listLoading = true
      this.listError = null

      try {
        const query = { page, per_page: perPage }
        if (section) query.section = section

        const response = await getJson('/api/public/homepage-cards', { query, signal })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = {
          currentPage: response?.current_page ?? page,
          lastPage: response?.last_page ?? 1,
          perPage: response?.per_page ?? perPage,
          total: response?.total ?? this.list.length,
          section,
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

    invalidate() {
      this.listFetchedAt = 0
    },
  },
})
