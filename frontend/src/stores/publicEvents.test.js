import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePublicEventsStore } from './publicEvents'
import { ApiError } from '../lib/api'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const SAMPLE_PAGE = {
  data: [
    { id: 1, title: 'AI Founder Salon', status: 'upcoming', starts_at: '2026-05-14T22:00:00Z' },
    { id: 2, title: 'Agentic Workflows', status: 'upcoming', starts_at: '2026-05-28T18:00:00Z' },
  ],
  current_page: 1,
  last_page: 2,
  per_page: 12,
  total: 18,
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

    const store = usePublicEventsStore()
    await store.loadList()

    expect(store.list).toHaveLength(2)
    expect(store.list[0].title).toBe('AI Founder Salon')
    expect(store.listMeta).toEqual({ currentPage: 1, lastPage: 2, perPage: 12, total: 18, time: 'upcoming' })
    expect(store.listLoading).toBe(false)
    expect(store.listError).toBeNull()
  })

  it('passes time, page, and per_page query parameters', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ...SAMPLE_PAGE, current_page: 2, per_page: 24 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadList({ time: 'past', page: 2, perPage: 24 })

    const [calledUrl] = fetchMock.mock.calls[0]
    expect(calledUrl).toContain('time=past')
    expect(calledUrl).toContain('page=2')
    expect(calledUrl).toContain('per_page=24')
  })

  it('does not refetch within the TTL when called with the same args', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadList()
    await store.loadList()

    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('refetches when force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('refetches when called with a different time filter', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadList({ time: 'upcoming' })
    await store.loadList({ time: 'past' })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('refetches when called with different pagination args', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadList({ page: 1 })
    await store.loadList({ page: 2 })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('captures the error and clears loading on failure', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'boom' }, { status: 500 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.listError).toBeInstanceOf(ApiError)
    expect(store.listLoading).toBe(false)
  })
})

describe('loadOne', () => {
  it('fetches a single event and populates currentEvent', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse({ data: { id: 7, title: 'AI Founder Salon', status: 'upcoming' } }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadOne(7)

    expect(store.currentEvent).toEqual({ id: 7, title: 'AI Founder Salon', status: 'upcoming' })
    expect(store.currentEventId).toBe(7)
    expect(fetchMock.mock.calls[0][0]).toContain('/api/public/events/7')
  })

  it('uses the cached list entry as an optimistic placeholder before the fetch resolves', async () => {
    let resolveFetch
    const fetchMock = vi.fn().mockImplementationOnce(
      () => new Promise((resolve) => { resolveFetch = resolve }),
    )
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    store.list = [{ id: 7, title: 'AI Founder Salon (cached)' }]

    const promise = store.loadOne(7)
    expect(store.currentEvent).toEqual({ id: 7, title: 'AI Founder Salon (cached)' })
    expect(store.currentLoading).toBe(true)

    resolveFetch(jsonResponse({ data: { id: 7, title: 'AI Founder Salon (fresh)' } }))
    await promise
    expect(store.currentEvent).toEqual({ id: 7, title: 'AI Founder Salon (fresh)' })
  })

  it('clears currentEvent on 404 and surfaces the error', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'not found' }, { status: 404 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await expect(store.loadOne(99)).rejects.toMatchObject({ status: 404 })
    expect(store.currentEvent).toBeNull()
    expect(store.currentError).toMatchObject({ status: 404 })
  })
})

describe('invalidate', () => {
  it('forces the next loadList to refetch', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicEventsStore()
    await store.loadList()
    store.invalidate()
    await store.loadList()

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
