import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

const DEFAULT_META = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
}

const ALL_CONTENT_STATUS = 'all'

function paginationMeta(response) {
  return {
    currentPage: response?.current_page ?? 1,
    lastPage: response?.last_page ?? 1,
    perPage: response?.per_page ?? 25,
    total: response?.total ?? 0,
  }
}

export const useAdminEventsStore = defineStore('adminEvents', {
  state: () => ({
    list: [],
    listMeta: { ...DEFAULT_META },
    listContentStatus: 'draft',
    listTime: 'all',
    initialized: false,
    loading: false,
    error: null,

    currentEvent: null,
    currentLoading: false,
    currentError: null,

    saving: false,
    saveError: null,
  }),
  getters: {
    hasEvents: (state) => state.list.length > 0,
    selectedEventTitle: (state) => state.currentEvent?.title || 'New event',
    isCreatingNew: (state) => !state.currentEvent?.id,
  },
  actions: {
    async loadList({
      contentStatus = this.listContentStatus,
      time = this.listTime,
      page = 1,
      perPage = 25,
      force = false,
      signal,
    } = {}) {
      if (
        this.initialized
        && !force
        && contentStatus === this.listContentStatus
        && time === this.listTime
        && page === this.listMeta.currentPage
        && perPage === this.listMeta.perPage
      ) {
        return this.list
      }

      this.loading = true
      this.error = null
      this.listContentStatus = contentStatus
      this.listTime = time

      try {
        const response = await getJson('/api/admin/events', {
          auth: true,
          signal,
          query: {
            content_status: contentStatus && contentStatus !== ALL_CONTENT_STATUS ? contentStatus : undefined,
            time: time || undefined,
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
        this.initialized = true

        if (this.currentEvent?.id) {
          const refreshed = this.list.find((event) => Number(event.id) === Number(this.currentEvent.id))
          if (refreshed) {
            this.currentEvent = refreshed
          }
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
        this.currentEvent = null
        return null
      }

      const cached = this.list.find((event) => Number(event.id) === Number(id))
      if (cached) {
        this.currentEvent = cached
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/admin/events/${id}`, { auth: true, signal })
        this.currentEvent = response?.data ?? null
        return this.currentEvent
      } catch (error) {
        this.currentError = error
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    selectEvent(event) {
      this.currentEvent = event ?? null
      this.currentError = null
      this.saveError = null
    },

    startNew() {
      this.currentEvent = null
      this.currentError = null
      this.saveError = null
    },

    async save(payload, { signal } = {}) {
      this.saving = true
      this.saveError = null

      try {
        const isUpdate = Boolean(this.currentEvent?.id)
        const path = isUpdate
          ? `/api/admin/events/${this.currentEvent.id}`
          : '/api/admin/events'

        const response = await sendJson(path, {
          auth: true,
          method: isUpdate ? 'PATCH' : 'POST',
          body: payload,
          signal,
        })

        const saved = response?.data ?? null
        if (saved) {
          this.applyUpdated(saved)
        }
        return saved
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    async transition(action, payload = {}, { signal } = {}) {
      if (!this.currentEvent?.id) {
        return null
      }

      const paths = {
        publish: `/api/admin/events/${this.currentEvent.id}/publish`,
        hide: `/api/admin/events/${this.currentEvent.id}/hide`,
        archive: `/api/admin/events/${this.currentEvent.id}/archive`,
        cancel: `/api/admin/events/${this.currentEvent.id}/cancel`,
      }
      const path = paths[action]
      if (!path) {
        throw new Error(`Unknown event transition: ${action}`)
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
          this.applyUpdated(updated)
        }
        return updated
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    applyUpdated(updated) {
      const index = this.list.findIndex((event) => Number(event.id) === Number(updated.id))
      const matchesFilter = this.listContentStatus === ALL_CONTENT_STATUS
        || updated.content_status === this.listContentStatus

      if (matchesFilter) {
        if (index === -1) {
          this.list = [updated, ...this.list]
          this.listMeta.total = (this.listMeta.total || 0) + 1
        } else {
          this.list.splice(index, 1, updated)
        }
        this.currentEvent = updated
      } else if (index !== -1) {
        this.list.splice(index, 1)
        this.listMeta.total = Math.max(0, this.listMeta.total - 1)
        this.currentEvent = this.list[0] ?? null
      } else {
        this.currentEvent = updated
      }
    },

    publish(options) {
      return this.transition('publish', {}, options)
    },

    hide(options) {
      return this.transition('hide', {}, options)
    },

    archive(options) {
      return this.transition('archive', {}, options)
    },

    cancel(cancellationNote = '', options) {
      const note = cancellationNote.trim()
      return this.transition('cancel', {
        cancellation_note: note === '' ? null : note,
      }, options)
    },

    clear() {
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.listContentStatus = 'draft'
      this.listTime = 'all'
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentEvent = null
      this.currentLoading = false
      this.currentError = null
      this.saving = false
      this.saveError = null
    },
  },
})
