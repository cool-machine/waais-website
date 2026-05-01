# WAAIS Vue Frontend Handoff

Last updated: May 1, 2026

This file is the historical frontend handoff. For the current full-project state, read `CURRENT_STATE.md`, `FRONTEND_HANDOFF_SUMMARY.md`, and `BACKEND_HANDOFF.md`.

## Current Branch

Frontend deployment branch after latest deployment work:

`main`

Current backend work is on `codex/backend-laravel-scaffold`.

## Important Git State

`frontend/` is tracked in git. The GitHub Pages root is populated from `frontend/dist` using root-level `index.html`, `404.html`, `assets/`, `favicon.svg`, and `icons.svg`.

For backend work, read `dev-context/PLATFORM_MODEL.md` and `dev-context/BACKEND_HANDOFF.md`. Frontend constants mirroring that vocabulary live in `frontend/src/data/platformModel.js`.

## What Was Done

Created the first Vue frontend scaffold in:

`/Users/gg1900/coding/waais-website/frontend/`

Actions completed:

- Created branch `codex/vue-frontend-scaffold`.
- Ran `npm create vite@latest frontend -- --template vue`.
- Ran `npm install`.
- Installed project dependencies:
  - `vue-router@4`
  - `pinia`
  - `tailwindcss`
  - `@tailwindcss/vite`
- Replaced the default Vite starter screen with a WAAIS route/component scaffold.
- Added Vue Router.
- Added Pinia initialization.
- Added Tailwind Vite plugin.
- Added WAAIS CSS tokens and responsive base styles in `frontend/src/styles.css`.
- Added public Vue routes/pages for:
  - `/`
  - `/events`
  - `/startups`
  - `/about`
  - `/partners`
  - `/membership`
  - `/forum`
  - `/contact`
  - `/legal`
- Added reusable components:
  - `PublicLayout.vue`
  - `PageHero.vue`
  - `CardGrid.vue`
  - `InfoCard.vue`
- Added static data files:
  - `events.js`
  - `startups.js`
  - `partners.js`
  - `forum.js`
- Continued the public frontend pass after coherence review:
  - Copied the licensed hero video into `frontend/public/assets/waais-hero-video.mp4`.
  - Added video support to `PageHero.vue`.
  - Expanded homepage, events, startups, membership, and forum preview content toward the approved static mockups.
  - Rewrote `frontend/README.md` for WAAIS instead of the default Vite text.
  - Renamed the package to `waais-frontend`.
  - Set Vite `base: './'` for portable static output.
  - Fixed stale handoff wording in `DEV_CONTEXT.md` and `CURRENT_STATE.md` about `/frontend/`.

## Verification Already Run

Build was run successfully:

```text
cd /Users/gg1900/coding/waais-website/frontend
npm run build
```

Result:

```text
✓ built in 154ms
```

The Vue dev server was also started successfully at:

`http://127.0.0.1:5174/`

It may not still be running in a later session; restart it with `npm run dev -- --host 127.0.0.1 --port 5174` if local review is needed.

The previous static mockup server may still be available at:

`http://127.0.0.1:5173/mockups/public-site.html`

## What Is Not Done Yet

Do not treat this as a finished production app. It is the current frontend preview, but it is still backed by static seed data.

Not done:

- The Vue pages are not intended to be pixel-perfect copies of the static mockups; they are the current production-frontend preview using static seed data.
- Full count-up/parallax behavior is not yet ported from the static mockup.
- App/auth/member/admin dashboard pages are frontend-only routes. They do not authenticate users or persist changes yet.
- Event, startup, and partner detail route structures exist, but they still use static seed data.
- Admin role gating is not implemented.
- Membership form does not submit anywhere yet.
- Backend API endpoints are not implemented yet.
- Laravel backend has been scaffolded, but PHP/Composer validation has not run locally.
- Authentication, database-backed workflows, email, CMS APIs, audit-log workflows, and Discourse SSO are not implemented.

## Next Recommended Steps

1. Re-run frontend checks if frontend files change:

```text
cd /Users/gg1900/coding/waais-website/frontend
npm run test:routes
npm run build
```

2. Start local dev server if needed:

```text
cd /Users/gg1900/coding/waais-website/frontend
npm run dev -- --host 127.0.0.1 --port 5174
```

3. Keep frontend changes aligned with `FRONTEND_HANDOFF_SUMMARY.md`.

## Source Mockups To Use

Primary visual reference:

`/Users/gg1900/coding/waais-website/mockups/public-site.html`

Auth/member/admin reference:

`/Users/gg1900/coding/waais-website/mockups/app-dashboard-admin-auth.html`

Design system reference:

`/Users/gg1900/coding/waais-website/mockups/design-system.html`

## Key Constraint

The frontend structure is stable enough for backend scaffolding. Azure deployment, Discourse installation, and production database access are still later phases.
