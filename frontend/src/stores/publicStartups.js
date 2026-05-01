import { defineStore } from 'pinia'
import { getJson } from '../lib/api'

// Public, anonymous, read-only view of the startup directory.
// Backed by /api/public/startup-listings, which serves only listings
// where content_status = published AND visibility = public, with the
// projection documented in backend/README.md ("Public Startup Listing
// API Shape"). This store deliberately knows nothing about review
// notes, ownership, or any other internal field.
//
// When the member dashboard's "my listings" or the admin queue lands,
// they should NOT extend this store. Add a sibling store
// (`useMyStartupsStore`, `useAdminStartupQueueStore`) that hits its
// own endpoint family. See `stores/README.md` for the rationale.

const LIST_TTL_MS = 60_000

export const usePublicStartupsStore = defineStore('publicStartups', {
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

    currentListing: null,
    currentListingId: null,
    currentLoading: false,
    currentError: null,
  }),
  getters: {
    /**
     * True when the cached list is fresher than LIST_TTL_MS and was
     * loaded with the same page+perPage parameters.
     */
    isListFresh: (state) => (page, perPage) => {
      if (state.listFetchedAt === 0) return false
      if (state.listMeta.currentPage !== page) return false
      if (state.listMeta.perPage !== perPage) return false
      return Date.now() - state.listFetchedAt < LIST_TTL_MS
    },
  },
  actions: {
    /**
     * Load a page of public listings. Honors a short in-memory TTL so
     * back-navigating from a detail page doesn't refetch.
     *
     * @param {object} [options]
     * @param {number} [options.page=1]
     * @param {number} [options.perPage=12] - capped server-side at 48
     * @param {boolean} [options.force=false] - bypass the TTL
     * @param {AbortSignal} [options.signal]
     */
    async loadList({ page = 1, perPage = 12, force = false, signal } = {}) {
      if (!force && this.isListFresh(page, perPage)) {
        return this.list
      }

      this.listLoading = true
      this.listError = null

      try {
        const response = await getJson('/api/public/startup-listings', {
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

    /**
     * Load a single public listing by id. If we already have it in
     * the cached list, that copy is surfaced immediately while the
     * fresh fetch resolves in the background.
     *
     * @param {string|number} id
     * @param {{ signal?: AbortSignal }} [options]
     */
    async loadOne(id, { signal } = {}) {
      const numericId = Number(id)

      // Optimistic display from the cached list, if available.
      const cached = this.list.find((item) => Number(item.id) === numericId) ?? null
      if (cached) {
        this.currentListing = cached
        this.currentListingId = numericId
      } else {
        this.currentListing = null
        this.currentListingId = numericId
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/public/startup-listings/${numericId}`, { signal })
        this.currentListing = response?.data ?? null
        return this.currentListing
      } catch (error) {
        this.currentError = error
        // 404 is a normal case (unpublished, hidden, members-only, or
        // unknown id) — leave currentListing null and let the page
        // render the "not found" branch.
        if (error?.status === 404) {
          this.currentListing = null
        }
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    /**
     * Used by tests and by future "I just submitted a new listing,
     * refresh the directory" flows on the member dashboard.
     */
    invalidate() {
      this.listFetchedAt = 0
    },
  },
})
