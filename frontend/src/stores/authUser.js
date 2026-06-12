import { defineStore } from 'pinia'
import { ApiError, getJson, redirectToGoogleSignIn, sendJson } from '../lib/api'

export const useAuthUserStore = defineStore('authUser', {
  state: () => ({
    user: null,
    initialized: false,
    loading: false,
    signingOut: false,
    emailLinkSending: false,
    emailLinkSent: false,
    emailLinkError: null,
    registering: false,
    registered: false,
    registerError: null,
    loggingIn: false,
    loginError: null,
    resendingVerification: false,
    verificationResent: false,
    passwordResetRequesting: false,
    passwordResetRequested: false,
    passwordResetting: false,
    passwordResetError: null,
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

    async requestEmailSignIn(email, { next = '/membership', signal } = {}) {
      this.emailLinkSending = true
      this.emailLinkSent = false
      this.emailLinkError = null

      try {
        await sendJson('/api/auth/email-link', {
          method: 'POST',
          body: { email, next },
          signal,
        })
        this.emailLinkSent = true
      } catch (error) {
        this.emailLinkError = error
        throw error
      } finally {
        this.emailLinkSending = false
      }
    },

    async register(payload, { signal } = {}) {
      this.registering = true
      this.registered = false
      this.registerError = null

      try {
        await sendJson('/api/auth/register', {
          method: 'POST',
          auth: true,
          body: payload,
          signal,
        })
        this.registered = true
      } catch (error) {
        this.registerError = error
        throw error
      } finally {
        this.registering = false
      }
    },

    async login({ email, password }, { signal } = {}) {
      this.loggingIn = true
      this.loginError = null

      try {
        await sendJson('/api/auth/login', {
          method: 'POST',
          body: { email, password },
          auth: true,
          signal,
        })
        await this.loadCurrentUser({ force: true, signal })
      } catch (error) {
        this.loginError = error
        throw error
      } finally {
        this.loggingIn = false
      }
    },

    async requestPasswordReset(email, { signal } = {}) {
      this.passwordResetRequesting = true
      this.passwordResetRequested = false

      try {
        await sendJson('/api/auth/forgot-password', {
          method: 'POST',
          auth: true,
          body: { email },
          signal,
        })
        this.passwordResetRequested = true
      } finally {
        this.passwordResetRequesting = false
      }
    },

    async resetPassword({ email, token, password, password_confirmation }, { signal } = {}) {
      this.passwordResetting = true
      this.passwordResetError = null

      try {
        await sendJson('/api/auth/reset-password', {
          method: 'POST',
          auth: true,
          body: { email, token, password, password_confirmation },
          signal,
        })
      } catch (error) {
        this.passwordResetError = error
        throw error
      } finally {
        this.passwordResetting = false
      }
    },

    async resendVerification(email, { signal } = {}) {
      this.resendingVerification = true
      this.verificationResent = false

      try {
        await sendJson('/api/auth/resend-verification', {
          method: 'POST',
          auth: true,
          body: { email },
          signal,
        })
        this.verificationResent = true
      } finally {
        this.resendingVerification = false
      }
    },

    async signOut({ signal } = {}) {
      this.signingOut = true
      this.error = null

      try {
        await sendJson('/api/logout', {
          method: 'POST',
          auth: true,
          signal,
        })
        this.user = null
        this.initialized = true
      } catch (error) {
        this.error = error
        throw error
      } finally {
        this.signingOut = false
      }
    },

    clear() {
      this.user = null
      this.initialized = false
      this.loading = false
      this.signingOut = false
      this.emailLinkSending = false
      this.emailLinkSent = false
      this.emailLinkError = null
      this.registering = false
      this.registered = false
      this.registerError = null
      this.loggingIn = false
      this.loginError = null
      this.resendingVerification = false
      this.verificationResent = false
      this.passwordResetRequesting = false
      this.passwordResetRequested = false
      this.passwordResetting = false
      this.passwordResetError = null
      this.error = null
    },
  },
})
