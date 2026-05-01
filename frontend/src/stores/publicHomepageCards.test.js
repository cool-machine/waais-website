import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePublicHomepageCardsStore } from './publicHomepageCards'
import { ApiError } from '../lib/api'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const SAMPLE_PAGE = {
  data: [
    { id: 1, section: 'what_we_do', title: 'Events with memory', body: 'Host salons.' },
    { id: 2, section: 'access_flow', title: 'Google sign in', body: 'New accounts start pending.' },
  ],
  current_page: 1,
  last_page: 1,
  per_page: 48,
  total: 2,
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('fetches cards and populates list + listMeta', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await store.loadList()

    expect(store.list).toHaveLength(2)
    expect(store.list[0].title).toBe('Events with memory')
    expect(store.listMeta).toEqual({ currentPage: 1, lastPage: 1, perPage: 48, total: 2, section: '' })
    expect(store.listLoading).toBe(false)
    expect(store.listError).toBeNull()
  })

  it('passes section, page, and per_page query parameters', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ...SAMPLE_PAGE, current_page: 2, per_page: 24 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await store.loadList({ section: 'what_we_do', page: 2, perPage: 24 })

    const [calledUrl] = fetchMock.mock.calls[0]
    expect(calledUrl).toContain('section=what_we_do')
    expect(calledUrl).toContain('page=2')
    expect(calledUrl).toContain('per_page=24')
  })

  it('does not refetch within the TTL when called with the same args', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await store.loadList()
    await store.loadList()

    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('refetches when called with a different section', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await store.loadList({ section: 'what_we_do' })
    await store.loadList({ section: 'access_flow' })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('refetches when force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('captures the error and clears loading on failure', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'boom' }, { status: 500 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.listError).toBeInstanceOf(ApiError)
    expect(store.listLoading).toBe(false)
  })
})

describe('bySection', () => {
  it('returns cards for a single section', () => {
    const store = usePublicHomepageCardsStore()
    store.list = SAMPLE_PAGE.data

    expect(store.bySection('what_we_do')).toEqual([
      { id: 1, section: 'what_we_do', title: 'Events with memory', body: 'Host salons.' },
    ])
  })
})

describe('invalidate', () => {
  it('forces the next loadList to refetch', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_PAGE))
    vi.stubGlobal('fetch', fetchMock)

    const store = usePublicHomepageCardsStore()
    await store.loadList()
    store.invalidate()
    await store.loadList()

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
