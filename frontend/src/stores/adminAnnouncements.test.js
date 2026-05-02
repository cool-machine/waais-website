import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAdminAnnouncementsStore } from './adminAnnouncements'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const ANNOUNCEMENT = {
  id: 8,
  title: 'Forum categories are live',
  summary: 'New member discussion spaces are available.',
  body: 'We opened new member spaces.',
  content_status: 'draft',
  visibility: 'members_only',
  audience: 'all_members',
  channel: 'dashboard',
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.restoreAllMocks()
})

describe('adminAnnouncements store', () => {
  it('loads announcements from the authenticated admin API', async () => {
    const fetchMock = vi.fn(() => Promise.resolve(jsonResponse({
      data: [ANNOUNCEMENT],
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 1,
    })))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminAnnouncementsStore()
    await store.loadList()

    expect(store.list).toEqual([ANNOUNCEMENT])
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/announcements?')
    expect(fetchMock.mock.calls[0][0]).toContain('content_status=draft')
    expect(fetchMock.mock.calls[0][1].credentials).toBe('include')
  })

  it('creates an announcement', async () => {
    const fetchMock = vi.fn((url, init = {}) => {
      if (url.includes('/api/admin/announcements')) {
        return Promise.resolve(jsonResponse({ data: ANNOUNCEMENT }, { status: init.method === 'POST' ? 201 : 200 }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminAnnouncementsStore()
    await store.save({
      title: ANNOUNCEMENT.title,
      body: ANNOUNCEMENT.body,
      audience: 'all_members',
      channel: 'dashboard',
    })

    const createRequest = fetchMock.mock.calls.find(([url, init]) => (
      url.includes('/api/admin/announcements') && init?.method === 'POST'
    ))
    expect(JSON.parse(createRequest[1].body)).toEqual({
      title: ANNOUNCEMENT.title,
      body: ANNOUNCEMENT.body,
      audience: 'all_members',
      channel: 'dashboard',
    })
    expect(store.currentAnnouncement).toEqual(ANNOUNCEMENT)
  })

  it('publishes the selected announcement and removes it from a draft queue', async () => {
    const published = { ...ANNOUNCEMENT, content_status: 'published' }
    const fetchMock = vi.fn((url) => {
      if (url.includes('/api/admin/announcements/8/publish')) return Promise.resolve(jsonResponse({ data: published }))
      if (url.includes('/api/admin/announcements')) {
        return Promise.resolve(jsonResponse({
          data: [ANNOUNCEMENT],
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
        }))
      }
      return Promise.resolve(jsonResponse({ message: 'Not found' }, { status: 404 }))
    })
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminAnnouncementsStore()
    await store.loadList()
    store.selectAnnouncement(ANNOUNCEMENT)
    await store.publish()

    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
  })
})
