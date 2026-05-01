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
- Static seed data only — backend integration is not yet wired.
- Build deployed to GitHub Pages via root-level `index.html`, `404.html`, `assets/`, `favicon.svg`, `icons.svg` copied from `frontend/dist`. Deploy steps live in `frontend/README.md`.

### Backend (live, validated locally)

- Local PHP 8.5.5 / Composer 2.9.7 working. `composer.json` pins Composer platform PHP to `8.3.0` to keep the lockfile compatible with the documented PHP 8.3+ target.
- Laravel 11 scaffold with WAAIS enums (`ApprovalStatus`, `AffiliationType`, `PermissionRole`, `ContentStatus`, `ContentVisibility`).
- User model with access helpers: `isPending`, `isMember`, `isAdmin`, `isSuperAdmin`, `canAccessMemberAreas`, `canPublishPublicContent`, `canManageAdminPrivileges`.
- Migrations for users (with `google_id`, `approval_status`, `affiliation_type`, `permission_role`, plus `approved_at`/`rejected_at`/`suspended_at` timestamps), membership applications, application revisions, audit logs, personal access tokens, cache, jobs.
- Sanctum API auth: `/api/user` returns access flags; `member.access` middleware on `/api/member/*`.
- Google OAuth via Socialite: `/auth/google/redirect` and `/auth/google/callback`. New users → `submitted` / `pending_user`. Existing unlinked users link by email. Approved members are not downgraded on re-sign-in. Email already linked to a different `google_id` returns 409.
- Applicant-owned membership application API: `GET/POST/PATCH /api/membership-application`, `POST /api/membership-application/reapply`. Rejected applicants can reapply. Field changes write `application_revisions` rows.
- Admin membership-application review API: `admin.access` middleware backed by `User::isAdmin()`. Routes under `/api/admin/applications`: queue (filterable by `status`), single-application detail with revisions, approve, reject, request-info. Approve promotes pending applicants to Member without downgrading existing Admin/SuperAdmin and syncs `affiliation_type` from the application. Reject and request-info both require `review_notes`. Each transition writes one `AuditLog` row capturing application + applicant before/after state plus IP and user-agent. This is the canonical implementation of the **Submission & Admin Review Pattern** (described in `PRODUCT.md`).
- Member-side startup-listing API: approved members only via `member.access`. Routes under `/api/startup-listings`: list own, show own, create, update. Submission stamps `approval_status = submitted` and `content_status = pending_review`. Owner cannot show or edit another member's listing. Approved listings cannot be self-edited (returns 409). Edits re-submit and clear reviewer fields. `startup_listing_revisions` rows are written on submit and update with the changed-fields diff.
- Admin startup-listing review API: routes under `/api/admin/startup-listings` mirror the membership review shape (queue filterable by `status`, show with revisions, approve, reject, request-info). Approve sets `approval_status = approved` + `content_status = published` + `approved_at`. Reject sets `approval_status = rejected` + `content_status = hidden` + `rejected_at` and requires `review_notes`. Request-info sets `approval_status = needs_more_info` + `content_status = draft` and requires `review_notes`. Each transition writes one `AuditLog` row keyed on `StartupListing` with before/after state plus IP and user-agent. `ContentVisibility` defaults to `public`.
- Super-admin role-management API: `super_admin.access` middleware backed by `User::canManageAdminPrivileges()`. Routes under `/api/admin/users/{user}` for `promote-admin`, `demote-admin`, `promote-super-admin`, `demote-super-admin`. Each transition is a row-locked update with strict from/to role guards (returns 409 on role mismatch). `promote-admin` additionally requires the target to be `approval_status = approved`. `demote-super-admin` is blocked when the target is the last `SuperAdmin` in the system (covers self-demotion and any path that would empty the role). Every transition writes one `AuditLog` row keyed on `User` with `role.promote_admin` / `role.demote_admin` / `role.promote_super_admin` / `role.demote_super_admin` plus before/after `permission_role` plus IP and user-agent.
- Public read API for startup listings: anonymous (no auth) routes under `/api/public/startup-listings` (index, paginated) and `/api/public/startup-listings/{listing}` (show). Both filter strictly to `content_status = published` AND `visibility = public`; anything else is invisible (404 on show). The response uses an explicit allowlist projection: `id`, `name`, `tagline`, `description`, `website_url`, `logo_url`, `industry`, `stage`, `location`, `founders`, `linkedin_url`, `approved_at` (ISO 8601). Internal fields — `review_notes`, `submitter_role`, `owner_id`, `reviewed_by`, `reviewed_at`, `submitted_at`, `rejected_at`, `approval_status`, `content_status`, `visibility`, `revisions`, `created_at`, `updated_at` — never appear, enforced by a denylist test. Default `per_page = 12`, capped at 48.
- Test suite: 65 passing (265 assertions). `php artisan migrate:fresh` passes against local SQLite.

### Production database decision

- Local dev/test: SQLite (in-memory for tests, file for dev).
- Staging / production: Azure Database for PostgreSQL Flexible Server.
- Migrations and queries should stay portable. No Postgres-only or MySQL-only SQL.

## 2. Present — Current Slice

No slice in progress. Last shipped slice: **public read API for startup listings** — anonymous index/show endpoints filtered to published+public, with an explicit allowlist projection. Backend test suite at 65 passing / 265 assertions.

## 3. Future — Ordered Next Slices

Backend slices follow the **Submission & Admin Review Pattern** documented in `PRODUCT.md`.

1. **Email notifications.** Applicant thank-you on submission, admin new-application notice, approval, request-more-info; rejection email is optional and admin-triggered. Same notification surfaces for startup listings. Email provider still TBD — start with Laravel's mail facade and a log driver in dev so we don't block on provider choice.
2. **Frontend wiring of the public startup directory.** Replace the static seed data in `frontend/src/data/startups.js` with calls to `/api/public/startup-listings`. Keep the existing `StartupsPage.vue` shape; just swap the data source. Surfaces the API publicly and proves the public projection is sufficient.
3. **Events / partners / homepage CMS APIs.** Same Submission & Admin Review Pattern, plus an event-specific lifecycle (capacity, waitlist, cancellation, recap, reminders).
4. **Discourse SSO relay.** When Discourse is provisioned at `forum.whartonai.studio`.
5. **Member dashboard + admin dashboard frontend wiring** of the authenticated APIs.
6. **Brand/logo asset replacement** when George provides it.
7. **Azure deployment** of app + backend, plus Discourse on Azure VM.

## Working Rules

- Small slices, one concern per branch.
- After every code slice: `composer validate --strict`, `php artisan test`, `php artisan migrate:fresh` — all must pass before commit.
- Update `DEV_CONTEXT.md` (and `STARTER_PROMPT.md` whenever the next-slice direction shifts) in the same commit as the code.
- Commit → push branch → merge to `main` → push `main` at the end of every slice.
- Local dev/test stays on SQLite. Production target is Postgres on Azure. Don't introduce Postgres-only or MySQL-only SQL in migrations or queries.
- If a slice would need human visual or manual testing to verify, stop and tell the user before continuing.

## Session Log

> Newest entry at the top. Each entry: date, what was done, what was left, watch-outs.

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

*Last updated: May 1, 2026*
