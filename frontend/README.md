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
