import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '../lib/api'
import { useAdminEventsStore } from './adminEvents'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

const EVENT = {
  id: 11,
  title: 'AI Founder Salon',
  summary: 'A focused salon.',
  description: 'Private dinner.',
  starts_at: '2026-06-10T18:00:00.000000Z',
  ends_at: '2026-06-10T21:00:00.000000Z',
  location: 'New York',
  format: 'Private dinner',
  capacity_limit: 50,
  waitlist_open: false,
  visibility: 'members_only',
  content_status: 'draft',
  cancelled_at: null,
  reminder_days_before: 2,
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('loadList', () => {
  it('loads the admin event queue with Sanctum credentials and content_status filter', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      data: [EVENT],
      current_page: 1,
      last_page: 1,
      per_page: 25,
      total: 1,
    }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    await store.loadList({ contentStatus: 'draft' })

    expect(store.list).toEqual([EVENT])
    expect(store.listMeta.total).toBe(1)
    expect(store.listContentStatus).toBe('draft')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/events')
    expect(url).toContain('content_status=draft')
    expect(init.method).toBe('GET')
    expect(init.credentials).toBe('include')
  })

  it('omits content_status when filter is "all"', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [EVENT] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    await store.loadList({ contentStatus: 'all' })

    const [url] = fetchMock.mock.calls[0]
    expect(url).not.toContain('content_status=')
    expect(store.listContentStatus).toBe('all')
  })

  it('caches a loaded page until force=true', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [EVENT] }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    await store.loadList()
    await store.loadList()
    await store.loadList({ force: true })

    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('surfaces load errors', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Forbidden.' }, { status: 403 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    await expect(store.loadList()).rejects.toBeInstanceOf(ApiError)
    expect(store.error).toBeInstanceOf(ApiError)
  })
})

describe('save', () => {
  it('POSTs a new event when no current event is selected', async () => {
    const created = { ...EVENT }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: created }, { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.startNew()
    const saved = await store.save({ title: 'AI Founder Salon' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/events')
    expect(url).not.toMatch(/\/api\/admin\/events\/\d+$/)
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ title: 'AI Founder Salon' })
    expect(saved).toEqual(created)
    expect(store.list).toEqual([created])
    expect(store.currentEvent).toEqual(created)
  })

  it('PATCHes the current event when one is selected', async () => {
    const updated = { ...EVENT, title: 'Renamed Salon' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: updated }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.list = [EVENT]
    store.selectEvent(EVENT)

    await store.save({ title: 'Renamed Salon' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/events/11')
    expect(init.method).toBe('PATCH')
    expect(store.currentEvent).toEqual(updated)
    expect(store.list).toEqual([updated])
  })

  it('captures validation errors on the saveError state', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({
      message: 'The given data was invalid.',
      errors: { title: ['The title field is required.'] },
    }, { status: 422 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.startNew()

    await expect(store.save({})).rejects.toBeInstanceOf(ApiError)
    expect(store.saveError).toBeInstanceOf(ApiError)
    expect(store.saveError.body.errors.title[0]).toContain('required')
  })
})

describe('transitions', () => {
  it('publishes the selected event and removes it from the draft queue', async () => {
    const published = { ...EVENT, content_status: 'published', published_at: '2026-06-01T10:00:00.000000Z' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: published }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.list = [EVENT]
    store.listMeta.total = 1
    store.listContentStatus = 'draft'
    store.selectEvent(EVENT)

    await store.publish()

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/events/11/publish')
    expect(init.method).toBe('POST')
    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
    expect(store.currentEvent).toBeNull()
  })

  it('keeps the event in the queue when the filter is "all"', async () => {
    const published = { ...EVENT, content_status: 'published' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: published }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.list = [EVENT]
    store.listMeta.total = 1
    store.listContentStatus = 'all'
    store.selectEvent(EVENT)

    await store.publish()

    expect(store.list).toEqual([published])
    expect(store.currentEvent).toEqual(published)
  })

  it('cancels the event with a note', async () => {
    const cancelled = { ...EVENT, cancelled_at: '2026-06-01T10:00:00.000000Z', cancellation_note: 'Venue unavailable.' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: cancelled }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.list = [EVENT]
    store.listContentStatus = 'all'
    store.selectEvent(EVENT)

    await store.cancel('  Venue unavailable.  ')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/admin/events/11/cancel')
    expect(JSON.parse(init.body)).toEqual({ cancellation_note: 'Venue unavailable.' })
    expect(store.currentEvent.cancelled_at).toBe('2026-06-01T10:00:00.000000Z')
  })

  it('sends null cancellation_note when cancelling without a note', async () => {
    const cancelled = { ...EVENT, cancelled_at: '2026-06-01T10:00:00.000000Z' }
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: cancelled }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.listContentStatus = 'all'
    store.selectEvent(EVENT)

    await store.cancel('')

    const [, init] = fetchMock.mock.calls[0]
    expect(JSON.parse(init.body)).toEqual({ cancellation_note: null })
  })

  it('hide and archive route to the matching endpoints', async () => {
    const hidden = { ...EVENT, content_status: 'hidden' }
    const archived = { ...EVENT, content_status: 'archived' }
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ data: hidden }))
      .mockResolvedValueOnce(jsonResponse({ data: archived }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useAdminEventsStore()
    store.listContentStatus = 'all'
    store.list = [EVENT]
    store.selectEvent(EVENT)

    await store.hide()
    expect(fetchMock.mock.calls[0][0]).toContain('/api/admin/events/11/hide')

    store.selectEvent(hidden)
    await store.archive()
    expect(fetchMock.mock.calls[1][0]).toContain('/api/admin/events/11/archive')
  })
})

describe('startNew/clear', () => {
  it('startNew clears currentEvent without touching the list', () => {
    const store = useAdminEventsStore()
    store.list = [EVENT]
    store.selectEvent(EVENT)
    store.startNew()
    expect(store.currentEvent).toBeNull()
    expect(store.list).toEqual([EVENT])
  })

  it('clear resets the store to defaults', () => {
    const store = useAdminEventsStore()
    store.list = [EVENT]
    store.listMeta.total = 5
    store.listContentStatus = 'published'
    store.selectEvent(EVENT)
    store.error = new Error('boom')

    store.clear()
    expect(store.list).toEqual([])
    expect(store.listMeta.total).toBe(0)
    expect(store.listContentStatus).toBe('draft')
    expect(store.currentEvent).toBeNull()
    expect(store.error).toBeNull()
  })
})
