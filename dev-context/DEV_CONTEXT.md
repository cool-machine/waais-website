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
- Test suite: 43 passing (183 assertions). `php artisan migrate:fresh` passes against local SQLite.

### Production database decision

- Local dev/test: SQLite (in-memory for tests, file for dev).
- Staging / production: Azure Database for PostgreSQL Flexible Server.
- Migrations and queries should stay portable. No Postgres-only or MySQL-only SQL.

## 2. Present — Current Slice

No slice in progress. Last shipped slice: **startup-listing submission + admin review** — the second implementation of the Submission & Admin Review Pattern. Backend test suite at 43 passing / 183 assertions.

## 3. Future — Ordered Next Slices

Backend slices follow the **Submission & Admin Review Pattern** documented in `PRODUCT.md`.

1. **Super-admin role management.** Promote/demote admin endpoints. Prevent self-demotion of the last super_admin. Audit-log every change.
2. **Email notifications.** Applicant thank-you on submission, admin new-application notice, approval, request-more-info; rejection email is optional and admin-triggered. Same notification surfaces for startup listings.
3. **Public read API for published startup listings + events.** Public listing/detail endpoints filtered by `content_status = published` and `visibility = public`. Required before frontend wiring is meaningful.
4. **Events / partners / homepage CMS APIs.** After review patterns are stable.
5. **Discourse SSO relay.** When Discourse is provisioned at `forum.whartonai.studio`.
6. **Frontend wiring** of the live API endpoints onto the existing Vue routes.
7. **Brand/logo asset replacement** when George provides it.
8. **Azure deployment** of app + backend, plus Discourse on Azure VM.

## Working Rules

- Small slices, one concern per branch.
- After every code slice: `composer validate --strict`, `php artisan test`, `php artisan migrate:fresh` — all must pass before commit.
- Update `DEV_CONTEXT.md` (and `STARTER_PROMPT.md` whenever the next-slice direction shifts) in the same commit as the code.
- Commit → push branch → merge to `main` → push `main` at the end of every slice.
- Local dev/test stays on SQLite. Production target is Postgres on Azure. Don't introduce Postgres-only or MySQL-only SQL in migrations or queries.
- If a slice would need human visual or manual testing to verify, stop and tell the user before continuing.

## Session Log

> Newest entry at the top. Each entry: date, what was done, what was left, watch-outs.

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
