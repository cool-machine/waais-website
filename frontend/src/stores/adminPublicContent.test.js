import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAdminPublicContentStore } from './adminPublicContent'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const CARD = {
  id: 5,
  section: 'what_we_do',
  eyebrow: 'Studio',
  title: 'Build with alumni operators',
  body: 'Applied AI work with the WAAIS network.',
  visibility: 'public',
  content_status: 'draft',
  sort_order: 10,
}

const PARTNER = {
  id: 9,
  name: 'Wharton AI Lab',
  partner_type: 'Academic partner',
  summary: 'Research partner.',
  description: 'Supports applied AI programming.',
  visibility: 'mixed',
  content_status: 'published',
  sort_order: 20,
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.restoreAllMocks()
})

describe('adminPublicContent store', () => {
  it('loads homepage cards from the authenticated admin API', async () => {
    const fetchMock = vi.fn(() => Promise.resolve(jsonResponse({
      data: [CARD],
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 1,
    })))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminPublicContentStore()
    await store.loadList()

    expect(store.list).toEqual([CARD])
    expect(store.resource).toBe('homepage_cards')
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/homepage-cards?')
    expect(fetchMock.mock.calls[0][0]).toContain('content_status=draft')
    expect(fetchMock.mock.calls[0][1].credentials).toBe('include')
  })

  it('switches to partners and omits all filters from the query string', async () => {
    const fetchMock = vi.fn(() => Promise.resolve(jsonResponse({
      data: [PARTNER],
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 1,
    })))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminPublicContentStore()
    store.selectItem(CARD)
    await store.loadList({ resource: 'partners', contentStatus: 'all', visibility: 'all', force: true })

    expect(store.resource).toBe('partners')
    expect(store.currentItem).toBeNull()
    expect(store.list).toEqual([PARTNER])
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/partners?')
    expect(fetchMock.mock.calls[0][0]).not.toContain('content_status=')
    expect(fetchMock.mock.calls[0][0]).not.toContain('visibility=')
  })

  it('creates a partner through the selected resource endpoint', async () => {
    const fetchMock = vi.fn((url, init = {}) => {
      if (url.includes('/api/admin/partners')) {
        return Promise.resolve(jsonResponse({ data: PARTNER }, { status: init.method === 'POST' ? 201 : 200 }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminPublicContentStore()
    await store.loadList({ resource: 'partners', contentStatus: 'all', visibility: 'all', force: true })
    await store.save({
      name: PARTNER.name,
      summary: PARTNER.summary,
      description: PARTNER.description,
      visibility: PARTNER.visibility,
    })

    const createRequest = fetchMock.mock.calls.find(([url, init]) => (
      url.includes('/api/admin/partners') && init?.method === 'POST'
    ))
    expect(JSON.parse(createRequest[1].body)).toEqual({
      name: PARTNER.name,
      summary: PARTNER.summary,
      description: PARTNER.description,
      visibility: PARTNER.visibility,
    })
    expect(store.currentItem).toEqual(PARTNER)
  })

  it('publishes the selected homepage card and removes it from a draft queue', async () => {
    const published = { ...CARD, content_status: 'published' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/admin/homepage-cards/5/publish')) {
        return Promise.resolve(jsonResponse({ data: published }))
      }
      if (url.includes('/api/admin/homepage-cards')) {
        return Promise.resolve(jsonResponse({
          data: [CARD],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminPublicContentStore()
    await store.loadList()
    store.selectItem(CARD)
    await store.publish()

    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
    expect(fetchMock.mock.calls.some(([url]) => url.includes('/api/admin/homepage-cards/5/publish'))).toBe(true)
  })
})
