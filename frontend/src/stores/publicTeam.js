import { defineStore } from 'pinia'
import { getJson } from '../lib/api'
import { readCache, writeCache } from '../lib/persistentCache'

// Public, anonymous view of the founders + board advisors shown on the About
// page. Backed by /api/public/team-members.

const LIST_TTL_MS = 5 * 60_000

export const usePublicTeamStore = defineStore('publicTeam', {
  state: () => ({
    list: [],
    listFetchedAt: 0,
    listLoading: false,
    listError: null,
  }),
  getters: {
    isListFresh: (state) =>
      state.listFetchedAt !== 0 && Date.now() - state.listFetchedAt < LIST_TTL_MS,
    founders: (state) => state.list.filter((m) => m.member_group === 'founder'),
    advisors: (state) => state.list.filter((m) => m.member_group !== 'founder'),
  },
  actions: {
    async loadList({ force = false, signal } = {}) {
      if (!force && this.isListFresh) {
        return this.list
      }

      if (this.list.length === 0) {
        const cached = readCache('team')
        if (cached) {
          this.list = cached.list
        }
      }

      this.listLoading = true
      this.listError = null

      try {
        const response = await getJson('/api/public/team-members', { signal })
        this.list = Array.isArray(response?.data) ? response.data : []
        this.listFetchedAt = Date.now()
        writeCache('team', { list: this.list })
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
