// Lightweight localStorage-backed cache for public list views.
//
// Purpose: let a full page reload paint the last-seen content instantly while
// the normal API request revalidates in the background. This makes refreshes
// feel fast even when the backend is waking from idle.
//
// It makes NO extra network requests — it only persists data already fetched,
// and reads it back on the next load. Best-effort: any storage error is
// swallowed so it can never break rendering.

const PREFIX = 'waais:cache:'

export function readCache(key) {
  try {
    if (typeof localStorage === 'undefined') return null
    const raw = localStorage.getItem(PREFIX + key)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed || !Array.isArray(parsed.list)) return null
    return parsed
  } catch {
    return null
  }
}

export function writeCache(key, value) {
  try {
    if (typeof localStorage === 'undefined') return
    localStorage.setItem(PREFIX + key, JSON.stringify(value))
  } catch {
    // Ignore quota / availability errors — the cache is a best-effort optimization.
  }
}
