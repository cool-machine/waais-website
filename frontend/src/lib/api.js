// Thin HTTP client used by all stores. Knows the API base URL,
// always asks for JSON, throws an `ApiError` on non-2xx so callers
// don't have to remember to check `response.ok`.
//
// Base URL precedence:
//   1. import.meta.env.VITE_API_BASE_URL (build-time / Vite env)
//   2. http://127.0.0.1:8000 (Laravel's `php artisan serve` default)
//
// Public stores stay anonymous by default. Authenticated stores opt in
// with `auth: true`, which sends browser credentials for Laravel
// Sanctum's session-cookie flow.

const DEFAULT_BASE_URL = 'http://127.0.0.1:8000'

export class ApiError extends Error {
  constructor(message, { status, url, body } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.url = url
    this.body = body
  }
}

function resolveBaseUrl() {
  const raw = import.meta.env?.VITE_API_BASE_URL
  // `??` only catches null/undefined; an env var set to '' should also fall back.
  const base = raw && raw.trim() !== '' ? raw : DEFAULT_BASE_URL
  return base.replace(/\/+$/, '')
}

function buildUrl(path, query) {
  const base = resolveBaseUrl()
  const url = new URL(path.startsWith('/') ? path : `/${path}`, `${base}/`)
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null) continue
      url.searchParams.set(key, String(value))
    }
  }
  return url.toString()
}

function buildGoogleAuthUrl(next) {
  return buildUrl('/auth/google/redirect', next ? { next } : undefined)
}

function readCookie(name, cookieString = globalThis.document?.cookie ?? '') {
  const encodedName = `${encodeURIComponent(name)}=`
  const cookie = cookieString
    .split(';')
    .map((item) => item.trim())
    .find((item) => item.startsWith(encodedName))

  if (!cookie) return null

  return decodeURIComponent(cookie.slice(encodedName.length))
}

async function parseBody(response) {
  const contentType = response.headers.get('content-type') ?? ''
  if (!contentType.includes('application/json')) {
    return null
  }
  try {
    return await response.json()
  } catch {
    return null
  }
}

/**
 * GET a JSON endpoint. Returns the parsed body on 2xx, throws ApiError otherwise.
 *
 * @param {string} path - path beginning with /, e.g. /api/public/startup-listings
 * @param {{ query?: Record<string, unknown>, signal?: AbortSignal, fetchImpl?: typeof fetch, auth?: boolean }} [options]
 */
export async function getJson(path, options = {}) {
  const { query, signal, fetchImpl, auth = false } = options
  const url = buildUrl(path, query)
  const fetchFn = fetchImpl ?? globalThis.fetch

  let response
  try {
    response = await fetchFn(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: auth ? 'include' : 'same-origin',
      signal,
    })
  } catch (cause) {
    throw new ApiError(`Network error contacting ${url}: ${cause.message}`, { url })
  }

  const body = await parseBody(response)

  if (!response.ok) {
    throw new ApiError(`Request to ${url} failed with status ${response.status}`, {
      status: response.status,
      url,
      body,
    })
  }

  return body
}

/**
 * Send a JSON request. Used by authenticated stores for Laravel API mutations.
 *
 * @param {string} path
 * @param {{ method?: 'POST'|'PATCH'|'PUT'|'DELETE', body?: unknown, query?: Record<string, unknown>, signal?: AbortSignal, fetchImpl?: typeof fetch, auth?: boolean }} [options]
 */
export async function sendJson(path, options = {}) {
  const {
    method = 'POST',
    body,
    query,
    signal,
    fetchImpl,
    auth = false,
  } = options
  const url = buildUrl(path, query)
  const fetchFn = fetchImpl ?? globalThis.fetch
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }

  const xsrfToken = auth ? readCookie('XSRF-TOKEN') : null
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = xsrfToken
  }

  let response
  try {
    response = await fetchFn(url, {
      method,
      headers,
      credentials: auth ? 'include' : 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body),
      signal,
    })
  } catch (cause) {
    throw new ApiError(`Network error contacting ${url}: ${cause.message}`, { url })
  }

  const responseBody = await parseBody(response)

  if (!response.ok) {
    throw new ApiError(`Request to ${url} failed with status ${response.status}`, {
      status: response.status,
      url,
      body: responseBody,
    })
  }

  return responseBody
}

/**
 * Start backend-owned Google OAuth. The backend callback logs the user
 * in with Sanctum's session cookie and redirects back to the Vue app.
 *
 * @param {{ location?: Location, next?: string }} [options]
 */
export function redirectToGoogleSignIn({ location = globalThis.location, next } = {}) {
  location.assign(buildGoogleAuthUrl(next))
}

// Exported only for tests — gives a knob to override the resolved base
// URL without poking import.meta.env directly.
export const __testing = { resolveBaseUrl, buildUrl, buildGoogleAuthUrl, readCookie }
