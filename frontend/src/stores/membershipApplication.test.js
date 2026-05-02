import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '../lib/api'
import { useMembershipApplicationStore } from './membershipApplication'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const APPLICATION = {
  id: 1,
  approval_status: 'submitted',
  email: 'applicant@example.com',
  first_name: 'Ada',
  last_name: 'Lovelace',
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('load', () => {
  it('loads the current user application with Sanctum credentials', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: APPLICATION }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMembershipApplicationStore()
    await store.load()

    expect(store.application).toEqual(APPLICATION)
    expect(store.status).toBe('submitted')
    expect(store.loading).toBe(false)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/membership-application')
    expect(init.method).toBe('GET')
    expect(init.credentials).toBe('include')
  })

  it('caches the application until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: APPLICATION }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMembershipApplicationStore()
    await store.load()
    await store.load()
    await store.load({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces load errors', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Unauthenticated.' }, { status: 401 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMembershipApplicationStore()
    await expect(store.load()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
  })
})

describe('save', () => {
  it('submits a new application with POST', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: APPLICATION }, { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMembershipApplicationStore()
    await store.save({ email: 'applicant@example.com' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/membership-application')
    expect(init.method).toBe('POST')
    expect(init.credentials).toBe('include')
    expect(store.application).toEqual(APPLICATION)
  })

  it('updates an existing submitted application with PATCH', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { ...APPLICATION, experience_summary: 'Updated' } }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMembershipApplicationStore()
    store.application = APPLICATION

    await store.save({ email: 'applicant@example.com', experience_summary: 'Updated' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/membership-application')
    expect(init.method).toBe('PATCH')
    expect(store.application.experience_summary).toBe('Updated')
  })

  it('uses the reapply endpoint for rejected applications', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { ...APPLICATION, approval_status: 'submitted' } }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMembershipApplicationStore()
    store.application = { ...APPLICATION, approval_status: 'rejected' }

    await store.save({ email: 'applicant@example.com' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/membership-application/reapply')
    expect(init.method).toBe('POST')
  })
})
