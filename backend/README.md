# WAAIS Backend

Laravel backend scaffold for the Wharton Alumni AI Studio platform.

## Current Scope

This directory is intentionally only the backend foundation. It pins the WAAIS access model before OAuth, controllers, API routes, or database-backed admin workflows are implemented.

Implemented in this scaffold:

- `approval_status`, `affiliation_type`, and `permission_role` as separate enum vocabularies.
- User identity and access fields for Google OAuth, approval state, affiliation, and permissions.
- Sanctum API authentication foundation.
- Google OAuth redirect/callback routes using Laravel Socialite.
- Google identity provisioning that creates pending users and preserves approved member access.
- Authenticated `/api/user` endpoint returning access-model flags.
- Member-only API route middleware backed by `canAccessMemberAreas()`.
- Applicant-owned membership application API endpoints for show, submit, update, and rejected-applicant reapply.
- Admin membership application review API endpoints for queue (filterable), single application detail with revisions, approve, reject, and request-more-info, with audit-log entries on every admin action and an `admin.access` middleware backed by `User::isAdmin()`.
- Member-submitted startup-listing API endpoints (list own, show, create, update) gated by `member.access`, with revision history and a 409 on self-edit of approved listings.
- Admin startup-listing review API endpoints (queue filterable by status, single listing detail with revisions, approve / reject / request-info) under `admin.access`, with audit-log entries on every admin action and `ContentStatus` / `ContentVisibility` driving the published lifecycle.
- Super-admin role-management API endpoints (promote/demote admin, promote/demote super_admin) under a `super_admin.access` middleware backed by `User::canManageAdminPrivileges()`. Strict from/to role guards return 409 on mismatch; `promote-admin` requires the target to be approved; `demote-super-admin` is blocked when the target would be the last super_admin. Each transition writes an audit log row.
- Public read API for startup listings: anonymous endpoints under `/api/public/startup-listings` (index, paginated) and `/api/public/startup-listings/{id}` (show), filtered strictly to `content_status = published` + `visibility = public`. Anything else is invisible (404 on show). Response shape is documented below.
- Email notifications via Laravel's `Notification` system on the `mail` channel, fired post-transaction. Surfaces, mirrored across membership applications and startup listings: submitter thank-you on submit/reapply (not on edit), admin "new submission" queue notice to all approved Admin/SuperAdmin users via `User::admins()`, approval email, request-more-info email, and an opt-in rejection email gated by a `send_email` boolean on the reject endpoint. Notification classes live under `App\Notifications\*`. Email provider is intentionally still TBD: dev `.env.example` ships with `MAIL_MAILER=log`.
- Membership application storage matching the documented v1 questionnaire.
- Application revision history.
- Generic audit log storage for role, application, profile, and content changes.
- Unit tests describing the first access rules.

Not implemented yet:

- Frontend wiring of the public startup directory (`frontend/src/data/startups.js` still serves static seed data; needs to call `/api/public/startup-listings`).
- Event, partner, announcement, or homepage CMS APIs.
- Discourse SSO relay.
- Production email provider selection (Azure Communication Services Email or Google Workspace).

## Local Setup

This scaffold requires PHP and Composer:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

Validation was completed locally on May 1, 2026 after repairing Homebrew PHP/Composer:

```text
PHP 8.5.5
Composer 2.9.7
composer install
php artisan test       # last verified: 84 tests, 330 assertions (after the email-notifications slice)
php artisan migrate:fresh
```

The local `.env`, `vendor/`, and SQLite database are ignored development artifacts. Commit `composer.lock` with backend dependency changes.

`composer.json` pins Composer's platform PHP to `8.3.0`. Keep that guard unless the production target changes; otherwise Composer on a newer local PHP can lock dependencies that require PHP 8.4+.

## Model Contract

The backend must stay aligned with:

- `../dev-context/PLATFORM_MODEL.md`
- `../frontend/src/data/platformModel.js`

Do not collapse access back into one overloaded `role` field. Laravel policies and controllers should use:

- `approval_status` for application/account review state.
- `affiliation_type` for Wharton/Penn/community relationship.
- `permission_role` for product permissions.

## Public Startup Listing API Shape

The frontend public startup directory consumes these endpoints. Both are anonymous (no auth required) and filter strictly to `content_status = published` AND `visibility = public`.

`GET /api/public/startup-listings` — paginated index. Default `per_page = 12`, capped at 48. Returns Laravel's standard pagination envelope (`data`, `current_page`, `last_page`, `per_page`, `total`, links). Each `data[*]` element follows the projection below. Sorted by `approved_at DESC, id DESC`.

`GET /api/public/startup-listings/{id}` — single listing in `{ "data": {...} }`. Returns 404 if the listing is not in (published, public) — including any draft / pending_review / hidden / archived state, or any members_only / mixed visibility.

Listing projection (load-bearing — drift is enforced by `PublicStartupListingApiTest::projection_excludes_internal_fields`):

```text
id            integer
name          string
tagline       string
description   string
website_url   string|null
logo_url      string|null
industry      string
stage         string|null
location      string|null
founders      string[]|null
linkedin_url  string|null
approved_at   ISO-8601 string|null
```

Internal fields (`review_notes`, `submitter_role`, `owner_id`, `reviewed_*`, `submitted_at`, `rejected_at`, `approval_status`, `content_status`, `visibility`, `revisions`, `created_at`, `updated_at`) are intentionally never present. Adding a public field requires updating both the controller projection and the test allowlist in the same commit.
