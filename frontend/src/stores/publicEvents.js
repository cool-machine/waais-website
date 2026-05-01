import { defineStore } from 'pinia'
import { getJson } from '../lib/api'

// Public, anonymous, read-only view of the events calendar.
// Backed by /api/public/events, which serves only published,
// public-or-mixed, non-cancelled events with the projection documented
// in backend/README.md ("Public Events API Shape").

const LIST_TTL_MS = 60_000

export const usePublicEventsStore = defineStore('publicEvents', {
  state: () => ({
    list: [],
    listMeta: {
      currentPage: 1,
      lastPage: 1,
      perPage: 12,
      total: 0,
      time: 'upcoming',
    },
    listFetchedAt: 0,
    listLoading: false,
    listError: null,

    currentEvent: null,
    currentEventId: null,
    currentLoading: false,
    currentError: null,
  }),
  getters: {
    isListFresh: (state) => (time, page, perPage) => {
      if (state.listFetchedAt === 0) return false
      if (state.listMeta.time !== time) return false
      if (state.listMeta.currentPage !== page) return false
      if (state.listMeta.perPage !== perPage) return false
      return Date.now() - state.listFetchedAt < LIST_TTL_MS
    },
  },
  actions: {
    async loadList({ time = 'upcoming', page = 1, perPage = 12, force = false, signal } = {}) {
      if (!force && this.isListFresh(time, page, perPage)) {
        return this.list
      }

      this.listLoading = true
      this.listError = null

      try {
        const response = await getJson('/api/public/events', {
          query: { time, page, per_page: perPage },
          signal,
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = {
          currentPage: response?.current_page ?? page,
          lastPage: response?.last_page ?? 1,
          perPage: response?.per_page ?? perPage,
          total: response?.total ?? this.list.length,
          time,
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
        this.currentEvent = cached
        this.currentEventId = numericId
      } else {
        this.currentEvent = null
        this.currentEventId = numericId
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/public/events/${numericId}`, { signal })
        this.currentEvent = response?.data ?? null
        return this.currentEvent
      } catch (error) {
        this.currentError = error
        if (error?.status === 404) {
          this.currentEvent = null
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
