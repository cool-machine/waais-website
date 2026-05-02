import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '../lib/api'
import { useAdminUsersStore } from './adminUsers'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const MEMBER = {
  id: 7,
  name: 'Grace Hopper',
  email: 'grace@example.com',
  approval_status: 'approved',
  affiliation_type: 'alumni',
  permission_role: 'member',
}

const ADMIN = {
  id: 12,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  approval_status: 'approved',
  affiliation_type: 'alumni',
  permission_role: 'admin',
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('loads the admin user list with Sanctum credentials', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      data: [MEMBER, ADMIN],
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 2,
    }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    await store.loadList()

    expect(store.list).toEqual([MEMBER, ADMIN])
    expect(store.listMeta.total).toBe(2)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/users')
    expect(init.method).toBe('GET')
    expect(init.credentials).toBe('include')
  })

  it('forwards permission_role, approval_status, and search filters', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [ADMIN] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    await store.loadList({ permissionRole: 'admin', approvalStatus: 'approved', q: 'ada' })

    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('permission_role=admin')
    expect(url).toContain('approval_status=approved')
    expect(url).toContain('q=ada')
    expect(store.listFilters.permission_role).toBe('admin')
  })

  it('omits "all" filter sentinels from the wire query', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [MEMBER] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    await store.loadList({ permissionRole: 'all', approvalStatus: 'all' })

    const [url] = fetchMock.mock.calls[0]
    expect(url).not.toContain('permission_role=')
    expect(url).not.toContain('approval_status=')
  })

  it('caches a loaded page until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [MEMBER] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    await store.loadList()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces load errors as ApiError', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Forbidden.' }, { status: 403 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
  })
})

describe('transitions', () => {
  it('promotes a selected member to admin and merges the partial response', async () => {
    const promotedPayload = {
      id: 7,
      name: 'Grace Hopper',
      email: 'grace@example.com',
      approval_status: 'approved',
      affiliation_type: 'alumni',
      permission_role: 'admin',
    }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: promotedPayload }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    store.list = [{ ...MEMBER, created_at: '2026-01-01T00:00:00Z' }]
    store.selectUser(store.list[0])

    await store.promoteAdmin()

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/users/7/promote-admin')
    expect(init.method).toBe('POST')
    expect(store.currentUser.permission_role).toBe('admin')
    // Merge keeps the wider listing fields that the partial response doesn't return.
    expect(store.currentUser.created_at).toBe('2026-01-01T00:00:00Z')
  })

  it('removes the user from the active filter when their role no longer matches', async () => {
    const promoted = { ...ADMIN, id: 7, permission_role: 'admin' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: promoted }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    store.listFilters.permission_role = 'member'
    store.list = [MEMBER]
    store.listMeta.total = 1
    store.selectUser(MEMBER)

    await store.promoteAdmin()

    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
  })

  it('captures backend conflicts on the saveError state', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      message: 'Cannot demote the last super admin.',
    }, { status: 409 }))
    vi.stubGlobal('fetch', fetchMock)

    const SUPER = { ...ADMIN, permission_role: 'super_admin' }
    const store = useAdminUsersStore()
    store.list = [SUPER]
    store.selectUser(SUPER)

    await expect(store.demoteSuperAdmin()).rejects.toBeInstanceOf(ApiError)
    expect(store.saveError).toBeInstanceOf(ApiError)
    expect(store.saveError.body.message).toContain('last super admin')
  })

  it('routes promote-super-admin and demote-admin to the matching endpoints', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: ADMIN }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminUsersStore()
    store.list = [ADMIN]
    store.selectUser(ADMIN)

    await store.promoteSuperAdmin()
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/users/12/promote-super-admin')

    await store.demoteAdmin()
    expect(fetchMock.mock.calls[1][0]).toContain('/api/admin/users/12/demote-admin')
  })
})

describe('clear', () => {
  it('resets state and filters', () => {
    const store = useAdminUsersStore()
    store.list = [MEMBER]
    store.listFilters.permission_role = 'admin'
    store.selectUser(MEMBER)
    store.error = new Error('boom')

    store.clear()
    expect(store.list).toEqual([])
    expect(store.currentUser).toBeNull()
    expect(store.listFilters.permission_role).toBe('all')
    expect(store.error).toBeNull()
  })
})
