import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '../lib/api'
import { useAdminMembershipApplicationsStore } from './adminMembershipApplications'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const APPLICATION = {
  id: 12,
  approval_status: 'submitted',
  email: 'ada@example.com',
  first_name: 'Ada',
  last_name: 'Lovelace',
  affiliation_type: 'alumni',
  school_affiliation: 'Wharton MBA',
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('loads the admin membership queue with Sanctum credentials and status filtering', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      data: [APPLICATION],
      current_page: 1,
      last_page: 2,
      per_page: 25,
      total: 26,
    }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    await store.loadList({ status: 'submitted' })

    expect(store.list).toEqual([APPLICATION])
    expect(store.currentApplication).toEqual(APPLICATION)
    expect(store.listMeta.total).toBe(26)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/applications')
    expect(url).toContain('status=submitted')
    expect(init.method).toBe('GET')
    expect(init.credentials).toBe('include')
  })

  it('caches a loaded page until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [APPLICATION] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    await store.loadList()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces load errors', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Forbidden.' }, { status: 403 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
  })
})

describe('loadOne', () => {
  it('uses a cached row as a placeholder before loading full detail', async () => {
    const fullApplication = { ...APPLICATION, revisions: [{ id: 1 }] }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: fullApplication }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    store.list = [APPLICATION]

    const result = await store.loadOne(APPLICATION.id)

    expect(result).toEqual(fullApplication)
    expect(store.currentApplication).toEqual(fullApplication)
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/applications/12')
  })
})

describe('transitions', () => {
  it('approves the selected application and removes it from the submitted queue', async () => {
    const approved = { ...APPLICATION, approval_status: 'approved', review_notes: 'Approved.' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: approved }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    store.list = [APPLICATION]
    store.listMeta.total = 1
    store.selectApplication(APPLICATION)

    await store.approve('Approved.')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/applications/12/approve')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ review_notes: 'Approved.' })
    expect(store.currentApplication).toBeNull()
    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
  })

  it('selects the next application after approving the current one from the active queue', async () => {
    const nextApplication = { ...APPLICATION, id: 13, email: 'next@example.com' }
    const approved = { ...APPLICATION, approval_status: 'approved' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: approved }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    store.list = [APPLICATION, nextApplication]
    store.listMeta.total = 2
    store.selectApplication(APPLICATION)

    await store.approve()

    expect(store.list).toEqual([nextApplication])
    expect(store.currentApplication).toEqual(nextApplication)
    expect(store.listMeta.total).toBe(1)
  })

  it('sends required notes and the rejection email flag when rejecting', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      data: { ...APPLICATION, approval_status: 'rejected' },
    }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminMembershipApplicationsStore()
    store.selectApplication(APPLICATION)

    await store.reject('Not a fit yet.', true)

    const [, init] = fetchMock.mock.calls[0]
    expect(JSON.parse(init.body)).toEqual({
      review_notes: 'Not a fit yet.',
      send_email: true,
    })
  })
})
