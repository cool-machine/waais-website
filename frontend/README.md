# WAAIS Vue Frontend

Vue 3 public frontend scaffold for the Wharton Alumni AI Studio platform.

This is the first production-frontend pass converted from the static mockups in `../mockups/`. It is not the Laravel backend, does not authenticate users, and does not persist membership applications, event registrations, startup listings, announcements, or admin changes yet.

## Stack

- Vue 3
- Vite
- Vue Router
- Pinia
- Tailwind CSS v4 via `@tailwindcss/vite`

## Commands

```sh
npm install
npm run dev -- --host 127.0.0.1 --port 5174
npm run build
npm run test:routes
```

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
- CMS publishing workflow
- Email and dashboard notifications
- Discourse SSO and forum installation
- Laravel API integration
