import { defineStore } from 'pinia'
import { getJson } from '../lib/api'
import { readCache, writeCache } from '../lib/persistentCache'

// Public, anonymous AI/analytics news feed. Backed by /api/public/news, which
// aggregates official Penn/Wharton RSS feeds and ranks AI stories first.

const LIST_TTL_MS = 5 * 60_000

export const usePublicNewsStore = defineStore('publicNews', {
  state: () => ({
    list: [],
    listFetchedAt: 0,
    listLoading: false,
    listError: null,
  }),
  getters: {
    isListFresh: (state) =>
      state.listFetchedAt !== 0 && Date.now() - state.listFetchedAt < LIST_TTL_MS,
  },
  actions: {
    async loadList({ force = false, signal } = {}) {
      if (!force && this.isListFresh) {
        return this.list
      }

      // Paint last-seen news instantly on a cold load, then revalidate.
      if (this.list.length === 0) {
        const cached = readCache('news')
        if (cached) {
          this.list = cached.list
        }
      }

      this.listLoading = true
      this.listError = null

      try {
        const response = await getJson('/api/public/news', { signal })
        this.list = Array.isArray(response?.data) ? response.data : []
        this.listFetchedAt = Date.now()
        writeCache('news', { list: this.list })
        return this.list
      } catch (error) {
        this.listError = error
        throw error
      } finally {
        this.listLoading = false
      }
    },
  },
})
