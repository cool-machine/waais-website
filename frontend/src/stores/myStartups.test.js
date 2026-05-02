import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '../lib/api'
import { useMyStartupsStore } from './myStartups'

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
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('loads member-owned startup listings with Sanctum credentials', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [LISTING] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMyStartupsStore()
    await store.loadList()

    expect(store.list).toEqual([LISTING])
    expect(store.hasListings).toBe(true)
    expect(store.loading).toBe(false)

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/startup-listings')
    expect(init.method).toBe('GET')
    expect(init.credentials).toBe('include')
  })

  it('caches the loaded list until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [LISTING] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMyStartupsStore()
    await store.loadList()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces load errors', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Forbidden.' }, { status: 403 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMyStartupsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
  })
})

describe('save', () => {
  it('submits a new listing with POST', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: LISTING }, { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMyStartupsStore()
    await store.save({ name: 'AutoFlow AI' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/startup-listings')
    expect(init.method).toBe('POST')
    expect(init.credentials).toBe('include')
    expect(store.currentListing).toEqual(LISTING)
    expect(store.list).toEqual([LISTING])
  })

  it('updates the selected listing with PATCH', async () => {
    const updated = { ...LISTING, tagline: 'Updated tagline' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: updated }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMyStartupsStore()
    store.list = [LISTING]
    store.selectListing(LISTING)

    await store.save({ name: 'AutoFlow AI', tagline: 'Updated tagline' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/startup-listings/7')
    expect(init.method).toBe('PATCH')
    expect(store.list[0].tagline).toBe('Updated tagline')
  })
})
