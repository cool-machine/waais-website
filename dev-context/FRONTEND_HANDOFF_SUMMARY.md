# WAAIS Frontend Handoff Summary

Last updated: May 1, 2026

This is the quick continuation file for another LLM/developer if the current session stops. Read this after `DEV_CONTEXT.md`, `CURRENT_STATE.md`, `VUE_FRONTEND_HANDOFF.md`, and `PLATFORM_MODEL.md`.

## Current State

- Branch: `main`
- Repository: `https://github.com/cool-machine/waais-website`
- GitHub Pages Vue preview: `https://cool-machine.github.io/waais-website/`
- Local Vue dev URL: `http://127.0.0.1:5174/`
- Static mockups remain available under `/mockups/`.
- `/backend/` does not exist yet.
- `/legacy/old-react-site/` is reference-only and ignored by git.

## What Has Been Implemented

The Vue frontend lives in `/frontend/` and uses:

- Vue 3
- Vite
- Vue Router
- Pinia
- Tailwind CSS v4 via `@tailwindcss/vite`

The GitHub Pages root is currently a built Vue preview copied from `frontend/dist` into root-level files:

- `index.html`
- `404.html`
- `assets/`
- `favicon.svg`
- `icons.svg`

Do not edit those generated root deployment files by hand unless you are intentionally patching the GitHub Pages output. Preferred workflow:

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

## Vue Routes Implemented

Public routes:

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

App/admin frontend-only routes:

- `/app/sign-in`
- `/app/pending`
- `/app/dashboard`
- `/app/profile`
- `/app/my-events`
- `/app/forum-feed`
- `/app/admin`
- `/app/approvals`
- `/app/users`
- `/app/events-admin`
- `/app/content-admin`
- `/app/announcements`

These are frontend mockups/scaffolds only. They do not authenticate users, persist data, send email, publish content, or integrate Discourse.

## Important Files

- `frontend/src/router/index.js` - Vue route definitions.
- `frontend/src/pages/` - route page components.
- `frontend/src/components/` - shared public components.
- `frontend/src/data/events.js` - static event seed data.
- `frontend/src/data/startups.js` - static startup seed data.
- `frontend/src/data/partners.js` - static partner seed data.
- `frontend/src/data/forum.js` - static forum preview data.
- `frontend/src/data/platformModel.js` - frontend constants for the backend model vocabulary.
- `frontend/scripts/smoke-routes.mjs` - dependency-free route smoke test.
- `dev-context/PLATFORM_MODEL.md` - canonical backend model contract.

## Verification Commands

Run from `/Users/gg1900/coding/waais-website/frontend`:

```sh
npm run test:routes
npm run build
```

Current expected route smoke output:

```text
Route smoke test passed: 13 route patterns and 24 concrete URLs checked.
```

## Deployment Status

GitHub Pages is configured from:

- Branch: `main`
- Path: `/`

Live preview URL:

`https://cool-machine.github.io/waais-website/`

Useful live routes:

- `https://cool-machine.github.io/waais-website/app/sign-in`
- `https://cool-machine.github.io/waais-website/app/admin`
- `https://cool-machine.github.io/waais-website/events/ai-founder-salon`
- `https://cool-machine.github.io/waais-website/startups/neural-insights`
- `https://cool-machine.github.io/waais-website/partners/cloud-platform`

Note: direct deep links are served through `404.html` as an SPA fallback. GitHub may return HTTP 404 internally for deep links, but the browser should render the Vue app.

## Backend Model Contract

Before Laravel work, follow `dev-context/PLATFORM_MODEL.md`.

Do not use one overloaded `role` field. Use separate fields:

- `approval_status`
- `affiliation_type`
- `permission_role`

The frontend mirrors the same vocabulary in `frontend/src/data/platformModel.js`.

## Recommended Next Step

Start a narrow Laravel backend scaffold.

Suggested first backend slice:

1. Create `/backend/` with Laravel.
2. Add basic test setup.
3. Add migrations/models for:
   - users
   - membership applications/profiles
   - application revisions
   - audit/activity logs
4. Encode the constants from `PLATFORM_MODEL.md`.
5. Add tests for:
   - pending users cannot access member resources
   - only super admins can promote/remove admins
   - rejected applicants can reapply
   - regular members cannot publish public content

Do not implement Google OAuth first. Build the model and access-policy foundation first.

## Known Gaps

- No Laravel backend yet.
- No real Google OAuth.
- No real form submission.
- No persistence.
- No transactional email.
- No admin audit log.
- No Discourse SSO.
- No real CMS publishing workflow.
- The Vue UI still uses static seed data.
- George still needs to provide the final brand/logo asset.
