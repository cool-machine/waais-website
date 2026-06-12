import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useContactMessageStore } from './contactMessage'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('send', () => {
  it('POSTs the message and tracks sent state', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }, { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useContactMessageStore()
    await store.send({ name: 'Ada', email: 'ada@example.com', topic: 'Support', message: 'Hello' })

    expect(store.sent).toBe(true)
    expect(store.sending).toBe(false)
    expect(store.error).toBeNull()

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/contact')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ name: 'Ada', email: 'ada@example.com', topic: 'Support', message: 'Hello' })
  })

  it('captures validation errors and rethrows', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ message: 'Invalid.', errors: { email: ['Invalid email.'] } }, { status: 422 }))
    vi.stubGlobal('fetch', fetchMock)

    const store = useContactMessageStore()
    await expect(store.send({ name: 'Ada', email: 'bad', topic: 'Support', message: 'Hi' })).rejects.toThrow()

    expect(store.sent).toBe(false)
    expect(store.error?.status).toBe(422)
  })
})
