# Frontend Stores Convention

Pinia stores are the single source of truth for any data the Vue app receives from the WAAIS API. Pages and components do not call `fetch` directly — they call store actions and read store state.

## Why a store, not a per-page fetch

Two reasons. First, when the same resource is rendered on multiple pages (a startup card on `/startups` and the same startup on `/startups/:id`), the store keeps one cache and one loading flag, so navigating between them doesn't refetch. Second, when authenticated pages land — member dashboard, admin queue — they will share an HTTP client, an auth strategy, and an error model with the public pages. Centralizing in stores means we extend that surface in one place per slice instead of hunting through every page.

## Naming

One store per (backend resource family) × (access surface). The access surface matters because the backend serves different projections and different fields to anonymous, member, and admin callers, and the frontend should not collapse those into one shape.

| Store name                       | Endpoint family                                  | Access surface             |
|----------------------------------|--------------------------------------------------|----------------------------|
| `useAuthUserStore`               | `/api/user`, `/auth/google/redirect`             | Browser session / auth     |
| `usePublicStartupsStore`         | `/api/public/startup-listings`                   | Anonymous, public site     |
| `usePublicEventsStore`           | `/api/public/events`                             | Anonymous, public site     |
| `usePublicPartnersStore`         | `/api/public/partners`                           | Anonymous, public site     |
| `usePublicHomepageCardsStore`    | `/api/public/homepage-cards`                     | Anonymous, public site     |
| `useMyStartupsStore` (planned)   | `/api/startup-listings`                          | Authenticated member       |
| `useAdminStartupQueueStore` (planned) | `/api/admin/startup-listings`                | Authenticated admin        |

When a new resource lands (events, partners, announcements, applications), follow the same pattern: name the store after the resource family and the surface it serves. **Do not** put public and admin views into the same store; their fields and their auth semantics diverge.

## Expected state shape

A store that lists a resource and shows a single item should expose, at minimum:

```js
state: () => ({
  // List view
  list: [],            // Array of resource objects in the projection the API returns
  listMeta: { … },     // Pagination envelope (currentPage, lastPage, perPage, total)
  listFetchedAt: 0,    // ms epoch of last successful list fetch (drives TTL)
  listLoading: false,
  listError: null,     // ApiError instance or null

  // Detail view
  currentListing: null,
  currentListingId: null,
  currentLoading: false,
  currentError: null,
})
```

Errors are stored as `ApiError` instances (see `src/lib/api.js`). A 404 should leave `currentListing` null and the page should treat it as a not-found state, not a generic error.

Stores should expose at least these actions:

- `loadList({ page, perPage, force, signal })` — paginated fetch with a short in-memory TTL so back-navigation doesn't refetch.
- `loadOne(id, { signal })` — single-item fetch. If the cached `list` already contains the item, set `currentListing` to that copy synchronously as an optimistic placeholder, then resolve with the freshly-fetched copy.
- `invalidate()` — drop TTL so the next `loadList` refetches. Used after mutations (e.g., the member dashboard submitting a new listing).

## When to add a new store vs. extend an existing one

Add a new store when:

- The endpoint family is different (`/api/admin/...` vs `/api/public/...`).
- The access surface is different (anonymous vs. authenticated; member vs. admin).
- The shape returned by the API is meaningfully different (e.g., admin sees `review_notes`, `owner_id`, full `revisions[]`).

Extend an existing store when:

- You're adding a new action on the same endpoint family and surface (e.g., `usePublicStartupsStore.loadFeatured()` if we add `/api/public/startup-listings/featured`).
- You're adding a derived getter over state that's already there.

## HTTP client

All stores call `getJson(...)` from `src/lib/api.js`. That client knows the API base URL (`VITE_API_BASE_URL`, default `http://127.0.0.1:8000`), sets `Accept: application/json`, and throws an `ApiError` on non-2xx so callers don't have to inspect `response.ok`.

Public stores call `getJson(...)` without auth options and stay anonymous. Authenticated stores call `getJson(..., { auth: true })`, which sends browser credentials for Laravel Sanctum's session-cookie flow. `useAuthUserStore` is the root session store: it loads `/api/user`, treats 401 as signed-out state, and starts Google sign-in through the backend `/auth/google/redirect` route.

## Testing

Stores are pure functions over a fake `fetch`. Each store ships with a `*.test.js` file that uses Vitest's `vi.stubGlobal('fetch', ...)` and `setActivePinia(createPinia())` per test. See `publicStartups.test.js` for the pattern.

Pages don't need a unit test in this slice — they're thin templates over a store. End-to-end behavior is covered by the manual smoke (boot Laravel + Vite, hit `/startups` and `/startups/:id`) until we add component tests in a later slice.
