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

export const useAdminUsersStore = defineStore('adminUsers', {
  state: () => ({
    list: [],
    listMeta: { ...DEFAULT_META },
    listFilters: {
      permission_role: ALL,
      approval_status: ALL,
      affiliation_type: ALL,
      q: '',
    },
    initialized: false,
    loading: false,
    error: null,

    currentUser: null,
    currentLoading: false,
    currentError: null,

    saving: false,
    saveError: null,
  }),
  getters: {
    hasUsers: (state) => state.list.length > 0,
    selectedUserName: (state) => state.currentUser?.name
      || state.currentUser?.email
      || 'User',
  },
  actions: {
    async loadList({
      permissionRole = this.listFilters.permission_role,
      approvalStatus = this.listFilters.approval_status,
      affiliationType = this.listFilters.affiliation_type,
      q = this.listFilters.q,
      page = 1,
      perPage = 25,
      force = false,
      signal,
    } = {}) {
      const filters = {
        permission_role: permissionRole,
        approval_status: approvalStatus,
        affiliation_type: affiliationType,
        q,
      }

      const sameFilters = Object.entries(filters).every(
        ([key, value]) => this.listFilters[key] === value,
      )

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
      this.listFilters = { ...filters }

      try {
        const response = await getJson('/api/admin/users', {
          auth: true,
          signal,
          query: {
            permission_role: permissionRole && permissionRole !== ALL ? permissionRole : undefined,
            approval_status: approvalStatus && approvalStatus !== ALL ? approvalStatus : undefined,
            affiliation_type: affiliationType && affiliationType !== ALL ? affiliationType : undefined,
            q: q && q.trim() !== '' ? q.trim() : undefined,
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
        this.initialized = true

        if (this.currentUser?.id) {
          const refreshed = this.list.find((user) => Number(user.id) === Number(this.currentUser.id))
          if (refreshed) {
            this.currentUser = refreshed
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
        this.currentUser = null
        return null
      }

      const cached = this.list.find((user) => Number(user.id) === Number(id))
      if (cached) {
        this.currentUser = cached
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/admin/users/${id}`, { auth: true, signal })
        this.currentUser = response?.data ?? null
        return this.currentUser
      } catch (error) {
        this.currentError = error
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    selectUser(user) {
      this.currentUser = user ?? null
      this.currentError = null
      this.saveError = null
    },

    async transition(action, { signal } = {}) {
      if (!this.currentUser?.id) {
        return null
      }

      const paths = {
        promoteAdmin: `/api/admin/users/${this.currentUser.id}/promote-admin`,
        demoteAdmin: `/api/admin/users/${this.currentUser.id}/demote-admin`,
        promoteSuperAdmin: `/api/admin/users/${this.currentUser.id}/promote-super-admin`,
        demoteSuperAdmin: `/api/admin/users/${this.currentUser.id}/demote-super-admin`,
      }
      const path = paths[action]
      if (!path) {
        throw new Error(`Unknown user transition: ${action}`)
      }

      this.saving = true
      this.saveError = null

      try {
        const response = await sendJson(path, { auth: true, signal, body: {} })
        const updates = response?.data ?? null
        if (updates && updates.id) {
          this.applyUpdates(updates)
        }
        return updates
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    applyUpdates(updates) {
      // The role-transition endpoints return a partial projection. Merge it
      // into the cached row + currentUser so we don't drop the wider listing
      // fields (avatar_url, approved_at, etc.).
      const merge = (target) => ({ ...target, ...updates })

      const index = this.list.findIndex((user) => Number(user.id) === Number(updates.id))
      const merged = index === -1
        ? { ...updates }
        : merge(this.list[index])

      const filterRole = this.listFilters.permission_role
      const matchesFilter = filterRole === ALL || merged.permission_role === filterRole

      if (matchesFilter) {
        if (index === -1) {
          this.list = [merged, ...this.list]
          this.listMeta.total = (this.listMeta.total || 0) + 1
        } else {
          this.list.splice(index, 1, merged)
        }
        this.currentUser = merged
      } else if (index !== -1) {
        this.list.splice(index, 1)
        this.listMeta.total = Math.max(0, this.listMeta.total - 1)
        this.currentUser = this.list[0] ?? null
      } else {
        this.currentUser = merged
      }
    },

    promoteAdmin(options) { return this.transition('promoteAdmin', options) },
    demoteAdmin(options) { return this.transition('demoteAdmin', options) },
    promoteSuperAdmin(options) { return this.transition('promoteSuperAdmin', options) },
    demoteSuperAdmin(options) { return this.transition('demoteSuperAdmin', options) },

    clear() {
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.listFilters = {
        permission_role: ALL,
        approval_status: ALL,
        affiliation_type: ALL,
        q: '',
      }
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentUser = null
      this.currentLoading = false
      this.currentError = null
      this.saving = false
      this.saveError = null
    },
  },
})
