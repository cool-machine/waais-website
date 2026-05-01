import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, getJson, __testing } from './api'

function jsonResponse(body, { status = 200 } = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('buildUrl', () => {
  it('joins base URL and path with a single slash', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000')
    expect(__testing.buildUrl('/api/public/startup-listings')).toBe(
      'http://localhost:8000/api/public/startup-listings',
    )
  })

  it('strips trailing slashes from the configured base URL', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000/')
    expect(__testing.buildUrl('/api/x')).toBe('http://localhost:8000/api/x')
  })

  it('falls back to the default base URL when VITE_API_BASE_URL is unset', () => {
    vi.stubEnv('VITE_API_BASE_URL', '')
    expect(__testing.buildUrl('/api/x')).toBe('http://127.0.0.1:8000/api/x')
  })

  it('appends query parameters and skips null/undefined values', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000')
    const url = __testing.buildUrl('/api/list', { page: 2, per_page: 12, missing: null, gone: undefined })
    expect(url).toBe('http://localhost:8000/api/list?page=2&per_page=12')
  })
})

describe('getJson', () => {
  it('returns parsed JSON on 2xx responses', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ data: [{ id: 1 }] }))
    const result = await getJson('/api/public/startup-listings', { fetchImpl })
    expect(result).toEqual({ data: [{ id: 1 }] })
    expect(fetchImpl).toHaveBeenCalledOnce()
    const [url, init] = fetchImpl.mock.calls[0]
    expect(url).toContain('/api/public/startup-listings')
    expect(init.headers.Accept).toBe('application/json')
  })

  it('throws ApiError with the response status on non-2xx', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ message: 'Not found' }, { status: 404 }))
    await expect(getJson('/api/public/startup-listings/999', { fetchImpl })).rejects.toMatchObject({
      name: 'ApiError',
      status: 404,
      body: { message: 'Not found' },
    })
  })

  it('throws ApiError on network failure', async () => {
    const fetchImpl = vi.fn().mockRejectedValue(new Error('connection refused'))
    await expect(getJson('/api/public/startup-listings', { fetchImpl })).rejects.toBeInstanceOf(ApiError)
  })

  it('serializes query parameters', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    await getJson('/api/public/startup-listings', { query: { page: 2, per_page: 24 }, fetchImpl })
    const [url] = fetchImpl.mock.calls[0]
    expect(url).toContain('page=2')
    expect(url).toContain('per_page=24')
  })
})
