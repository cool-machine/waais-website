# WAAIS Vue Frontend

Vue 3 public frontend scaffold for the Wharton Alumni AI Studio platform.

This is the first production-frontend pass converted from the static mockups in `../mockups/`. It is not the Laravel backend, but it now starts the backend-owned Google OAuth flow, can request email sign-in links for non-Google applicants, reads the current Sanctum user session from `/api/user`, supports sign-out, persists the applicant-owned membership form, shows the member dashboard profile/application status and announcements, lets approved members submit/update owned startup listings from the dashboard, and wires admin membership/startup review queues, events, public content, announcements, and user management. It does not persist event registrations or forum feeds yet.

## Stack

- Vue 3
- Vite
- Vue Router
- Pinia
- Tailwind CSS v4 via `@tailwindcss/vite`
- Vitest + @vue/test-utils + jsdom for unit/component tests

## Commands

```sh
npm install
npm run dev -- --host 127.0.0.1 --port 5174
npm run build
npm test          # vitest run — unit/component tests
npm run test:watch
npm run test:routes
```

## API Integration

The public site reads live data from the Laravel API via Pinia stores. The startup directory, events calendar, partners directory, and homepage CMS cards are wired to public API endpoints. The HTTP client lives at `src/lib/api.js`. The base URL resolves from `VITE_API_BASE_URL` (default `http://127.0.0.1:8000`, which is Laravel's `php artisan serve` default).

Authenticated frontend requests also go through `src/lib/api.js`. Public stores stay anonymous by default; authenticated stores pass `auth: true`, which sends browser credentials for Laravel Sanctum's session-cookie flow. `sendJson()` handles JSON mutations and sends Laravel's `X-XSRF-TOKEN` header when the cookie is present. The current-user store lives at `src/stores/authUser.js`, calls `/api/user`, treats 401 as an anonymous browser state, starts Google sign-in by redirecting to `${VITE_API_BASE_URL}/auth/google/redirect`, requests email sign-in links through `POST /api/auth/email-link`, and signs out through `POST /api/logout`. The membership application store lives at `src/stores/membershipApplication.js`; it backs the real `/membership` form and the first member dashboard profile/application status views. The member announcements store lives at `src/stores/memberAnnouncements.js` and backs the member-dashboard announcements panel from `/api/announcements`. The member startup store lives at `src/stores/myStartups.js` and backs `/app/my-startups`. The admin membership approvals store lives at `src/stores/adminMembershipApplications.js` and backs `/app/approvals` for listing membership applications, loading detail, and approving/rejecting/requesting more info. The admin startup review store lives at `src/stores/adminStartupListings.js` and backs `/app/startup-review` for the same queue/detail/transition flow against startup listings. The admin events store lives at `src/stores/adminEvents.js` and backs `/app/events-admin` for create/edit/publish/hide/archive/cancel against `/api/admin/events`. The admin public content store lives at `src/stores/adminPublicContent.js` and backs `/app/content-admin` for create/edit/publish/hide/archive against `/api/admin/homepage-cards` and `/api/admin/partners`. The admin announcements store lives at `src/stores/adminAnnouncements.js` and backs `/app/announcements` for create/edit/publish/hide/archive against `/api/admin/announcements`. The admin users store lives at `src/stores/adminUsers.js` and backs `/app/users` for filterable directory search and (super-admin only) promote/demote transitions against `/api/admin/users` and the existing `/api/admin/users/{user}/{promote-admin,demote-admin,promote-super-admin,demote-super-admin}` routes.

Local dev workflow when you need real startup data on `/startups`:

```sh
# in /backend
php artisan migrate:fresh
php artisan db:seed --class=SmokeStartupSeeder --force
php artisan serve --host=127.0.0.1 --port=8000

# in /frontend (separate terminal)
npm run dev -- --host 127.0.0.1 --port 5174
```

Events, partners, and homepage CMS cards also read from the Laravel API, but there are not yet smoke seeders for those surfaces. Create/publish rows through the API or a local tinker session when you need non-empty `/events` or `/partners` data locally. The homepage keeps fallback copy for CMS-backed sections so an empty CMS does not blank out the landing page.

Local membership application smoke workflow:

```sh
# in /backend
php artisan serve --host=127.0.0.1 --port=8000

# in /frontend
npm run dev -- --host 127.0.0.1 --port 5174
```

Open `http://127.0.0.1:5174/waais-website/membership`, sign in with Google if needed, and submit/update the form. The backend `.env` must have valid Google OAuth settings for sign-in.

Pages must not call `fetch` directly. They consume Pinia stores under `src/stores/`. The convention for naming and structuring stores — and when to add a new store vs. extend an existing one — is documented in `src/stores/README.md`.

## Deploy to GitHub Pages

GitHub Pages serves the built Vue preview from the repository root (branch `main`, path `/`). To refresh the deployed preview after frontend changes, build and copy the artifacts to the repo root:

```sh
cd /Users/gg1900/coding/waais-website/frontend
npm run build
cd ..
rm -rf assets
mkdir -p assets
cp frontend/dist/index.html index.html
cp frontend/dist/index.html 404.html
cp -R frontend/dist/assets/. assets/
cp frontend/dist/favicon.svg favicon.svg
cp frontend/dist/icons.svg icons.svg
```

Then commit the updated root-level files and push `main`. Direct deep links are served via `404.html` as an SPA fallback — GitHub may return HTTP 404 internally, but the browser renders the Vue app.

Live preview: `https://cool-machine.github.io/waais-website/`

## Implemented Routes

- `/`
- `/events`
- `/events/:id`
- `/startups`
- `/startups/:id`
- `/about`
- `/partners`
- `/partners/:id`
- `/membership`
- `/forum`
- `/contact`
- `/legal`
- `/app/:view?`

## Still Mocked

- Member dashboard event/forum surfaces
- Full admin route guarding and super-admin-specific permission gating
- Email and dashboard notifications (UI side — the backend ships them)
- Discourse SSO and forum installation
- Forum-preview pages still serve static seed data (`src/data/forum.js`) — wiring them to the Laravel API is queued in a subsequent slice

The startup directory (`/startups`, `/startups/:id`, and the homepage's "Featured startups" section), events calendar (`/events`, `/events/:id`, and the homepage's "Selected events" section), partners directory (`/partners`, `/partners/:id`), and homepage CMS card sections read from the live API.
