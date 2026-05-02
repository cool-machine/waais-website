import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

const DEFAULT_META = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
}

const ALL = 'all'

function paginationMeta(response) {
  return {
    currentPage: response?.current_page ?? 1,
    lastPage: response?.last_page ?? 1,
    perPage: response?.per_page ?? 25,
    total: response?.total ?? 0,
  }
}

export const useAdminAnnouncementsStore = defineStore('adminAnnouncements', {
  state: () => ({
    list: [],
    listMeta: { ...DEFAULT_META },
    filters: {
      content_status: 'draft',
      audience: ALL,
    },
    initialized: false,
    loading: false,
    error: null,

    currentAnnouncement: null,
    currentLoading: false,
    currentError: null,

    saving: false,
    saveError: null,
  }),
  getters: {
    hasAnnouncements: (state) => state.list.length > 0,
    selectedTitle: (state) => state.currentAnnouncement?.title || 'New announcement',
    isCreatingNew: (state) => !state.currentAnnouncement?.id,
  },
  actions: {
    async loadList({
      contentStatus = this.filters.content_status,
      audience = this.filters.audience,
      page = 1,
      perPage = 25,
      force = false,
      signal,
    } = {}) {
      const sameFilters = this.filters.content_status === contentStatus
        && this.filters.audience === audience

      if (
        this.initialized
        && !force
        && sameFilters
        && page === this.listMeta.currentPage
        && perPage === this.listMeta.perPage
      ) {
        return this.list
      }

      this.loading = true
      this.error = null
      this.filters = { content_status: contentStatus, audience }

      try {
        const response = await getJson('/api/admin/announcements', {
          auth: true,
          signal,
          query: {
            content_status: contentStatus && contentStatus !== ALL ? contentStatus : undefined,
            audience: audience && audience !== ALL ? audience : undefined,
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
        this.initialized = true

        if (this.currentAnnouncement?.id) {
          const refreshed = this.list.find((item) => Number(item.id) === Number(this.currentAnnouncement.id))
          if (refreshed) {
            this.currentAnnouncement = refreshed
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
        this.currentAnnouncement = null
        return null
      }

      const cached = this.list.find((item) => Number(item.id) === Number(id))
      if (cached) {
        this.currentAnnouncement = cached
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/admin/announcements/${id}`, { auth: true, signal })
        this.currentAnnouncement = response?.data ?? null
        return this.currentAnnouncement
      } catch (error) {
        this.currentError = error
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    selectAnnouncement(announcement) {
      this.currentAnnouncement = announcement ?? null
      this.currentError = null
      this.saveError = null
    },

    startNew() {
      this.currentAnnouncement = null
      this.currentError = null
      this.saveError = null
    },

    async save(payload, { signal } = {}) {
      this.saving = true
      this.saveError = null

      try {
        const isUpdate = Boolean(this.currentAnnouncement?.id)
        const path = isUpdate
          ? `/api/admin/announcements/${this.currentAnnouncement.id}`
          : '/api/admin/announcements'

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

    async transition(action, { signal } = {}) {
      if (!this.currentAnnouncement?.id) {
        return null
      }

      const paths = {
        publish: `/api/admin/announcements/${this.currentAnnouncement.id}/publish`,
        hide: `/api/admin/announcements/${this.currentAnnouncement.id}/hide`,
        archive: `/api/admin/announcements/${this.currentAnnouncement.id}/archive`,
      }
      const path = paths[action]
      if (!path) {
        throw new Error(`Unknown announcement transition: ${action}`)
      }

      this.saving = true
      this.saveError = null

      try {
        const response = await sendJson(path, { auth: true, signal, body: {} })
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
      const index = this.list.findIndex((item) => Number(item.id) === Number(updated.id))
      const matchesStatus = this.filters.content_status === ALL
        || updated.content_status === this.filters.content_status
      const matchesAudience = this.filters.audience === ALL
        || updated.audience === this.filters.audience
      const matchesFilter = matchesStatus && matchesAudience

      if (matchesFilter) {
        if (index === -1) {
          this.list = [updated, ...this.list]
          this.listMeta.total = (this.listMeta.total || 0) + 1
        } else {
          this.list.splice(index, 1, updated)
        }
        this.currentAnnouncement = updated
      } else if (index !== -1) {
        this.list.splice(index, 1)
        this.listMeta.total = Math.max(0, this.listMeta.total - 1)
        this.currentAnnouncement = this.list[0] ?? null
      } else {
        this.currentAnnouncement = updated
      }
    },

    publish(options) { return this.transition('publish', options) },
    hide(options) { return this.transition('hide', options) },
    archive(options) { return this.transition('archive', options) },

    clear() {
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.filters = {
        content_status: 'draft',
        audience: ALL,
      }
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentAnnouncement = null
      this.currentLoading = false
      this.currentError = null
      this.saving = false
      this.saveError = null
    },
  },
})
