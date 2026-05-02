# WAAIS — Dev Context

> Current state of work. Updated at the end of every slice. For the project description, read `PRODUCT.md`. For data/access vocabulary, read `PLATFORM_MODEL.md`.

## Read Order at Start of Session

1. `dev-context/PRODUCT.md` — what we're building (stable)
2. `dev-context/PLATFORM_MODEL.md` — data/access contract
3. `dev-context/DEV_CONTEXT.md` — this file: past / present / future / session log
4. `backend/README.md` — backend validation status and commands
5. `frontend/README.md` — frontend run/build/deploy commands

Project root: `/Users/gg1900/coding/waais-website`

## 1. Past — What's Been Done That We Still Use

> Curated. If a past decision is no longer in force, remove it from this section instead of leaving stale guidance behind. The chronological diary is at the bottom under **Session Log**.

### Repository and structure

- Fresh git repo at `https://github.com/cool-machine/waais-website` on `main`.
- `/legacy/old-react-site/` is local-only reference, ignored by git.
- `/mockups/` — static HTML reference (public site, app/admin/auth, design system). Visual spec only.
- `/frontend/` — Vue 3 + Vite + Tailwind v4 source. GitHub Pages serves `frontend/dist` from the repo root.
- `/backend/` — Laravel 11 source.
- `/dev-context/` — these docs (4 files: PRODUCT, PLATFORM_MODEL, DEV_CONTEXT, STARTER_PROMPT).

### Frontend (live)

- Vue 3 scaffold with public + app/admin routes (see `frontend/src/router/index.js`).
- Public startup directory (`/startups`, `/startups/:id`) and the homepage's "Featured startups" section read from the live Laravel API via a Pinia store.
- Public events calendar (`/events`, `/events/:id`) and the homepage's "Selected events" section read from the live Laravel API via a Pinia store. The list filter calls the public events API with `time = all | upcoming | past`.
- Public partners directory (`/partners`, `/partners/:id`) reads from the live Laravel API via a Pinia store.
- Homepage CMS cards (`what_we_do`, `access_flow`) read from the live Laravel API via a Pinia store, with frontend fallback copy when the CMS is empty.
- Other public surfaces (forum preview) still serve static seed data and will be wired in subsequent slices.
- HTTP client at `frontend/src/lib/api.js` — shared `getJson()` / `sendJson()` wrappers, base URL via `VITE_API_BASE_URL` (default `http://127.0.0.1:8000`), `Accept: application/json`, throws `ApiError` on non-2xx. Public stores stay anonymous by default; authenticated stores pass `auth: true` to send Sanctum session credentials. JSON mutations also send Laravel's `X-XSRF-TOKEN` header when the cookie is present.
- Auth/current-user store at `frontend/src/stores/authUser.js` — calls `/api/user`, treats 401 as signed-out state, exposes approval/permission access getters, and starts Google sign-in by redirecting to `/auth/google/redirect` on the backend.
- Email-link sign-in start is enabled for non-Google applicants. `POST /api/auth/email-link` creates/reuses a pending user, sends a 30-minute signed link through Laravel mail/log, and `/auth/email/callback/{user}` verifies the signature, marks the email verified, logs the user into the browser session, and returns them to the frontend.
- Membership application UI at `/membership` is backed by `frontend/src/stores/membershipApplication.js` and the authenticated Laravel endpoints (`GET/POST/PATCH /api/membership-application`, `POST /api/membership-application/reapply`). Signed-out users can choose Google sign-in or request an email sign-in link; pending/needs-more-info/rejected applicants can submit/update/reapply; approved applications render read-only.
- Member dashboard profile/application status views at `/app/dashboard` and `/app/profile` consume `useAuthUserStore` plus `useMembershipApplicationStore` for live account, profile, and application status. `/app/dashboard` also consumes `frontend/src/stores/memberAnnouncements.js` for published member-visible announcements. Other member surfaces (`/app/my-events`, `/app/forum-feed`) remain queued.
- Member dashboard startup ownership view at `/app/my-startups` consumes `frontend/src/stores/myStartups.js`, backed by authenticated member endpoints (`GET/POST/PATCH /api/startup-listings`). Approved members can submit new listings and update non-approved listings; approved listings render as not editable.
- Admin dashboard membership approvals view at `/app/approvals` consumes `frontend/src/stores/adminMembershipApplications.js`, backed by authenticated admin endpoints (`GET /api/admin/applications`, `GET /api/admin/applications/{id}`, and approve/reject/request-info transitions). It is the canonical live admin review surface for membership applications.
- Admin dashboard startup-listing review view at `/app/startup-review` consumes `frontend/src/stores/adminStartupListings.js`, backed by authenticated admin endpoints (`GET /api/admin/startup-listings`, `GET /api/admin/startup-listings/{id}`, and approve/reject/request-info transitions). It mirrors the membership queue but stays separate from public/member startup stores.
- Admin dashboard event management view at `/app/events-admin` consumes `frontend/src/stores/adminEvents.js`, backed by authenticated admin endpoints (`GET /api/admin/events` filterable by `content_status`, `GET /api/admin/events/{id}`, `POST /api/admin/events` create, `PATCH /api/admin/events/{id}` update, plus `publish`/`hide`/`archive`/`cancel` transitions). The store auto-removes events from the active filter when their `content_status` no longer matches and supports an `all` content_status filter that keeps everything in view.
- Admin dashboard public content view at `/app/content-admin` consumes `frontend/src/stores/adminPublicContent.js`, backed by authenticated admin endpoints for `/api/admin/homepage-cards` and `/api/admin/partners`. Admins can switch resource families, filter by content status, create/edit records, and publish/hide/archive them without touching code.
- Admin dashboard announcements view at `/app/announcements` consumes `frontend/src/stores/adminAnnouncements.js`, backed by authenticated admin endpoints for `/api/admin/announcements`. Admins can filter by content status/audience, create/edit drafts, and publish/hide/archive announcements. Published announcements appear in the member dashboard through `/api/announcements`.
- Admin dashboard user directory view at `/app/users` consumes `frontend/src/stores/adminUsers.js`, backed by authenticated admin endpoints (`GET /api/admin/users` filterable by `permission_role`, `approval_status`, `affiliation_type`, and free-text `q`; `GET /api/admin/users/{user}`) plus the existing super-admin role transitions. Promote/demote buttons are gated to super admins via `auth.user.can_manage_admin_privileges`; regular admins see profile context only. Self-role changes are blocked client-side and the last-super-admin guard surfaces on `saveError` when the backend returns 409.
- Auth UI now has mutually exclusive states: signed-out users see "Sign in with Google" and disabled future "Sign in with email"; signed-in users see account context plus "Sign out". Backend `POST /api/logout` clears browser-session auth when present and the frontend clears authenticated stores after logout.
- Pinia store at `frontend/src/stores/publicStartups.js` — `loadList`, `loadOne`, in-memory TTL cache so back-navigation between list and detail doesn't refetch. Convention for adding future stores (one per backend resource × access surface) is documented in `frontend/src/stores/README.md`.
- Vitest + @vue/test-utils + jsdom configured in `frontend/vitest.config.js`. Specs live next to source as `*.test.js`. `npm test` runs them. Current coverage: 141 specs across `pages/AppMockupPage`, `pages/MembershipPage`, `lib/api`, `stores/authUser`, `stores/membershipApplication`, `stores/memberAnnouncements`, `stores/myStartups`, `stores/adminMembershipApplications`, `stores/adminStartupListings`, `stores/adminEvents`, `stores/adminPublicContent`, `stores/adminAnnouncements`, `stores/adminUsers`, `stores/publicStartups`, `stores/publicEvents`, `stores/publicPartners`, and `stores/publicHomepageCards`.
- Build deployed to GitHub Pages via root-level `index.html`, `404.html`, `assets/`, `favicon.svg`, `icons.svg` copied from `frontend/dist`. Deploy steps live in `frontend/README.md`.

### Backend (live, validated locally)

- Local PHP 8.5.5 / Composer 2.9.7 working. `composer.json` pins Composer platform PHP to `8.3.0` to keep the lockfile compatible with the documented PHP 8.3+ target.
- Laravel 11 scaffold with WAAIS enums (`ApprovalStatus`, `AffiliationType`, `PermissionRole`, `ContentStatus`, `ContentVisibility`).
- User model with access helpers: `isPending`, `isMember`, `isAdmin`, `isSuperAdmin`, `canAccessMemberAreas`, `canPublishPublicContent`, `canManageAdminPrivileges`.
- Migrations for users (with `google_id`, `approval_status`, `affiliation_type`, `permission_role`, plus `approved_at`/`rejected_at`/`suspended_at` timestamps), membership applications, application revisions, audit logs, personal access tokens, cache, jobs.
- Sanctum API auth: `/api/user` returns access flags; `member.access` middleware on `/api/member/*`. Local SPA auth is backed by credentialed CORS config for the Vite dev origins and Sanctum stateful domains (`localhost:5174`, `127.0.0.1:5174`).
- Google OAuth via Socialite: `/auth/google/redirect` and `/auth/google/callback`. New users → `submitted` / `pending_user`. Existing unlinked users link by email. Approved members are not downgraded on re-sign-in. Email already linked to a different `google_id` returns 409. The redirect route accepts a safe relative `next` path so flows such as Membership can return to `/membership` after Google instead of always landing on the app pending/dashboard mockup.
- Email-link auth: `POST /api/auth/email-link` validates an email and optional safe relative `next` path, creates/reuses a user without downgrading approved members/admins, sends `EmailSignInLink`, and returns `{ ok: true }`. Signed callback `/auth/email/callback/{user}` is protected by Laravel's `signed` middleware and redirects to the safe frontend path after `Auth::login()`.
- DiscourseConnect SSO relay: `GET /discourse/sso` validates Discourse's signed `sso` payload with `DISCOURSE_CONNECT_SECRET`, requires an approved member/admin session, and returns a signed nonce/email/external_id/name payload to Discourse. Guest requests are preserved in session and resumed after Google login; pending users redirect to `/app/pending`. WAAIS admins map to Discourse moderator + `waais_admins`; WAAIS super admins map to Discourse admin.
- Applicant-owned membership application API: `GET/POST/PATCH /api/membership-application`, `POST /api/membership-application/reapply`. Rejected applicants can reapply. Field changes write `application_revisions` rows.
- Admin membership-application review API: `admin.access` middleware backed by `User::isAdmin()`. Routes under `/api/admin/applications`: queue (filterable by `status`), single-application detail with revisions, approve, reject, request-info. Approve promotes pending applicants to Member without downgrading existing Admin/SuperAdmin and syncs `affiliation_type` from the application. Reject and request-info both require `review_notes`. Each transition writes one `AuditLog` row capturing application + applicant before/after state plus IP and user-agent. This is the canonical implementation of the **Submission & Admin Review Pattern** (described in `PRODUCT.md`).
- Member-side startup-listing API: approved members only via `member.access`. Routes under `/api/startup-listings`: list own, show own, create, update. Submission stamps `approval_status = submitted` and `content_status = pending_review`. Owner cannot show or edit another member's listing. Approved listings cannot be self-edited (returns 409). Edits re-submit and clear reviewer fields. `startup_listing_revisions` rows are written on submit and update with the changed-fields diff.
- Admin startup-listing review API: routes under `/api/admin/startup-listings` mirror the membership review shape (queue filterable by `status`, show with revisions, approve, reject, request-info). Approve sets `approval_status = approved` + `content_status = published` + `approved_at`. Reject sets `approval_status = rejected` + `content_status = hidden` + `rejected_at` and requires `review_notes`. Request-info sets `approval_status = needs_more_info` + `content_status = draft` and requires `review_notes`. Each transition writes one `AuditLog` row keyed on `StartupListing` with before/after state plus IP and user-agent. `ContentVisibility` defaults to `public`.
- Email notifications wired through Laravel's `Notification` system on the `mail` channel, fired after the DB transaction commits so a failed save never produces a stray email. Surfaces (mirrored across membership applications and startup listings): submitter thank-you on submission/reapply (not on edit), admin "new submission in queue" notice sent to all approved Admins/SuperAdmins via the `User::admins()` query scope, approval email, request-more-info email, and an opt-in rejection email gated by a `send_email` boolean on the reject endpoint payload (default false). All five notification classes per surface live under `App\Notifications\*`. Each notification carries the underlying model (`MembershipApplication` / `StartupListing`); the mail body uses the applicant first name or owner display name, includes reviewer notes when present, and points back to the relevant dashboard URL via `config('app.url')`. Local dev uses `MAIL_MAILER=log`; production target is Azure Communication Services Email over SMTP via the `azure_communication_services` mailer.
- Events backend: admin-managed content (no Submission & Admin Review pattern). `events` table has `content_status`/`visibility` plus event-specific fields (`starts_at`, `ends_at`, `location`, `format`, `image_url`, `registration_url`, `capacity_limit`, `waitlist_open`, `recap_content`, `reminder_days_before` default 2, `cancelled_at`, `cancellation_note`). Admin endpoints under `/api/admin/events` (index filterable by `content_status`/`visibility`/`time`, store, show, update, publish, hide, archive, cancel) audit-log every state-changing action. Cancellation is a separate axis from `content_status`: cancelled events stay visible to admins but are filtered from every public surface. Public read API at `/api/public/events` (index + show) filters to `content_status = published` AND `visibility IN (public, mixed)` AND `cancelled_at IS NULL`, with `time = upcoming|past|all` (default `upcoming`) and an explicit allowlist projection that adds a derived `status` ("upcoming" / "past" / "recap").
- Event reminder dispatch: scheduled `events:send-reminders` command runs daily at 09:00. It finds published, non-cancelled events whose `starts_at` date matches today + `reminder_days_before`, sends `EventReminder` emails to approved verified members/admins/super-admins, and records `event_reminder_deliveries` by event/user/start time for idempotent reruns. There is still no internal RSVP attendee list; v1 reminders are a member/admin event fan-out.
- Partners backend: admin-managed content (no Submission & Admin Review pattern). `partners` table has `content_status`/`visibility`, lifecycle timestamps, `name`, `partner_type`, `summary`, `description`, `website_url`, `logo_url`, and `sort_order`. Admin endpoints under `/api/admin/partners` (index filterable by `content_status`/`visibility`, store, show, update, publish, hide, archive) audit-log every state-changing action. Public read API at `/api/public/partners` (index + show) filters to `content_status = published` AND `visibility IN (public, mixed)` with an explicit allowlist projection.
- Homepage CMS cards backend: admin-managed content (no Submission & Admin Review pattern). `homepage_cards` table has `content_status`/`visibility`, lifecycle timestamps, `section`, `eyebrow`, `title`, `body`, optional link fields, and `sort_order`. Admin endpoints under `/api/admin/homepage-cards` (index filterable by `section`/`content_status`/`visibility`, store, show, update, publish, hide, archive) audit-log every state-changing action. Public read API at `/api/public/homepage-cards` (index + show) filters to `content_status = published` AND `visibility IN (public, mixed)` with an explicit allowlist projection.
- Announcements backend: admin-managed content (no Submission & Admin Review pattern). `announcements` table has `content_status`/`visibility`, lifecycle timestamps, `audience`, `channel`, title/body fields, and optional action link fields. Admin endpoints under `/api/admin/announcements` (index filterable by `content_status`/`visibility`/`audience`, store, show, update, publish, hide, archive) audit-log every state-changing action. Member read API at `/api/announcements` is gated by `member.access`, filters to published member-visible announcements, includes admin-only announcements only for admin/super-admin users, and uses an explicit allowlist projection.
- Super-admin role-management API: `super_admin.access` middleware backed by `User::canManageAdminPrivileges()`. Routes under `/api/admin/users/{user}` for `promote-admin`, `demote-admin`, `promote-super-admin`, `demote-super-admin`. Each transition is a row-locked update with strict from/to role guards (returns 409 on role mismatch). `promote-admin` additionally requires the target to be `approval_status = approved`. `demote-super-admin` is blocked when the target is the last `SuperAdmin` in the system (covers self-demotion and any path that would empty the role). Every transition writes one `AuditLog` row keyed on `User` with `role.promote_admin` / `role.demote_admin` / `role.promote_super_admin` / `role.demote_super_admin` plus before/after `permission_role` plus IP and user-agent.
- Admin user directory API: `admin.access` middleware. Routes under `/api/admin/users` for `index` (filterable by `permission_role`, `approval_status`, `affiliation_type`, free-text `q` across name/email/first_name/last_name/display_name; paginated, default `per_page = 25` capped at 100) and `/api/admin/users/{user}` for `show`. Both use an explicit allowlisted projection (`id`, `name`, `first_name`, `last_name`, `display_name`, `email`, `email_verified_at`, `avatar_url`, `approval_status`, `affiliation_type`, `permission_role`, `approved_at`, `rejected_at`, `suspended_at`, `created_at`). Internal fields (`password`, `remember_token`, `google_id`) are intentionally never exposed; drift is enforced by `AdminUserApiTest::projection_excludes_internal_fields`.
- Public read API for startup listings: anonymous (no auth) routes under `/api/public/startup-listings` (index, paginated) and `/api/public/startup-listings/{listing}` (show). Both filter strictly to `content_status = published` AND `visibility = public`; anything else is invisible (404 on show). The response uses an explicit allowlist projection: `id`, `name`, `tagline`, `description`, `website_url`, `logo_url`, `industry`, `stage`, `location`, `founders`, `linkedin_url`, `approved_at` (ISO 8601). Internal fields — `review_notes`, `submitter_role`, `owner_id`, `reviewed_by`, `reviewed_at`, `submitted_at`, `rejected_at`, `approval_status`, `content_status`, `visibility`, `revisions`, `created_at`, `updated_at` — never appear, enforced by a denylist test. Default `per_page = 12`, capped at 48.
- Test suite: 177 passing / 775 assertions after the event reminder dispatch slice. `php artisan migrate:fresh` passes against local SQLite.

### Production database decision

- Local dev/test: SQLite (in-memory for tests, file for dev).
- Staging / production: Azure Database for PostgreSQL Flexible Server.
- Migrations and queries should stay portable. No Postgres-only or MySQL-only SQL.

## 2. Present — Current Slice

No slice in progress. Event reminder dispatch shipped on May 2, 2026 at 22:05 CEST and merged to `main`.

## 3. Future — Ordered Next Slices

1. **Announcement email dispatch.** The announcements model stores `channel = email_dashboard`, but this slice only publishes dashboard announcements; actual email fan-out is still queued.
2. **Forum feed/public teaser wiring.** Discourse SSO is implemented, but forum content still needs Discourse API integration once the forum is provisioned.
3. **Brand/logo asset replacement** when George provides it.
4. **Azure deployment** of app + backend, plus Discourse on Azure VM.

## Working Rules

- Small slices, one concern per branch.
- After every code slice: `composer validate --strict`, `php artisan test`, `php artisan migrate:fresh` — all must pass before commit.
- Update `DEV_CONTEXT.md` (and `STARTER_PROMPT.md` whenever the next-slice direction shifts) in the same commit as the code.
- Commit → push branch → merge to `main` → push `main` at the end of every slice.
- Local dev/test stays on SQLite. Production target is Postgres on Azure. Don't introduce Postgres-only or MySQL-only SQL in migrations or queries.
- If a slice would need human visual or manual testing to verify, stop and tell the user before continuing.

## Session Log

> Newest entry at the top. Each entry: date, what was done, what was left, watch-outs.

**May 2, 2026 22:05 CEST — Event reminder dispatch (shipped)**
- Did: added `event_reminder_deliveries` to track per-event/per-user reminder sends by event start time, keeping reruns idempotent while allowing a moved event to send again for the new date
- Did: added `EventReminder` mail notification and `events:send-reminders`, scheduled daily at 09:00 with `withoutOverlapping()`
- Did: the command sends reminders to approved, verified members/admins/super-admins for published, non-cancelled events whose `starts_at` date matches today + `reminder_days_before`
- Did: added `EventReminderDispatchTest`; validation passed at `php artisan test` 177 tests / 775 assertions, `composer validate --strict`, and `php artisan migrate:fresh`
- Left off at: ready for the next slice — announcement email fan-out for announcements with `channel = email_dashboard`
- Watch out for: there is still no internal RSVP/attendee table. Until that lands, event reminders are a member/admin fan-out rather than attendee-specific registration reminders.

**May 2, 2026 21:47 CEST — DiscourseConnect SSO relay (shipped)**
- Did: added `GET /discourse/sso`, validating DiscourseConnect `sso` + `sig` with `DISCOURSE_CONNECT_SECRET` and rejecting invalid signatures or non-Discourse return URLs
- Did: added approved-member/admin payload generation with stable `external_id`, verified email, display name, sanitized username, `waais_members`/`waais_admins` groups, and admin/moderator flags
- Did: unauthenticated SSO requests are preserved in session and resumed after Google login; pending users are redirected to the pending page instead of receiving forum access
- Did: added `.env.example` entries and backend docs for `DISCOURSE_URL`, `DISCOURSE_CONNECT_SECRET`, and the Discourse provider URL
- Did: added `DiscourseSsoTest`; validation passed at `php artisan test` 172 tests / 752 assertions
- Left off at: ready for the next slice — event reminder dispatch, announcement email fan-out, or forum feed/public teaser wiring after Discourse is provisioned
- Watch out for: Discourse must be configured separately with the same connect secret and provider URL. Group/admin sync requires matching Discourse settings for DiscourseConnect group/admin overrides.

**May 2, 2026 21:40 CEST — Announcements backend + admin/member surfaces (shipped)**
- Did: added `announcements` backend table/model plus admin endpoints under `/api/admin/announcements` for index/show/create/update/publish/hide/archive with audit logs
- Did: added member read endpoints under `/api/announcements`, gated by `member.access`, with a strict published/visible/audience-filtered projection
- Did: added `frontend/src/stores/adminAnnouncements.js` and `frontend/src/stores/memberAnnouncements.js`
- Did: replaced the static `/app/announcements` mock with a live admin announcement manager and added a published announcements panel to the member dashboard
- Did: added backend and frontend coverage; validation passed at `php artisan test` 166 tests / 728 assertions and frontend coverage is now 141 specs
- Left off at: ready for the next slice — Discourse SSO relay, event reminder dispatch, or announcement email fan-out
- Watch out for: announcements can store `channel = email_dashboard`, but this slice only surfaces dashboard announcements. Email fan-out should be a separate queued job/notification slice.

**May 2, 2026 18:30 CEST — Admin dashboard public content management (shipped)**
- Did: repaired stale handoff/product docs around email-link auth, Azure Communication Services Email, shipped startup review, and admin CMS/user-management frontend state
- Did: added `frontend/src/stores/adminPublicContent.js`, covering authenticated admin list/detail/create/update/publish/hide/archive flows for `/api/admin/homepage-cards` and `/api/admin/partners`
- Did: replaced the static `/app/content-admin` placeholder with a live admin surface for switching between homepage cards and partners, filtering by content status, editing resource-specific fields, and moving items through publish/hide/archive transitions
- Did: added frontend coverage for the new store plus `/app/content-admin`; validation passed at `npm test` 135 specs, `npm run build`, `npm run test:routes`, `composer validate --strict`, `php artisan test` 153 tests / 662 assertions, and `php artisan migrate:fresh`
- Left off at: ready for the next slice — announcements backend plus admin/member surfaces
- Watch out for: `/app/content-admin` manages existing backend resources only. Announcements still needs a fresh backend migration/model/API before frontend wiring.

**May 2, 2026 18:15 CEST — /app/users heading copy fix (shipped)**
- Did: tightened the `/app/users` hero heading from "Search the membership and adjust roles." to "Search the members and adjust roles." after George flagged the wording during the live browser smoke
- Did: updated the matching `pages/AppMockupPage.test.js` assertion. Frontend validation passed at `npm test` 128 specs, `npm run build`, `npm run test:routes`. Backend untouched
- Did: refreshed root-level GitHub Pages build artifacts from `frontend/dist`
- Did: added `backend/database/seeders/LocalSmokeUsersSeeder.php` (idempotent, opt-in, not in the production seed chain) so future local smokes can re-seed George + a varied user fixture set with `php artisan db:seed --class=LocalSmokeUsersSeeder --force`
- Left off at: ready for the next slice — public content (homepage cards + partners) or announcements

**May 2, 2026 18:05 CEST — Admin dashboard user directory (shipped)**
- Did: added backend `App\Http\Controllers\Api\Admin\AdminUserController` with `index` (filterable by `permission_role`, `approval_status`, `affiliation_type`, free-text `q` across name/email/first_name/last_name/display_name; paginated, default `per_page = 25` capped at 100) and `show`. Both routes registered under `/api/admin/users` and `/api/admin/users/{user}` inside the existing `admin.access` group, sitting next to the super-admin role-management routes
- Did: response uses an explicit allowlisted projection (`id`, `name`, `first_name`, `last_name`, `display_name`, `email`, `email_verified_at`, `avatar_url`, `approval_status`, `affiliation_type`, `permission_role`, `approved_at`, `rejected_at`, `suspended_at`, `created_at`). Internal fields (`password`, `remember_token`, `google_id`) are intentionally never serialized. Drift is enforced by `AdminUserApiTest::projection_excludes_internal_fields`
- Did: added 10 backend tests in `AdminUserApiTest` covering admin/super-admin gating, pagination metadata, role filter, approval-status filter, search-by-email, per_page validation bounds, single user show, and the projection denylist
- Did: added `frontend/src/stores/adminUsers.js`, backed by the new authenticated admin endpoints plus the existing role transitions (promote/demote admin and super_admin). The store keeps a partial-merge `applyUpdates()` helper so the role-transition payload (which is a smaller projection) doesn't drop wider listing fields like `created_at`. Filter sentinels (`all`) are stripped at the wire boundary
- Did: rewired `/app/users` from a static directory mockup to a live admin user surface. Approved admins can filter by role and approval status, run a free-text search across name/email, and select a user to see profile context. Promote/demote buttons render only when the current viewer has `can_manage_admin_privileges`; regular admins see profile context only. Self-role changes are blocked from the same screen via `isCurrentAuthUser`
- Did: refreshed the admin nav and `signOut()`/`loadMemberDashboard()` to wire `useAdminUsersStore`. Added 10 store specs and 2 page specs (queue render + super-admin promote-to-admin flow). Frontend validation passed at `npm test` 128 specs, `npm run build`, and `npm run test:routes`
- Did: required backend validation still passed: `composer validate --strict`, `php artisan test` 153 passed / 662 assertions, and `php artisan migrate:fresh`
- Did: refreshed root-level GitHub Pages build artifacts from `frontend/dist`
- Left off at: ready for the next slice — public content admin (homepage cards + partners) or announcements
- Watch out for: super-admin role-management endpoints still return a smaller partial projection (`id`, `name`, `email`, `approval_status`, `permission_role`, `affiliation_type`). The store merges those into the listing row, but if a future field added to the directory needs to round-trip through a role transition, either widen the role-controller projection or have the page refresh the row via `loadOne(id)` afterward. Also: `confirm()` is used as a guard before promote/demote; if a future modal-driven UI replaces it, update the corresponding test stub (`vi.spyOn(window, 'confirm')`)

**May 2, 2026 17:55 CEST — Admin dashboard event management (shipped)**
- Did: added `frontend/src/stores/adminEvents.js`, backed by authenticated admin event endpoints (`GET /api/admin/events`, `GET /api/admin/events/{id}`, `POST /api/admin/events` create, `PATCH /api/admin/events/{id}` update, `POST publish`/`hide`/`archive`/`cancel`). Store supports content_status filter (default `draft`, plus `all` keeps everything in view), pagination metadata, list cache invalidation on `force`, validation-error state, and a single `applyUpdated()` helper that adds, replaces, or removes the affected event from the active filter slice
- Did: rewired `/app/events-admin` from the static mockup to a live admin event management surface. Approved admins can filter by content_status, refresh the queue, start a new event, edit an existing one, set visibility/capacity/reminder/registration URL/recap fields, and run `Save`, `Publish`, `Hide`, `Archive`, or `Cancel` transitions with an optional cancellation note. Pending/non-admin users see an explicit access-required state instead of a disabled form
- Did: kept this store separate from `usePublicEventsStore`; admin event projections never share state with the anonymous public events store
- Did: updated `loadMemberDashboard()` to load events when the admin lands on `/app/admin` or `/app/events-admin`, and made `signOut()` clear the new store
- Did: replaced the static "Published events: 12" admin metric with a live `Events in view` count
- Did: added 14 store tests (`stores/adminEvents.test.js`) and 2 page tests (`pages/AppMockupPage.test.js`) covering list+filter, status filter, save POST/PATCH branching, validation-error capture, publish removes from draft queue, publish keeps in queue when filter is `all`, cancel with/without note, hide and archive endpoints, startNew/clear. Frontend validation passed at `npm test` 116 specs, `npm run build`, and `npm run test:routes`
- Did: required backend validation still passed: `composer validate --strict`, `php artisan test` 143 passed / 628 assertions, and `php artisan migrate:fresh`
- Did: refreshed root-level GitHub Pages build artifacts from `frontend/dist`
- Left off at: ready for the next slice — the next admin dashboard surface (user management, public content, or announcements)
- Watch out for: events use `content_status` (admin-managed) instead of `approval_status`, so the admin queue defaults to `draft` rather than `submitted`. Cancellation is a separate axis from `content_status`: cancelled events still appear in the admin queue but are filtered from every public surface. Datetime input uses `datetime-local` and converts to ISO at the wire boundary; if a future slice introduces explicit timezone handling for international events, replace the in-page conversion helpers (`toLocalDateTimeInput`, `localDateTimeToIso`) with timezone-aware logic. The `publish_at` / `hidden_at` / `archived_at` timestamps are written by the backend on transition; the frontend reads `cancelled_at` and `content_status` only

**May 2, 2026 16:15 CEST — Email-link application start (shipped)**
- Did: added backend email-link auth start: `POST /api/auth/email-link` creates/reuses a pending user without downgrading approved users, sends an `EmailSignInLink` notification, and returns `{ ok: true }`
- Did: added signed email callback `/auth/email/callback/{user}` protected by Laravel's `signed` middleware. It marks the email verified, logs the user into the browser session, and redirects to the safe frontend `next` path, defaulting to `/membership`
- Did: enabled the public `/membership` email start form and the app sign-in email form. Both call `useAuthUserStore.requestEmailSignIn()`
- Did: added backend `EmailAuthTest`, frontend `MembershipPage.test.js`, and auth-store coverage
- Did: smoked the full chain locally over the running dev servers. `POST /api/auth/email-link` returned `{ ok: true }`; the signed callback URL pulled from `backend/storage/logs/laravel.log` returned `302 -> http://127.0.0.1:5174/waais-website/membership` and set the `laravel-session` cookie; a follow-up `GET /api/user` with that cookie returned the authenticated pending user
- Did: refreshed root-level GitHub Pages build artifacts from `frontend/dist` (`index.html`, `404.html`, `assets/`, `favicon.svg`, `icons.svg`)
- Did: full automated validation green: frontend `npm test` 100 specs, `npm run build`, `npm run test:routes`; backend `composer validate --strict`, `php artisan test` 143 passed / 628 assertions, and `php artisan migrate:fresh`
- Left off at: ready for the next slice — another admin dashboard surface (user management, event management, public content, or announcements)
- Watch out for: local email uses `MAIL_MAILER=log`; the signed link appears in `backend/storage/logs/laravel.log`. The link expires in 30 minutes and uses `APP_URL`, so local smoke expects Laravel on `http://127.0.0.1:8000`. When smoking with curl, the SPA path adds an `Origin` header that switches Sanctum into stateful mode and requires `X-XSRF-TOKEN`; the SPA's `sendJson()` wrapper handles that, so the route works in-browser even though a bare `Origin`-bearing curl POST returns CSRF mismatch

**May 2, 2026 15:46 CEST — Admin dashboard startup-listing review wiring**
- Did: added `frontend/src/stores/adminStartupListings.js`, backed by authenticated admin startup-listing endpoints (`GET /api/admin/startup-listings`, `GET /api/admin/startup-listings/{id}`, `POST approve`, `POST reject`, `POST request-info`) with list pagination metadata, selected detail loading, transition actions, validation-error state, and queue removal after terminal status changes
- Did: added `/app/startup-review` as a live admin startup review surface. Approved admins can filter by status, select a listing, review owner/listing context, add review notes, approve, request more info, reject, and choose whether to send the optional rejection email
- Did: kept this store separate from `usePublicStartupsStore` and `useMyStartupsStore`; startup public/member/admin projections remain isolated
- Did: updated membership and startup admin queue stores so records that leave the active filter after approve/reject/request-info no longer remain open in the submitted review form; the detail pane clears or advances to the next item
- Did: added focused store and component tests for queue loading, approve transition, and post-transition queue behavior. Frontend validation passed at `npm test` 98 specs, `npm run build`, and `npm run test:routes`
- Did: required backend validation still passed: `composer validate --strict`, `php artisan test` 139 passed / 612 assertions, and `php artisan migrate:fresh`
- Did: manually smoked `/app/startup-review` locally. George saw `AutoFlow AI`, approved it, then confirmed it moved out of the submitted queue
- Left off at: ready for the next slice — email-link application start for non-Google applicants, or another admin dashboard surface
- Watch out for: approved startup listings move to the `Approved` filter; they are not deleted. `php artisan migrate:fresh` resets local SQLite data, so reseed an approved admin and submitted startup if continuing manual browser checks

**May 2, 2026 15:29 CEST — Admin dashboard membership approvals queue + sign-out**
- Did: added `frontend/src/stores/adminMembershipApplications.js`, backed by authenticated admin membership endpoints (`GET /api/admin/applications`, `GET /api/admin/applications/{id}`, `POST approve`, `POST reject`, `POST request-info`) with list pagination metadata, selected detail loading, transition actions, validation-error state, and queue removal after terminal status changes
- Did: rewired `/app/approvals` from static mock rows to the live admin membership application queue. Approved admins can filter by status, select an applicant, review affiliation/application context, add review notes, approve, request more info, reject, and choose whether to send the optional rejection email
- Did: added `POST /api/logout`, `useAuthUserStore.signOut()`, and a mutually-exclusive auth UI: signed-out state shows Google/email sign-in options; signed-in state shows sign-out only and hides the Auth nav group
- Did: kept this as a membership-only admin surface. Startup listing review remains separate so member/public/startup stores stay isolated from admin review projections
- Did: added store/component/auth tests for queue loading, approve transition, logout, signed-out auth choices, and signed-in sign-out-only state. Frontend validation passed at `npm test` 88 specs, `npm run build`, and `npm run test:routes`
- Did: required backend validation still passed: `composer validate --strict`, `php artisan test` 139 passed / 612 assertions, and `php artisan migrate:fresh`
- Left off at: ready for the next slice — admin dashboard startup-listing review wiring using the same admin queue pattern against `/api/admin/startup-listings`
- Watch out for: `php artisan migrate:fresh` resets local SQLite data. Reseed `cool.lstm@gmail.com` as admin plus a submitted application if continuing manual browser checks. George confirmed Ada Lovelace rendered before the final sign-out simplification; hard-refresh and click sign-out once more after pulling/serving the final build

**May 2, 2026 13:00 CEST — Member dashboard startup ownership**
- Did: added `frontend/src/stores/myStartups.js`, backed by authenticated member startup endpoints (`GET/POST/PATCH /api/startup-listings`) with list caching, select-new/select-existing behavior, create, update, and error state
- Did: wired `/app/my-startups` into the member dashboard nav. Approved members can submit a startup listing, see owned listings with review status, select one into the form, and update non-approved listings. Pending/non-approved users see an explicit approval-required card instead of a disabled form
- Did: preserved `usePublicStartupsStore` as an anonymous public directory store; pending member-owned listings remain hidden from `/startups` until admin approval publishes them
- Did: added store tests and component tests for member-owned listing render/select and pending-user approval-required state. Frontend validation passed at `npm test` 76 specs, `npm run build`, and `npm run test:routes`
- Did: required backend validation still passed: `composer validate --strict`, `php artisan test` 138 passed / 610 assertions, and `php artisan migrate:fresh`
- Did: manually smoked the visible dashboard flow locally. Approved-member startup submission/update worked, and the submitted listing did not appear in the public startup directory
- Left off at: ready for the next slice — admin dashboard approvals queue wiring, starting with membership/startup applications
- Watch out for: startup URL fields are optional, but browser URL inputs require complete URLs with `https://` if filled. This is native browser validation, not a backend error

**May 2, 2026 — Member dashboard profile/application status**
- Did: rewired `/app/dashboard` to show live account status, membership-application status, affiliation, permission role, and a profile snapshot from `useAuthUserStore` plus `useMembershipApplicationStore`
- Did: rewired `/app/profile` to show live identity and application summary fields instead of static mock profile data
- Did: left `/app/my-events`, `/app/forum-feed`, member startup ownership, and all admin dashboard views as future slices so this slice stays scoped to profile/application status
- Did: added component coverage in `frontend/src/pages/AppMockupPage.test.js`; frontend validation passed at `npm test` 69 specs, `npm run build`, and `npm run test:routes`
- Did: required backend validation still passed: `composer validate --strict`, `php artisan test` 138 passed / 610 assertions, and `php artisan migrate:fresh`
- Did: manually smoked the visible dashboard flow locally. `/app/dashboard` and `/app/profile` rendered authenticated account/application fields, and the membership edit link returned to `/membership`
- Left off at: ready for the next slice — member dashboard startup ownership wiring with `useMyStartupsStore`
- Watch out for: `php artisan migrate:fresh` resets local SQLite data before browser smoke. Re-sign in and resubmit the membership form if you need populated dashboard application fields in local dev

**May 2, 2026 — Membership application UI**
- Did: extended the frontend HTTP client with `sendJson()` for authenticated JSON mutations, preserving anonymous public fetch behavior and adding Laravel `X-XSRF-TOKEN` forwarding for mutating requests
- Did: added `frontend/src/stores/membershipApplication.js` for applicant-owned `GET/POST/PATCH /api/membership-application` and `POST /api/membership-application/reapply`
- Did: rewired `/membership` from a static questionnaire to an authenticated application form. Signed-out visitors see one membership application card with "Continue with Google" active and "Start with email" disabled for a future email-link auth slice; signed-in applicants can submit/update; rejected applicants use reapply; needs-more-info/rejected states show admin review notes; approved applications render read-only
- Did: added safe `next` handling to Google OAuth so membership sign-in returns to `/membership`, added credentialed CORS config for the local Vite origins, widened Sanctum's default local stateful domains, and made unauthenticated API browser requests return JSON 401 instead of trying to redirect to a missing `login` route
- Did: pinned `FRONTEND_URL` in `backend/phpunit.xml` so backend Google OAuth tests stay stable even when local `.env` uses the GitHub Pages/Vite base path for manual browser testing
- Did: added Vitest coverage for `sendJson()` and the membership application store plus backend regression tests for API unauthenticated behavior and Google OAuth intended paths. Automated validation: frontend `npm test` 67 passed, `npm run build` clean, `npm run test:routes` clean; backend `composer validate --strict` clean, `php artisan test` 138 passed / 610 assertions, and `php artisan migrate:fresh` clean
- Did: manually smoked the membership flow locally. Google sign-in returned to `/membership`, the authenticated form appeared, and submitting the form saved successfully with submitted status
- Left off at: ready for the next slice — member dashboard frontend wiring
- Watch out for: local dev commands should use `PATH=/opt/homebrew/bin:$PATH ...` in this shell so Node/PHP/Composer resolve to the Homebrew toolchain. Local `.env` should include `FRONTEND_URL=http://127.0.0.1:5174/waais-website`, `SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5174,127.0.0.1,127.0.0.1:5174`, and a non-conflicting `SESSION_COOKIE` such as `waais_session` when manually testing SPA auth

**May 1, 2026 — Sanctum auth frontend + Google sign-in UI flow**
- Did: extended `frontend/src/lib/api.js` so public requests remain anonymous by default while authenticated requests can pass `auth: true` to include Sanctum session credentials. Added backend Google OAuth URL construction and a `redirectToGoogleSignIn()` helper that sends users to `/auth/google/redirect`
- Did: added `frontend/src/stores/authUser.js` with `loadCurrentUser()`, access-model getters, 401-as-signed-out behavior, and `startGoogleSignIn()`
- Did: wired `/app/sign-in`, `/app/pending`, and `/app/dashboard` to the auth store. The sign-in button now starts the backend-owned Google flow; pending/dashboard views reflect the current `/api/user` session where available
- Did: added Vitest coverage for credential handling, Google redirect URL construction, current-user loading, anonymous 401 handling, error handling, and auth-store caching. Updated `frontend/README.md` and `frontend/src/stores/README.md`
- Validation so far: after forcing Homebrew Node onto `PATH`, `npm test` passed at 54 specs, `npm run build` passed, and `npm run test:routes` passed. Backend validation required by project rules also passed: `composer validate --strict`, `php artisan test` 136 passed / 604 assertions, and `php artisan migrate:fresh`
- Did: manually verified the local browser OAuth flow with Laravel on `127.0.0.1:8000` and Vite on `127.0.0.1:5174`; Google sign-in redirected to `/app/pending` and displayed "Your account is awaiting approval"
- Left off at: ready for the next slice — membership application UI on the public site
- Watch out for: running `npm` without Homebrew Node first on `PATH` can pick the Codex app Node and fail on Vite/Vitest's `rolldown` native binding with a Team ID code-signature error. Use `PATH=/opt/homebrew/bin:$PATH npm ...` locally if that happens

**May 1, 2026 — Email provider selection/config**
- Did: selected Azure Communication Services Email over SMTP as the production transactional email target. Rationale: WAAIS is already targeting Azure infrastructure, ACS Email supports SMTP and custom verified domains, and Laravel already supports SMTP via Symfony Mailer without adding a provider-specific package
- Did: added a named `azure_communication_services` mailer in `backend/config/mail.php` with SMTP defaults (`smtp.azurecomm.net`, port `587`) and separate `ACS_MAIL_*` environment variables
- Did: kept local development on `MAIL_MAILER=log`; expanded `backend/.env.example` with commented production ACS settings (`MAIL_MAILER=azure_communication_services`, `ACS_MAIL_USERNAME`, `ACS_MAIL_PASSWORD`, `MAIL_EHLO_DOMAIN`, etc.)
- Did: documented production email setup in `backend/README.md`, including required Azure-side setup: create Email Resource, verify `whartonai.studio` sending domain, connect it to an ACS Resource, create Microsoft Entra-backed SMTP credentials, and store credentials as production secrets
- Did: added `MailConfigurationTest` to pin the ACS mailer transport/host/port defaults. Validation: `composer validate --strict` clean, `php artisan test` 136 passed / 604 assertions, `php artisan migrate:fresh` clean
- Left off at: ready for the next slice — Sanctum auth in the frontend HTTP client + Google sign-in UI flow
- Watch out for: no live email was sent because Azure resources/secrets are not provisioned in this repo. The selected production config still requires Azure portal/domain verification work outside code

**May 1, 2026 — Homepage CMS backend + frontend**
- Did: added `homepage_cards` migration and `App\Models\HomepageCard` with shared content lifecycle (`content_status`, `visibility`, `published_at`, `hidden_at`, `archived_at`) plus CMS card content (`section`, `eyebrow`, `title`, `body`, `link_label`, `link_url`, `sort_order`)
- Did: added `App\Http\Controllers\Api\Admin\AdminHomepageCardController` and routes under `/api/admin/homepage-cards` for index (filterable by `section`, `content_status`, and `visibility`), store, show, update, publish, hide, and archive. Every state-changing action writes one `AuditLog` row keyed on `HomepageCard`
- Did: added `App\Http\Controllers\Api\PublicHomepageCardController` and anonymous routes at `/api/public/homepage-cards` (index + show). Public queries filter strictly to `content_status = published` AND `visibility IN (public, mixed)`. Projection is allowlisted to `id`, `section`, `eyebrow`, `title`, `body`, `link_label`, `link_url`, `visibility`, `published_at`; internal fields are denied by test
- Did: added 14 backend feature tests across `AdminHomepageCardApiTest` and `PublicHomepageCardApiTest`, covering admin gating, create/update lifecycle audit logs, filtering, sorting, 404 behavior for non-public records, projection denylist, and pagination validation
- Did: added `frontend/src/stores/publicHomepageCards.js`, backed by `/api/public/homepage-cards`, with `loadList({ section, page, perPage, force, signal })`, `invalidate()`, a 60-second TTL cache keyed by `section/page/perPage`, and a `bySection(section)` getter
- Did: rewired the homepage's "What we do" cards and access-flow timeline to the CMS store. The frontend keeps fallback copy for those sections so an empty CMS or API outage does not blank out the landing page
- Did: updated `backend/README.md`, `frontend/README.md`, `frontend/src/stores/README.md`, this file, and `STARTER_PROMPT.md`. Frontend checks: `npm test` 47/47, `npm run build` clean, `npm run test:routes` clean. Backend checks required by project rules: `composer validate --strict` clean, `php artisan test` 135 passed / 601 assertions, `php artisan migrate:fresh` clean
- Left off at: ready for the next slice — email provider selection/config, unless George wants to prioritize Sanctum auth in the frontend first
- Watch out for: root-level GitHub Pages build artifacts were refreshed from `frontend/dist`, but the preview will only load live API data once `VITE_API_BASE_URL` points at a deployed backend. There are no event/partner/homepage CMS smoke seeders yet, so local non-empty CMS views require creating/publishing rows through API calls or tinker

**May 1, 2026 — Partners backend + frontend**
- Did: added `partners` migration and `App\Models\Partner` with shared content lifecycle (`content_status`, `visibility`, `published_at`, `hidden_at`, `archived_at`) plus partner content (`name`, `partner_type`, `summary`, `description`, `website_url`, `logo_url`, `sort_order`)
- Did: added `App\Http\Controllers\Api\Admin\AdminPartnerController` and routes under `/api/admin/partners` for index (filterable by `content_status` and `visibility`), store, show, update, publish, hide, and archive. Every state-changing action writes one `AuditLog` row keyed on `Partner`
- Did: added `App\Http\Controllers\Api\PublicPartnerController` and anonymous routes at `/api/public/partners` (index + show). Public queries filter strictly to `content_status = published` AND `visibility IN (public, mixed)`. Projection is allowlisted to `id`, `name`, `partner_type`, `summary`, `description`, `website_url`, `logo_url`, `visibility`, `published_at`; internal fields are denied by test
- Did: added 15 backend feature tests across `AdminPartnerApiTest` and `PublicPartnerApiTest`, covering admin gating, create/update lifecycle audit logs, filtering, sorting, 404 behavior for non-public records, projection denylist, and pagination validation
- Did: added `frontend/src/stores/publicPartners.js`, a sibling of the public startup/events stores, backed by `/api/public/partners`. It exposes `loadList({ page, perPage, force, signal })`, `loadOne(id)`, `invalidate()`, a 60-second TTL cache keyed by `page/perPage`, and an optimistic detail placeholder from the cached list
- Did: rewired `PartnersPage.vue` and `PartnerDetailPage.vue` to the live store with loading, empty, API-error, retry, 404, and generic error states. Deleted `frontend/src/data/partners.js`
- Did: updated `backend/README.md`, `frontend/README.md`, `frontend/src/stores/README.md`, this file, and `STARTER_PROMPT.md`. Frontend checks: `npm test` 39/39, `npm run build` clean, `npm run test:routes` clean. Backend checks required by project rules: `composer validate --strict` clean, `php artisan test` 121 passed / 521 assertions, `php artisan migrate:fresh` clean
- Left off at: ready for the next slice — homepage CMS backend + frontend, using the same admin-managed CMS/public-read/store pattern
- Watch out for: root-level GitHub Pages build artifacts were refreshed from `frontend/dist`, but the preview will only load live API data once `VITE_API_BASE_URL` points at a deployed backend. There are no event/partner smoke seeders yet, so local non-empty `/events` and `/partners` views require creating/publishing rows through API calls or tinker

**May 1, 2026 — Events frontend wiring**
- Did: added `frontend/src/stores/publicEvents.js`, a sibling of `publicStartups.js`, backed by `/api/public/events`. It exposes `loadList({ time, page, perPage, force, signal })`, `loadOne(id)`, `invalidate()`, a 60-second TTL cache keyed by `time/page/perPage`, and an optimistic detail placeholder from the cached list
- Did: rewired `EventsPage.vue` to the live store. The visible filters are now `All`, `Upcoming`, and `Past`, and they call the backend with `time = all | upcoming | past`. Loading, empty, API-error, and retry states are explicit
- Did: rewired `EventDetailPage.vue` to `loadOne(id)`, including loading, 404, and generic API-error states. The detail page renders the public projection fields (`starts_at`, `ends_at`, `location`, `format`, `capacity_limit`, `waitlist_open`, `registration_url`, `recap_content`, derived `status`) and links to external registration only for active events
- Did: rewired the homepage's "Selected events" section to the same store with `time=upcoming`, `perPage=3`; deleted `frontend/src/data/events.js`
- Did: fixed stale `backend/README.md` wording that still described the backend as "only the backend foundation"; updated `frontend/README.md` and `frontend/src/stores/README.md` to include the live events store
- Did: added 11 Vitest specs in `frontend/src/stores/publicEvents.test.js`, mirroring the startup-store coverage and adding the time-filter cache behavior. Frontend checks: `npm test` 29/29, `npm run build` clean, `npm run test:routes` clean. Backend checks required by project rules: `composer validate --strict` clean, `php artisan test` 106 passed / 441 assertions, `php artisan migrate:fresh` clean
- Left off at: ready for the next slice — partners backend + frontend using the admin-managed CMS/public-read/store pattern
- Watch out for: root-level GitHub Pages build artifacts were refreshed from `frontend/dist`, but the preview will only load live API data once `VITE_API_BASE_URL` points at a deployed backend. No local smoke with real event rows was run in-browser; this slice was verified with store tests, route smoke, production build, and backend API tests

**May 1, 2026 — Events backend (admin-managed, public read)**
- Did: added `events` migration with full content lifecycle (`content_status`, `visibility`, `published_at`, `hidden_at`, `archived_at`), separate cancellation axis (`cancelled_at`, `cancellation_note`), `recap_content`, `reminder_days_before` (smallint, default 2 per PRODUCT.md), and event content (`title`, `summary`, `description`, `starts_at`, `ends_at`, `location`, `format`, `image_url`, `registration_url`, `capacity_limit`, `waitlist_open`)
- Did: added `App\Models\Event` with proper enum casts and a `derivedStatus()` helper that returns `cancelled` / `recap` / `past` / `upcoming` based on the model state. Used in the public projection so the frontend doesn't have to recompute it
- Did: added `App\Http\Controllers\Api\Admin\AdminEventController` with `index` (filterable by `content_status`, `visibility`, `time`), `store` (creates as draft), `show`, `update`, `publish`, `hide`, `archive`, and `cancel`. Each state-changing action writes one `AuditLog` row keyed on `Event`. Cancellation is a separate axis from `content_status`: the controller toggles `cancelled_at` independently. `cancel` returns 409 if the event is already cancelled. `update` validates `ends_at >= starts_at`
- Did: added `App\Http\Controllers\Api\PublicEventController` (anonymous) at `/api/public/events` (index + show). Filters strictly to `content_status = published` AND `visibility IN (public, mixed)` AND `cancelled_at IS NULL`. `time = upcoming | past | all` with default `upcoming`; upcoming sorts ASC by `starts_at`, past sorts DESC. Allowlist projection (16 fields including derived `status`); denylist test enforces drift. Members-only events are intentionally invisible to anonymous callers (will surface later via the authenticated member events store)
- Did: 22 new tests across `AdminEventApiTest` (13) and `PublicEventApiTest` (9). Suite at 106 passed / 441 assertions. `composer validate --strict` clean, `php artisan migrate:fresh` clean against local SQLite
- Did: events do **not** use the Submission & Admin Review Pattern. PRODUCT.md says "Admins create, edit, publish, hide, and archive at launch," and the pattern's enumeration in PRODUCT.md does not list events. So no `approval_status`, no `submitted_at`/`reviewed_at`/`reviewed_by`/`review_notes`, no submitter notifications. Documented this decision in `backend/README.md`
- Left off at: ready for the next slice — events frontend wiring (sibling Pinia store, wire `EventsPage.vue`, `EventDetailPage.vue`, and the homepage's "Selected events" section to it)
- Watch out for: (1) `reminder_days_before` is stored but no scheduled job dispatches reminders yet — that's queued as a separate slice. (2) `waitlist_open` is admin-toggled because v1 uses external registration only; once internal RSVP lands, this becomes derived from a registered_count. (3) The derived `status` field is part of the public projection contract — if you change the order of precedence in `Event::derivedStatus()`, update `PublicEventApiTest::derived_status_reflects_recap_past_and_upcoming` in the same commit. (4) Cancelled events never reach the public projection, so `derivedStatus()` returning `cancelled` is admin-only display

**May 1, 2026 — Public startup directory frontend wiring**
- Did: introduced `frontend/src/lib/api.js` — a single `getJson()` HTTP client with `VITE_API_BASE_URL` (default `http://127.0.0.1:8000`) resolution, `Accept: application/json`, query-string serialization, and an `ApiError` class that callers can switch on (`error.status`). Tests cover base-URL precedence including the empty-string-fallback edge case
- Did: introduced `frontend/src/stores/publicStartups.js` — Pinia store with `list`, `listMeta`, `currentListing`, loading/error state, plus actions `loadList({ page, perPage, force, signal })` and `loadOne(id, { signal })`. List has a 60-second in-memory TTL keyed on (page, perPage); detail load surfaces a cached optimistic placeholder before the fresh fetch resolves; 404 leaves `currentListing` null
- Did: rewrote `StartupsPage.vue`, `StartupDetailPage.vue`, and the "Featured startups" section of `HomePage.vue` to consume the store. Loading, empty, error, and not-found states are explicit. The detail page now renders `industry`, `tagline`, `description`, `stage`, `location`, `founders[]`, plus action buttons for `website_url` and `linkedin_url` — fields surfaced by the public projection. Deleted `frontend/src/data/startups.js`
- Did: added `frontend/src/stores/README.md` documenting the store convention — one store per backend-resource × access-surface, expected state shape, when to add a new store vs. extend an existing one, why public/member/admin do not share a store. This is the doc the user asked for so future migrations from "page-local fetch" to "shared store" are unnecessary
- Did: installed Vitest + @vue/test-utils + jsdom; added `vitest.config.js` (kept separate from `vite.config.js` so the production GitHub Pages base URL is unaffected), `npm test` and `npm run test:watch` scripts. 18 specs across `lib/api` and `stores/publicStartups`
- Did: added `backend/database/seeders/SmokeStartupSeeder.php` — local-only seeder that inserts two approved+published+public listings plus one members_only and one pending_review (the latter two should be invisible to the public API). Useful for re-running the smoke without manual tinker calls
- Did: validated end-to-end. Backend: `composer validate --strict` clean, `php artisan migrate:fresh` clean, `php artisan test` 84 passed / 330 assertions. Frontend: `npm test` 18/18, `npm run build` clean, `npm run test:routes` clean. Manual smoke: booted Laravel + Vite, opened `/startups` and `/startups/1`, confirmed real listings render and the members_only / pending_review seeds are correctly hidden from the public projection
- Left off at: ready for the next slice — events public API + frontend wiring (Submission & Admin Review Pattern with event-specific lifecycle: capacity, waitlist, cancellation, recap, reminders)
- Watch out for: (1) `VITE_API_BASE_URL` defaults to `http://127.0.0.1:8000` for local dev. The deployed GitHub Pages preview will need a real production API URL once the backend ships to Azure; until then the GitHub Pages preview will fail to load real listings. (2) The HomePage featured-startups section is hidden when the store list is empty, so a fresh deploy without published listings will show no "Featured startups" band — that's the intended behavior, not a bug. (3) When member/admin stores land, they share the `getJson()` client with this slice; auth will be added there in one place. Don't fork the client. (4) The seeder is for smoke checks only and is intentionally not in the production seeder chain — `php artisan db:seed --class=SmokeStartupSeeder --force` to re-run it

**May 1, 2026 — Email notifications (membership applications + startup listings)**
- Did: added 10 mail-channel notification classes under `App\Notifications\*` — five per surface (`*Submitted`, `*ReceivedByAdmin`, `*Approved`, `*NeedsMoreInfo`, `*Rejected`). Each carries the underlying model, returns `['mail']` from `via()`, and renders a `MailMessage` with greeting (applicant first name / owner display name), reviewer notes when present, and an action button back to the relevant URL via `config('app.url')`
- Did: added `User::scopeAdmins()` — `where(approval_status = approved)` AND `whereIn(permission_role, [admin, super_admin])`. This is the canonical recipient list for the "new submission" admin notice across surfaces
- Did: wired the member-side controllers (`MembershipApplicationController`, `StartupListingController`) — `submit()` and `reapply()` fire the submitter thank-you to the user and a `*ReceivedByAdmin` to all admins via `Notification::send($admins, ...)` only when the admin set is non-empty. `update()` deliberately does not fire any notification (queue surface still bumps `submitted_at`)
- Did: wired the admin-side controllers (`AdminMembershipApplicationController`, `AdminStartupListingController`) — each transition action passes a `notification` class through the shared `transition()` helper, which fires the notification only after `DB::transaction()` commits. Reject is opt-in: the admin must include `send_email: true` in the reject payload to trigger the rejection email; default is no email
- Did: `.env.example` now ships with `APP_NAME=WAAIS`, `MAIL_FROM_ADDRESS=noreply@whartonai.studio`, and `MAIL_MAILER=log` so dev never blocks on a real provider
- Did: 19 new feature tests across `MembershipNotificationsTest` (10) and `StartupListingNotificationsTest` (9) using `Notification::fake()`. Coverage: submission thank-you + admin notice, reapply (membership only), no-email-on-edit, approve sends approval email only, request-info sends needs-more-info only, reject without flag is silent, reject with `send_email=true` fires, reject with `send_email=false` is silent, all notifications return `['mail']` from `via()`, rendered approval mail includes the applicant first name / listing name and the review notes
- Did: validated locally on PHP 8.5.5 / Composer 2.9.7 — `composer validate --strict` clean, `php artisan migrate:fresh` clean, `php artisan test` 84 passed / 330 assertions in 2.36s
- Left off at: ready for the next slice — frontend wiring of the public startup directory (`frontend/src/data/startups.js` → `/api/public/startup-listings`)
- Watch out for: notifications fire post-transaction by re-loading the actor (`$application->applicant()->first()` / `$listing->owner()->first()`) and re-fetching the model with `->fresh()`. If a future slice changes those relationships or transaction shape, the post-commit fire path needs to come along. Also: rejection email is opt-in by design — if product policy ever changes to "always email on rejection", flip the default in both admin controllers and update the test allow-default expectation. The `User::admins()` scope is load-bearing for the admin notice; if we ever introduce a `notifications_opt_out` flag, it should be applied here

**May 1, 2026 — Public read API for startup listings**
- Did: added `App\Http\Controllers\Api\PublicStartupListingController` with `index` (paginated) and `show`. Both go through a single `publicQuery()` that filters strictly to `content_status = published` AND `visibility = public`; the show endpoint uses `findOrFail` so anything outside that bucket — draft, pending_review, hidden, archived, members_only, mixed — returns 404
- Did: response uses an explicit allowlist projection (`id`, `name`, `tagline`, `description`, `website_url`, `logo_url`, `industry`, `stage`, `location`, `founders`, `linkedin_url`, `approved_at`). Internal review/ownership fields (`review_notes`, `submitter_role`, `owner_id`, `reviewed_by`, `reviewed_at`, `submitted_at`, `rejected_at`, `approval_status`, `content_status`, `visibility`, `revisions`, `created_at`, `updated_at`) are never serialized
- Did: routes registered as `Route::prefix('public')` outside the `auth:sanctum` group so anonymous callers reach them
- Did: pagination — default `per_page = 12`, capped at 48 by validation; `per_page=0` and `per_page=999` both return 422
- Did: 8 new feature tests in `PublicStartupListingApiTest`, including a denylist assertion that diffs the actual projection key set against the documented allowlist so future drift fails the build. Suite at 65 passed (265 assertions). `composer validate --strict` clean, `migrate:fresh` clean
- Left off at: ready to start the email-notifications slice, or alternatively do the frontend wiring of the public directory next (replacing the static seed in `frontend/src/data/startups.js`). Either is unblocked
- Watch out for: the projection allowlist is now load-bearing for callers. Adding a new public field means updating both the controller projection and the test allowlist in the same commit. The denylist test will catch accidental leaks but won't catch missing-field regressions

**May 1, 2026 — Super-admin role management**
- Did: added `EnsureSuperAdminAccess` middleware backed by `User::canManageAdminPrivileges()`, registered as the `super_admin.access` route alias in `bootstrap/app.php`
- Did: added `App\Http\Controllers\Api\Admin\AdminUserRoleController` with `promoteAdmin`, `demoteAdmin`, `promoteSuperAdmin`, `demoteSuperAdmin`. Each transition uses `lockForUpdate()` on the target user, validates the strict from/to permission_role pair (returns 409 on mismatch), and writes one `AuditLog` row keyed on `User` with action `role.promote_admin` / `role.demote_admin` / `role.promote_super_admin` / `role.demote_super_admin`
- Did: `promote-admin` additionally requires the target to have `approval_status = approved` (returns 409 otherwise)
- Did: `demote-super-admin` is blocked when the target would be the last `SuperAdmin` in the system. The check counts other super_admins excluding the target, so it covers both self-demotion and demoting any single remaining super_admin
- Did: routes registered under `/api/admin/users/{user}/{promote-admin,demote-admin,promote-super-admin,demote-super-admin}` inside the existing `admin.access` group, then nested inside `super_admin.access` so a regular admin gets 403 not 405
- Did: 14 new feature tests in `AdminUserRoleApiTest`. Suite at 57 passed (211 assertions). `composer validate --strict` clean, `migrate:fresh` clean against local SQLite
- Left off at: ready for the next slice — public read API for published startup listings (and eventually events) so the frontend can stop running on static seed data
- Watch out for: PRODUCT.md still says "Limit super_admin to George plus at most two designated others" — this is treated as policy, not enforced in code. If we want a hard cap, add it on `promote-super-admin` (count existing super_admins, abort if >= 3). Also, re-promotion is asymmetric: to promote a member to super_admin you must call `promote-admin` then `promote-super-admin`. If that's friction, we could add a single `set-role` endpoint later

**May 1, 2026 — Startup-listing submission + admin review**
- Did: added `startup_listings` and `startup_listing_revisions` migrations carrying both review fields (`approval_status`, `submitted_at`, `reviewed_at`, `reviewed_by`, `review_notes`, `approved_at`, `rejected_at`) and content-lifecycle fields (`content_status`, `visibility`)
- Did: lean v1 listing fields — `name`, `tagline`, `description`, `website_url`, `logo_url`, `industry`, `stage`, `location`, `founders` (json array), `submitter_role`, `linkedin_url`
- Did: `App\Models\StartupListing` and `StartupListingRevision` with proper enum casts (`ApprovalStatus`, `ContentStatus`, `ContentVisibility`); `User::startupListings()` HasMany relationship
- Did: member-side `App\Http\Controllers\Api\StartupListingController` (`index` / `show` / `store` / `update`) gated by `member.access`. Owner-only show/update. Approved listings reject self-edit with 409. Submit and update both stamp `submitted_at`, set `approval_status = submitted`, `content_status = pending_review`, and clear reviewer fields. Revision rows track changed fields with old/new values
- Did: admin-side `App\Http\Controllers\Api\Admin\AdminStartupListingController` (`index` / `show` / `approve` / `reject` / `requestInfo`) gated by `admin.access`. Approve → `Approved` + `Published` + `approved_at`. Reject → `Rejected` + `Hidden` + `rejected_at`, requires `review_notes`. Request-info → `NeedsMoreInfo` + `Draft`, requires `review_notes`. Each transition writes one `AuditLog` row keyed on `StartupListing` with IP and user-agent
- Did: routes wired under `/api/startup-listings` (member.access) and `/api/admin/startup-listings` (admin.access)
- Did: 15 new feature tests across `StartupListingApiTest` and `AdminStartupListingApiTest`. Suite at 43 passed (183 assertions). `composer validate --strict` clean, `migrate:fresh` clean against local SQLite
- Left off at: ready for the next slice — super-admin role management
- Watch out for: nothing breaking. The `submitter_role` field captures the submitter's role at the company; if we later split "founder member submitting" from "non-founder member submitting on behalf of a portfolio company", we may want a separate `relationship_to_company` field. Public read API for published listings is not yet implemented — frontend can't read approved listings via the API yet

**May 1, 2026 — Dev-context consolidation**
- Did: collapsed `dev-context/` from eight files to four — `PRODUCT.md` (stable description), `PLATFORM_MODEL.md` (data/access contract, unchanged), `DEV_CONTEXT.md` (past/present/future/session log, this file), `STARTER_PROMPT.md` (handover prompt, trimmed read list)
- Did: deleted `CURRENT_STATE.md`, `BACKEND_HANDOFF.md`, `FRONTEND_HANDOFF_SUMMARY.md`, `VUE_FRONTEND_HANDOFF.md`, `HANDOVER_TEMPLATE.md`. Frontend deploy-build commands moved to `frontend/README.md`. Backend validation log already lives in `backend/README.md`
- Left off at: docs restructured. No code changed in this slice. Next slice is startup-listing submission + admin review
- Watch out for: nothing. Tests still pass at 28 / 119 because no code changed; rerun after the next slice to be sure

**May 1, 2026 — Admin membership-application review (canonical pattern implementation)**
- Did: added `EnsureAdminAccess` middleware backed by `User::isAdmin()`, registered it as the `admin.access` route alias in `bootstrap/app.php`
- Did: added `App\Http\Controllers\Api\Admin\AdminMembershipApplicationController` with `index` (filterable queue), `show`, `approve`, `reject`, and `requestInfo` actions; approve promotes `PendingUser` → `Member` (existing `Admin`/`SuperAdmin` are not downgraded), syncs `affiliation_type` from the application, stamps `approved_at`; reject and request-info both require `review_notes`
- Did: each admin transition writes one `AuditLog` row capturing application + applicant before/after state plus `ip_address` / `user_agent`
- Did: 9 feature tests in `tests/Feature/AdminMembershipApplicationApiTest.php`. Suite at 28 passed (119 assertions). Shipped on `main` as `20135b8`
- Left off at: ready for the next slice — startup-listing submission + admin review

**May 1, 2026 — Submission & Admin Review Pattern named**
- Did: extracted the reusable submit-then-admin-review concept into a named pattern covering membership applications, startup listings, forum public-discussion requests, topic proposals, and future partner-listing requests
- Did: aligned slice ordering across docs (later folded into this consolidated DEV_CONTEXT.md)

**May 1, 2026 — Membership application API foundation**
- Did: applicant-owned `GET/POST/PATCH /api/membership-application` and `POST /api/membership-application/reapply`. Application revisions on changed fields. Approved applications are not applicant-editable

**May 1, 2026 — Google OAuth foundation**
- Did: Socialite Google login. New users → `submitted` / `pending_user`. Email linking by existing email. Approved members preserved on re-sign-in. Conflict on different `google_id` returns 409

**May 1, 2026 — Backend auth foundation**
- Did: Laravel Sanctum + `HasApiTokens`. Authenticated `/api/user` and `member.access` middleware

**May 1, 2026 — Backend runtime validation**
- Did: repaired Homebrew PHP/Composer (PHP 8.5.5, Composer 2.9.7). Pinned Composer platform PHP to `8.3.0` in `composer.json`. Local SQLite migrate + tests green

**May 1, 2026 — Production DB switch to Postgres**
- Did: switched the production target from MySQL to Azure Database for PostgreSQL Flexible Server. Local dev/test remains SQLite

**May 1, 2026 — Laravel backend scaffold**
- Did: created `/backend/` with WAAIS enums, model stubs, migrations for users / applications / revisions / audit logs, and first access-rule unit tests

**April 30 – May 1, 2026 — Vue frontend & GitHub Pages**
- Did: scaffolded `/frontend/` with Vite/Vue/Router/Pinia/Tailwind. Built and deployed Vue preview to GitHub Pages root. Static mockups remain under `/mockups/` as visual reference

**April 30, 2026 — Mockups, design system, product decisions**
- Did: built static HTML mockups for public site, app/admin/auth, and design system; locked colors, typography, page inventory, membership flow, admin content management, and forum direction. Detailed product decisions live in `PRODUCT.md`

**April 30, 2026 — Project bootstrap**
- Did: cloned the original waais-v2 React site for reference, audited it, and defined stack/architecture/domain. Old React site isolated in `/legacy/old-react-site/` (git-ignored)

---

*Last updated: May 2, 2026 21:47 CEST*
