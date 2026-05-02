import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '../lib/api'
import { useAdminStartupListingsStore } from './adminStartupListings'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const LISTING = {
  id: 7,
  name: 'AutoFlow AI',
  tagline: 'Workflow automation for B2B teams.',
  description: 'AutoFlow AI helps operations teams orchestrate workflows.',
  industry: 'AI Engineering',
  approval_status: 'submitted',
  content_status: 'pending_review',
  owner: {
    id: 3,
    name: 'Grace Hopper',
    email: 'grace@example.com',
  },
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('loads the admin startup queue with Sanctum credentials and status filtering', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      data: [LISTING],
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 1,
    }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    await store.loadList({ status: 'submitted' })

    expect(store.list).toEqual([LISTING])
    expect(store.currentListing).toEqual(LISTING)
    expect(store.listMeta.total).toBe(1)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/startup-listings')
    expect(url).toContain('status=submitted')
    expect(init.method).toBe('GET')
    expect(init.credentials).toBe('include')
  })

  it('caches a loaded page until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [LISTING] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    await store.loadList()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces load errors', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Forbidden.' }, { status: 403 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
  })
})

describe('loadOne', () => {
  it('loads a full listing detail after setting a cached placeholder', async () => {
    const fullListing = { ...LISTING, revisions: [{ id: 1 }] }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: fullListing }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    store.list = [LISTING]

    const result = await store.loadOne(LISTING.id)

    expect(result).toEqual(fullListing)
    expect(store.currentListing).toEqual(fullListing)
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/startup-listings/7')
  })
})

describe('transitions', () => {
  it('approves the selected listing and removes it from the submitted queue', async () => {
    const approved = { ...LISTING, approval_status: 'approved', content_status: 'published' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: approved }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    store.list = [LISTING]
    store.listMeta.total = 1
    store.selectListing(LISTING)

    await store.approve('Approved.')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/startup-listings/7/approve')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ review_notes: 'Approved.' })
    expect(store.currentListing).toBeNull()
    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
  })

  it('selects the next listing after approving the current one from the active queue', async () => {
    const nextListing = { ...LISTING, id: 8, name: 'Second Startup' }
    const approved = { ...LISTING, approval_status: 'approved', content_status: 'published' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: approved }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    store.list = [LISTING, nextListing]
    store.listMeta.total = 2
    store.selectListing(LISTING)

    await store.approve()

    expect(store.list).toEqual([nextListing])
    expect(store.currentListing).toEqual(nextListing)
    expect(store.listMeta.total).toBe(1)
  })

  it('sends required notes and the rejection email flag when rejecting', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      data: { ...LISTING, approval_status: 'rejected', content_status: 'hidden' },
    }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminStartupListingsStore()
    store.selectListing(LISTING)

    await store.reject('Missing company detail.', true)

    const [, init] = fetchMock.mock.calls[0]
    expect(JSON.parse(init.body)).toEqual({
      review_notes: 'Missing company detail.',
      send_email: true,
    })
  })
})
