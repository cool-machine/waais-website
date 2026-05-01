# WAAIS Vue Frontend

Vue 3 public frontend scaffold for the Wharton Alumni AI Studio platform.

This is the first production-frontend pass converted from the static mockups in `../mockups/`. It is not the Laravel backend, does not authenticate users, and does not persist membership applications, event registrations, startup listings, announcements, or admin changes yet.

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

The public site reads live data from the Laravel API via Pinia stores. The startup directory, events calendar, and partners directory are wired to public API endpoints. The HTTP client lives at `src/lib/api.js`. The base URL resolves from `VITE_API_BASE_URL` (default `http://127.0.0.1:8000`, which is Laravel's `php artisan serve` default).

Local dev workflow when you need real startup data on `/startups`:

```sh
# in /backend
php artisan migrate:fresh
php artisan db:seed --class=SmokeStartupSeeder --force
php artisan serve --host=127.0.0.1 --port=8000

# in /frontend (separate terminal)
npm run dev -- --host 127.0.0.1 --port 5174
```

Events and partners also read from the Laravel API, but there are not yet smoke seeders for those surfaces. Create/publish rows through the API or a local tinker session when you need non-empty `/events` or `/partners` data locally.

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

- Google OAuth and pending approval
- Membership application submission
- Member dashboard and admin dashboard
- Admin/super-admin permission gating
- CMS publishing workflow UI for events, partners, announcements, homepage
- Email and dashboard notifications (UI side — the backend ships them)
- Discourse SSO and forum installation
- Forum-preview pages still serve static seed data (`src/data/forum.js`) — wiring them to the Laravel API is queued in a subsequent slice

The startup directory (`/startups`, `/startups/:id`, and the homepage's "Featured startups" section), events calendar (`/events`, `/events/:id`, and the homepage's "Selected events" section), and partners directory (`/partners`, `/partners/:id`) read from the live API.
