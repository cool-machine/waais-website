import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthUserStore } from './authUser'
import { ApiError } from '../lib/api'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const MEMBER = {
  id: 1,
  name: 'Grace Hopper',
  email: 'grace@example.com',
  approval_status: 'approved',
  affiliation_type: 'alumni',
  permission_role: 'member',
  can_access_member_areas: true,
  can_publish_public_content: false,
  can_manage_admin_privileges: false,
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllEnvs()
})

describe('loadCurrentUser', () => {
  it('loads the authenticated user with Sanctum credentials', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(MEMBER))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAuthUserStore()
    await store.loadCurrentUser()

    expect(store.user).toEqual(MEMBER)
    expect(store.isAuthenticated).toBe(true)
    expect(store.canAccessMemberAreas).toBe(true)
    expect(store.loading).toBe(false)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/user')
    expect(init.credentials).toBe('include')
  })

  it('treats 401 as anonymous state', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Unauthenticated.' }, { status: 401 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAuthUserStore()
    const result = await store.loadCurrentUser()

    expect(result).toBeNull()
    expect(store.user).toBeNull()
    expect(store.initialized).toBe(true)
    expect(store.error).toBeNull()
  })

  it('caches the loaded user until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(MEMBER))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAuthUserStore()
    await store.loadCurrentUser()
    await store.loadCurrentUser()
    await store.loadCurrentUser({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces non-auth API errors', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Server error' }, { status: 500 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAuthUserStore()
    await expect(store.loadCurrentUser()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
    expect(store.loading).toBe(false)
  })
})

describe('startGoogleSignIn', () => {
  it('redirects to the backend Google OAuth route', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000')
    const location = { assign: vi.fn() }

    const store = useAuthUserStore()
    store.startGoogleSignIn({ location })

    expect(location.assign).toHaveBeenCalledWith('http://localhost:8000/auth/google/redirect')
  })

  it('passes a requested frontend return path', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000')
    const location = { assign: vi.fn() }

    const store = useAuthUserStore()
    store.startGoogleSignIn({ location, next: '/membership' })

    expect(location.assign).toHaveBeenCalledWith('http://localhost:8000/auth/google/redirect?next=%2Fmembership')
  })
})
