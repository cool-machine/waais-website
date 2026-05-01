import { defineStore } from 'pinia'
import { ApiError, getJson, redirectToGoogleSignIn } from '../lib/api'

export const useAuthUserStore = defineStore('authUser', {
  state: () => ({
    user: null,
    initialized: false,
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    isPending: (state) => state.user?.permission_role === 'pending_user',
    isApproved: (state) => state.user?.approval_status === 'approved',
    canAccessMemberAreas: (state) => state.user?.can_access_member_areas === true,
    canPublishPublicContent: (state) => state.user?.can_publish_public_content === true,
    canManageAdminPrivileges: (state) => state.user?.can_manage_admin_privileges === true,
  },
  actions: {
    async loadCurrentUser({ force = false, signal } = {}) {
      if (this.initialized && !force) {
        return this.user
      }

      this.loading = true
      this.error = null

      try {
        this.user = await getJson('/api/user', { auth: true, signal })
        this.initialized = true
        return this.user
      } catch (error) {
        this.user = null
        this.initialized = true

        if (error instanceof ApiError && error.status === 401) {
          this.error = null
          return null
        }

        this.error = error
        throw error
      } finally {
        this.loading = false
      }
    },

    startGoogleSignIn(options) {
      redirectToGoogleSignIn(options)
    },

    clear() {
      this.user = null
      this.initialized = false
      this.loading = false
      this.error = null
    },
  },
})
