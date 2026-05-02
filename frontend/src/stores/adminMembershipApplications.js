import { defineStore } from 'pinia'
import { getJson, sendJson } from '../lib/api'

const DEFAULT_META = {
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
}

function paginationMeta(response) {
  return {
    currentPage: response?.current_page ?? 1,
    lastPage: response?.last_page ?? 1,
    perPage: response?.per_page ?? 25,
    total: response?.total ?? 0,
  }
}

export const useAdminMembershipApplicationsStore = defineStore('adminMembershipApplications', {
  state: () => ({
    list: [],
    listMeta: { ...DEFAULT_META },
    listStatus: 'submitted',
    initialized: false,
    loading: false,
    error: null,

    currentApplication: null,
    currentLoading: false,
    currentError: null,

    saving: false,
    saveError: null,
  }),
  getters: {
    hasApplications: (state) => state.list.length > 0,
    selectedApplicantName: (state) => {
      const application = state.currentApplication
      if (!application) return ''
      const parts = [application.first_name, application.last_name].filter(Boolean)
      return parts.join(' ') || application.applicant?.name || application.email || 'Applicant'
    },
  },
  actions: {
    async loadList({ status = this.listStatus, page = 1, perPage = 25, force = false, signal } = {}) {
      if (
        this.initialized
        && !force
        && status === this.listStatus
        && page === this.listMeta.currentPage
        && perPage === this.listMeta.perPage
      ) {
        return this.list
      }

      this.loading = true
      this.error = null
      this.listStatus = status

      try {
        const response = await getJson('/api/admin/applications', {
          auth: true,
          signal,
          query: {
            status: status || undefined,
            page,
            per_page: perPage,
          },
        })

        this.list = Array.isArray(response?.data) ? response.data : []
        this.listMeta = paginationMeta(response)
        this.initialized = true

        if (!this.currentApplication && this.list.length > 0) {
          this.currentApplication = this.list[0]
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
        this.currentApplication = null
        return null
      }

      const cached = this.list.find((application) => Number(application.id) === Number(id))
      if (cached) {
        this.currentApplication = cached
      }

      this.currentLoading = true
      this.currentError = null

      try {
        const response = await getJson(`/api/admin/applications/${id}`, { auth: true, signal })
        this.currentApplication = response?.data ?? null
        return this.currentApplication
      } catch (error) {
        this.currentError = error
        throw error
      } finally {
        this.currentLoading = false
      }
    },

    selectApplication(application) {
      this.currentApplication = application ?? null
      this.currentError = null
      this.saveError = null
    },

    async transition(action, payload = {}, { signal } = {}) {
      if (!this.currentApplication?.id) {
        return null
      }

      const paths = {
        approve: `/api/admin/applications/${this.currentApplication.id}/approve`,
        reject: `/api/admin/applications/${this.currentApplication.id}/reject`,
        requestInfo: `/api/admin/applications/${this.currentApplication.id}/request-info`,
      }
      const path = paths[action]
      if (!path) {
        throw new Error(`Unknown membership application transition: ${action}`)
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
          this.currentApplication = updated
          const index = this.list.findIndex((application) => Number(application.id) === Number(updated.id))

          if (updated.approval_status === this.listStatus) {
            if (index === -1) {
              this.list = [updated, ...this.list]
            } else {
              this.list.splice(index, 1, updated)
            }
          } else if (index !== -1) {
            this.list.splice(index, 1)
            this.listMeta.total = Math.max(0, this.listMeta.total - 1)
          }
        }

        return updated
      } catch (error) {
        this.saveError = error
        throw error
      } finally {
        this.saving = false
      }
    },

    approve(reviewNotes = '', options) {
      return this.transition('approve', {
        review_notes: reviewNotes.trim() || null,
      }, options)
    },

    reject(reviewNotes, sendEmail = false, options) {
      return this.transition('reject', {
        review_notes: reviewNotes,
        send_email: sendEmail,
      }, options)
    },

    requestInfo(reviewNotes, options) {
      return this.transition('requestInfo', {
        review_notes: reviewNotes,
      }, options)
    },

    clear() {
      this.list = []
      this.listMeta = { ...DEFAULT_META }
      this.listStatus = 'submitted'
      this.initialized = false
      this.loading = false
      this.error = null
      this.currentApplication = null
      this.currentLoading = false
      this.currentError = null
      this.saving = false
      this.saveError = null
    },
  },
})
