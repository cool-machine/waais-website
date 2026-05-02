import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useMemberAnnouncementsStore } from './memberAnnouncements'

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
  visibility: 'members_only',
  audience: 'all_members',
  channel: 'dashboard',
  published_at: '2026-05-02T18:00:00.000000Z',
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.restoreAllMocks()
})

describe('memberAnnouncements store', () => {
  it('loads member-visible announcements with auth credentials', async () => {
    const fetchMock = vi.fn(() => Promise.resolve(jsonResponse({
      data: [ANNOUNCEMENT],
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 1,
    })))
    vi.stubGlobal('fetch', fetchMock)

    const store = useMemberAnnouncementsStore()
    await store.loadList()

    expect(store.list).toEqual([ANNOUNCEMENT])
    expect(store.latest).toEqual(ANNOUNCEMENT)
    expect(fetchMock.mock.calls[0][0]).toContain('/api/announcements?')
    expect(fetchMock.mock.calls[0][1].credentials).toBe('include')
  })
})
