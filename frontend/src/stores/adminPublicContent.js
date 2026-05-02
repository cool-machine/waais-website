import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

const DEFAULT_META = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
}

const ALL = 'all'

const RESOURCES = {
  homepage_cards: {
    label: 'Homepage cards',
    singularLabel: 'Homepage card',
    path: '/api/admin/homepage-cards',
    titleField: 'title',
    defaultStatus: 'draft',
  },
  partners: {
    label: 'Partners',
    singularLabel: 'Partner',
    path: '/api/admin/partners',
    titleField: 'name',
    defaultStatus: 'draft',
  },
}

function paginationMeta(response) {
  return {
    currentPage: response?.current_page ?? 1,
    lastPage: response?.last_page ?? 1,
    perPage: response?.per_page ?? 25,
    total: response?.total ?? 0,
  }
}

function configFor(resource) {
  return RESOURCES[resource] ?? RESOURCES.homepage_cards
}

export const useAdminPublicContentStore = defineStore('adminPublicContent', {
  state: () => ({
    resource: 'homepage_cards',
    list: [],
    listMeta: { ...DEFAULT_META },
    filters: {
      content_status: 'draft',
      visibility: ALL,
    },
    initialized: false,
    loading: false,
    error: null,

    currentItem: null,
    currentLoading: false,
    currentError: null,

    saving: false,
    saveError: null,
  }),
  getters: {
    hasItems: (state) => state.list.length > 0,
    resourceConfig: (state) => configFor(state.resource),
    selectedTitle: (state) => {
      const config = configFor(state.resource)
      return state.currentItem?.[config.titleField] || `New ${config.singularLabel.toLowerCase()}`
    },
    isCreatingNew: (state) => !state.currentItem?.id,
  },
  actions: {
    async loadList({
      resource = this.resource,
      contentStatus = this.filters.content_status,
      visibility = this.filters.visibility,
      page = 1,
      perPage = 25,
      force = false,
      signal,
    } = {}) {
      const nextResource = RESOURCES[resource] ? resource : 'homepage_cards'
      const sameFilters = this.resource === nextResource
        && this.filters.content_status === contentStatus
        && this.filters.visibility === visibility

      if (
        this.initialized
        && !force
        && sameFilters
        && page === this.listMeta.currentPage
        && perPage === this.listMeta.perPage
      ) {
        return this.list
      }

      if (this.resource !== nextResource) {
        this.currentItem = null
        this.currentError = null
        this.saveError = null
      }

      this.resource = nextResource
      this.filters = { content_status: contentStatus, visibility }
      this.loading = true
      this.error = null

      try {
        const response = await getJson(configFor(nextResource).path, {
          auth: true,
          signal,
          query: {
            content_status: contentStatus && contentStatus !== ALL ? contentStatus : undefined,
            visibility: visibility && visibility !== ALL ? visibility : undefined,
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
        this.initialized = true

        if (this.currentItem?.id) {
          const refreshed = this.list.find((item) => Number(item.id) === Number(this.currentItem.id))
          if (refreshed) {
            this.currentItem = refreshed
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
        this.currentItem = null
        return null
      }

      const cached = this.list.find((item) => Number(item.id) === Number(id))
      if (cached) {
        this.currentItem = cached
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`${configFor(this.resource).path}/${id}`, { auth: true, signal })
        this.currentItem = response?.data ?? null
        return this.currentItem
      } catch (error) {
        this.currentError = error
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    selectItem(item) {
      this.currentItem = item ?? null
      this.currentError = null
      this.saveError = null
    },

    startNew() {
      this.currentItem = null
      this.currentError = null
      this.saveError = null
    },

    async save(payload, { signal } = {}) {
      this.saving = true
      this.saveError = null

      try {
        const isUpdate = Boolean(this.currentItem?.id)
        const path = isUpdate
          ? `${configFor(this.resource).path}/${this.currentItem.id}`
          : configFor(this.resource).path

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
      if (!this.currentItem?.id) {
        return null
      }

      const paths = {
        publish: `${configFor(this.resource).path}/${this.currentItem.id}/publish`,
        hide: `${configFor(this.resource).path}/${this.currentItem.id}/hide`,
        archive: `${configFor(this.resource).path}/${this.currentItem.id}/archive`,
      }
      const path = paths[action]
      if (!path) {
        throw new Error(`Unknown public content transition: ${action}`)
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
      const matchesVisibility = this.filters.visibility === ALL
        || updated.visibility === this.filters.visibility
      const matchesFilter = matchesStatus && matchesVisibility

      if (matchesFilter) {
        if (index === -1) {
          this.list = [updated, ...this.list]
          this.listMeta.total = (this.listMeta.total || 0) + 1
        } else {
          this.list.splice(index, 1, updated)
        }
        this.currentItem = updated
      } else if (index !== -1) {
        this.list.splice(index, 1)
        this.listMeta.total = Math.max(0, this.listMeta.total - 1)
        this.currentItem = this.list[0] ?? null
      } else {
        this.currentItem = updated
      }
    },

    publish(options) { return this.transition('publish', options) },
    hide(options) { return this.transition('hide', options) },
    archive(options) { return this.transition('archive', options) },

    clear() {
      this.resource = 'homepage_cards'
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.filters = {
        content_status: 'draft',
        visibility: ALL,
      }
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentItem = null
      this.currentLoading = false
      this.currentError = null
      this.saving = false
      this.saveError = null
    },
  },
})
