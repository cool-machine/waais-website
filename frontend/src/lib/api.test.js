import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, getJson, sendJson, __testing } from './api'

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
    expect(init.credentials).toBe('same-origin')
  })

  it('includes browser credentials when auth=true', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ id: 1 }))
    await getJson('/api/user', { auth: true, fetchImpl })

    const [, init] = fetchImpl.mock.calls[0]
    expect(init.credentials).toBe('include')
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

describe('buildGoogleAuthUrl', () => {
  it('points at the backend Google OAuth redirect route', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000/')
    expect(__testing.buildGoogleAuthUrl()).toBe('http://localhost:8000/auth/google/redirect')
  })

  it('can include a safe frontend return path', () => {
    vi.stubEnv('VITE_API_BASE_URL', 'http://localhost:8000/')
    expect(__testing.buildGoogleAuthUrl('/membership')).toBe(
      'http://localhost:8000/auth/google/redirect?next=%2Fmembership',
    )
  })
})

describe('sendJson', () => {
  it('sends JSON with authenticated credentials', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 1 } }))
    const result = await sendJson('/api/membership-application', {
      auth: true,
      body: { email: 'applicant@example.com' },
      fetchImpl,
    })

    expect(result).toEqual({ data: { id: 1 } })
    const [url, init] = fetchImpl.mock.calls[0]
    expect(url).toContain('/api/membership-application')
    expect(init.method).toBe('POST')
    expect(init.credentials).toBe('include')
    expect(init.headers.Accept).toBe('application/json')
    expect(init.headers['Content-Type']).toBe('application/json')
    expect(init.body).toBe(JSON.stringify({ email: 'applicant@example.com' }))
  })

  it('includes Laravel XSRF token when present', async () => {
    vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=abc%20123; laravel-session=session-value' })
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 1 } }))

    await sendJson('/api/membership-application', { auth: true, fetchImpl })

    const [, init] = fetchImpl.mock.calls[0]
    expect(init.headers['X-XSRF-TOKEN']).toBe('abc 123')
  })

  it('supports PATCH and throws ApiError on validation failures', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ message: 'Invalid' }, { status: 422 }))

    await expect(sendJson('/api/membership-application', {
      method: 'PATCH',
      body: { email: '' },
      fetchImpl,
    })).rejects.toMatchObject({ status: 422, body: { message: 'Invalid' } })

    const [, init] = fetchImpl.mock.calls[0]
    expect(init.method).toBe('PATCH')
  })
})

describe('readCookie', () => {
  it('returns a decoded cookie by name', () => {
    expect(__testing.readCookie('XSRF-TOKEN', 'one=1; XSRF-TOKEN=hello%20there')).toBe('hello there')
  })

  it('returns null when the cookie is absent', () => {
    expect(__testing.readCookie('missing', 'one=1')).toBeNull()
  })
})
