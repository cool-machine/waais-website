import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePublicStartupsStore } from './publicStartups'
import { ApiError } from '../lib/api'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const SAMPLE_PAGE = {
  data: [
    { id: 1, name: 'AutoFlow AI', tagline: 'B2B workflow automation', industry: 'AI Engineering' },
    { id: 2, name: 'EcoPredict', tagline: 'Climate analytics', industry: 'Climate Tech' },
  ],
  current_page: 1,
  last_page: 3,
  per_page: 12,
  total: 30,
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('fetches a page and populates list + listMeta', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadList()

    expect(store.list).toHaveLength(2)
    expect(store.list[0].name).toBe('AutoFlow AI')
    expect(store.listMeta).toEqual({ currentPage: 1, lastPage: 3, perPage: 12, total: 30 })
    expect(store.listLoading).toBe(false)
    expect(store.listError).toBeNull()
  })

  it('passes page and per_page query parameters', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ...SAMPLE_PAGE, current_page: 2, per_page: 24 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadList({ page: 2, perPage: 24 })

    const [calledUrl] = fetchMock.mock.calls[0]
    expect(calledUrl).toContain('page=2')
    expect(calledUrl).toContain('per_page=24')
  })

  it('does not refetch within the TTL when called with the same args', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadList()
    await store.loadList()

    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('refetches when force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('refetches when called with different pagination args', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadList({ page: 1 })
    await store.loadList({ page: 2 })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('captures the error and clears loading on failure', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'boom' }, { status: 500 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.listError).toBeInstanceOf(ApiError)
    expect(store.listLoading).toBe(false)
  })
})

describe('loadOne', () => {
  it('fetches a single listing and populates currentListing', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({ data: { id: 7, name: 'AutoFlow AI', tagline: 'Workflow automation' } }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadOne(7)

    expect(store.currentListing).toEqual({ id: 7, name: 'AutoFlow AI', tagline: 'Workflow automation' })
    expect(store.currentListingId).toBe(7)
    expect(fetchMock.mock.calls[0][0]).toContain('/api/public/startup-listings/7')
  })

  it('uses the cached list entry as an optimistic placeholder before the fetch resolves', async () => {
    let resolveFetch
    const fetchMock = vi.fn().mockImplementationOnce(
      () => new Promise((resolve) => { resolveFetch = resolve }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    store.list = [{ id: 7, name: 'AutoFlow AI (cached)' }]

    const promise = store.loadOne(7)
    // optimistic populated synchronously
    expect(store.currentListing).toEqual({ id: 7, name: 'AutoFlow AI (cached)' })
    expect(store.currentLoading).toBe(true)

    resolveFetch(jsonResponse({ data: { id: 7, name: 'AutoFlow AI (fresh)' } }))
    await promise
    expect(store.currentListing).toEqual({ id: 7, name: 'AutoFlow AI (fresh)' })
  })

  it('clears currentListing on 404 and surfaces the error', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'not found' }, { status: 404 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await expect(store.loadOne(99)).rejects.toMatchObject({ status: 404 })
    expect(store.currentListing).toBeNull()
    expect(store.currentError).toMatchObject({ status: 404 })
  })
})

describe('invalidate', () => {
  it('forces the next loadList to refetch', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicStartupsStore()
    await store.loadList()
    store.invalidate()
    await store.loadList()

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
