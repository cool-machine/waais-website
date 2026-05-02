# WAAIS Backend

Laravel API for the Wharton Alumni AI Studio platform.

## Current Scope

This directory contains the Laravel API for WAAIS. It started as the backend foundation, but now includes the access model, Google/Sanctum auth foundations, membership application workflows, startup-listing workflows, public read APIs, email notifications, role management, admin-managed events, admin-managed partners, and homepage CMS cards.

Implemented:

- `approval_status`, `affiliation_type`, and `permission_role` as separate enum vocabularies.
- User identity and access fields for Google OAuth, approval state, affiliation, and permissions.
- Sanctum API authentication foundation, including credentialed local CORS defaults for the Vite dev origins.
- Google OAuth redirect/callback routes using Laravel Socialite, including safe relative `next` paths for frontend flows that need to return to their originating page.
- Google identity provisioning that creates pending users and preserves approved member access.
- Authenticated `/api/user` endpoint returning access-model flags.
- Authenticated `POST /api/logout` endpoint for ending browser-session auth. It invalidates the session when one is attached and remains safe for token-style Sanctum requests without a session store.
- Member-only API route middleware backed by `canAccessMemberAreas()`.
- Applicant-owned membership application API endpoints for show, submit, update, and rejected-applicant reapply.
- Admin membership application review API endpoints for queue (filterable), single application detail with revisions, approve, reject, and request-more-info, with audit-log entries on every admin action and an `admin.access` middleware backed by `User::isAdmin()`.
- Member-submitted startup-listing API endpoints (list own, show, create, update) gated by `member.access`, with revision history and a 409 on self-edit of approved listings.
- Admin startup-listing review API endpoints (queue filterable by status, single listing detail with revisions, approve / reject / request-info) under `admin.access`, with audit-log entries on every admin action and `ContentStatus` / `ContentVisibility` driving the published lifecycle.
- Super-admin role-management API endpoints (promote/demote admin, promote/demote super_admin) under a `super_admin.access` middleware backed by `User::canManageAdminPrivileges()`. Strict from/to role guards return 409 on mismatch; `promote-admin` requires the target to be approved; `demote-super-admin` is blocked when the target would be the last super_admin. Each transition writes an audit log row.
- Public read API for startup listings: anonymous endpoints under `/api/public/startup-listings` (index, paginated) and `/api/public/startup-listings/{id}` (show), filtered strictly to `content_status = published` + `visibility = public`. Anything else is invisible (404 on show). Response shape is documented below.
- Events backend: admin-managed content (no Submission & Admin Review pattern — events are not user-submitted). Migration adds `events` table with `content_status`/`visibility` plus event-specific fields (`starts_at`, `ends_at`, `location`, `format`, `image_url`, `registration_url`, `capacity_limit`, `waitlist_open`, `recap_content`, `reminder_days_before` default 2, `cancelled_at`, `cancellation_note`). Admin endpoints under `/api/admin/events` (index filterable by `content_status`/`visibility`/`time`, store, show, update, publish, hide, archive, cancel) write one `AuditLog` row per state-changing action. Cancellation is independent of `content_status`: a cancelled event remains visible to admins but is filtered out of every public surface. Public read API at `/api/public/events` (index + show) filters strictly to `content_status = published` AND `visibility IN (public, mixed)` AND `cancelled_at IS NULL`. Index supports `time = upcoming|past|all` (default `upcoming`); upcoming sorts ASC by `starts_at`, past sorts DESC. Response shape is documented below.
- Partners backend: admin-managed content (no Submission & Admin Review pattern). Migration adds `partners` table with `content_status`/`visibility`, lifecycle timestamps, `name`, `partner_type`, `summary`, `description`, `website_url`, `logo_url`, and `sort_order`. Admin endpoints under `/api/admin/partners` (index filterable by `content_status`/`visibility`, store, show, update, publish, hide, archive) write one `AuditLog` row per state-changing action. Public read API at `/api/public/partners` (index + show) filters strictly to `content_status = published` AND `visibility IN (public, mixed)`. Response shape is documented below.
- Homepage CMS cards backend: admin-managed content (no Submission & Admin Review pattern). Migration adds `homepage_cards` table with `content_status`/`visibility`, lifecycle timestamps, `section`, `eyebrow`, `title`, `body`, optional link fields, and `sort_order`. Admin endpoints under `/api/admin/homepage-cards` (index filterable by `section`/`content_status`/`visibility`, store, show, update, publish, hide, archive) write one `AuditLog` row per state-changing action. Public read API at `/api/public/homepage-cards` (index + show) filters strictly to `content_status = published` AND `visibility IN (public, mixed)`. Response shape is documented below.
- Email notifications via Laravel's `Notification` system on the `mail` channel, fired post-transaction. Surfaces, mirrored across membership applications and startup listings: submitter thank-you on submit/reapply (not on edit), admin "new submission" queue notice to all approved Admin/SuperAdmin users via `User::admins()`, approval email, request-more-info email, and an opt-in rejection email gated by a `send_email` boolean on the reject endpoint. Notification classes live under `App\Notifications\*`. Local dev uses `MAIL_MAILER=log`; production target is Azure Communication Services Email over SMTP via the `azure_communication_services` mailer in `config/mail.php`.
- Membership application storage matching the documented v1 questionnaire.
- Application revision history.
- Generic audit log storage for role, application, profile, and content changes.
- Unit tests describing the first access rules.

Not implemented yet:

- Announcement APIs.
- Discourse SSO relay.
- Event reminder dispatch (the `reminder_days_before` field is stored but no scheduled job sends the reminders yet).

## Local Setup

This scaffold requires PHP and Composer:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

Validation was completed locally on May 2, 2026 after the admin membership approvals + sign-out frontend slice:

```text
PHP 8.5.5
Composer 2.9.7
composer install
php artisan test       # last verified: 139 tests, 612 assertions
php artisan migrate:fresh
```

The local `.env`, `vendor/`, and SQLite database are ignored development artifacts. Commit `composer.lock` with backend dependency changes.

`composer.json` pins Composer's platform PHP to `8.3.0`. Keep that guard unless the production target changes; otherwise Composer on a newer local PHP can lock dependencies that require PHP 8.4+.

## Production Email Provider

Production email target: **Azure Communication Services Email over SMTP**.

Reasoning:

- The platform is already targeting Azure for hosting and database infrastructure.
- Azure Communication Services Email supports custom verified domains and SMTP sending.
- Laravel already supports SMTP through Symfony Mailer, so no provider-specific package is required for the current notification flow.
- Local development remains provider-independent with `MAIL_MAILER=log`.

Production environment variables:

```env
MAIL_MAILER=azure_communication_services
ACS_MAIL_HOST=smtp.azurecomm.net
ACS_MAIL_PORT=587
ACS_MAIL_USERNAME=...
ACS_MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@whartonai.studio
MAIL_FROM_NAME="${APP_NAME}"
MAIL_EHLO_DOMAIN=whartonai.studio
```

Operational setup still required outside the repo:

- Create an Azure Communication Email Resource.
- Provision and verify the sending domain for `whartonai.studio`.
- Connect the Email Resource to an Azure Communication Services Resource.
- Create SMTP credentials using a Microsoft Entra application with access to the Communication Services Resource.
- Store the SMTP username and Entra application client secret as production secrets.

References:

- [Microsoft Learn: Azure Communication Services Email overview](https://learn.microsoft.com/en-us/azure/communication-services/concepts/email/email-overview) — custom domains, SMTP support, and pay-as-you-go sending.
- [Microsoft Learn: Send email using SMTP](https://learn.microsoft.com/en-us/azure/communication-services/quickstarts/email/send-email-smtp/send-email-smtp) — host `smtp.azurecomm.net`, port `587`, SSL/TLS enabled, Entra-backed SMTP credentials.
- [Laravel 11 Mail documentation](https://laravel.com/docs/11.x/mail) — SMTP is supported through Laravel's mail configuration.

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

## Public Events API Shape

Both endpoints are anonymous. They filter strictly to `content_status = published` AND `visibility IN (public, mixed)` AND `cancelled_at IS NULL`.

`GET /api/public/events` — paginated index. Default `per_page = 12`, capped at 48. Query parameter `time = upcoming | past | all` (default `upcoming`). Upcoming events sort ASC by `starts_at`; past events sort DESC.

`GET /api/public/events/{id}` — single event in `{ "data": {...} }`. Returns 404 for any non-published / non-public-or-mixed / cancelled event, including draft, pending_review, hidden, archived, and members_only.

Event projection (load-bearing — drift is enforced by `PublicEventApiTest::projection_excludes_internal_fields`):

```text
id                    integer
title                 string
summary               string
description           string
starts_at             ISO-8601 string
ends_at               ISO-8601 string|null
location              string|null
format                string|null
image_url             string|null
registration_url      string|null
capacity_limit        integer|null
waitlist_open         boolean
visibility            "public" | "mixed"
recap_content         string|null
status                "upcoming" | "past" | "recap"   (derived; cancelled never reaches the public projection)
published_at          ISO-8601 string|null
```

Internal fields (`created_by`, `creator`, `content_status`, `cancelled_at`, `cancellation_note`, `reminder_days_before`, `hidden_at`, `archived_at`, `created_at`, `updated_at`) are intentionally never present.

## Public Partners API Shape

Both endpoints are anonymous. They filter strictly to `content_status = published` AND `visibility IN (public, mixed)`.

`GET /api/public/partners` — paginated index. Default `per_page = 12`, capped at 48. Partners sort by `sort_order ASC, name ASC, id ASC`.

`GET /api/public/partners/{id}` — single partner in `{ "data": {...} }`. Returns 404 for any non-published / non-public-or-mixed partner, including draft, pending_review, hidden, archived, and members_only.

Partner projection (load-bearing — drift is enforced by `PublicPartnerApiTest::projection_excludes_internal_fields`):

```text
id              integer
name            string
partner_type    string|null
summary         string
description     string
website_url     string|null
logo_url        string|null
visibility      "public" | "mixed"
published_at    ISO-8601 string|null
```

Internal fields (`created_by`, `creator`, `content_status`, `hidden_at`, `archived_at`, `sort_order`, `created_at`, `updated_at`) are intentionally never present.

## Public Homepage Cards API Shape

Both endpoints are anonymous. They filter strictly to `content_status = published` AND `visibility IN (public, mixed)`.

`GET /api/public/homepage-cards` — paginated index. Default `per_page = 48`, capped at 48. Optional query parameter `section` filters cards for one homepage section. Cards sort by `section ASC, sort_order ASC, id ASC`.

`GET /api/public/homepage-cards/{id}` — single card in `{ "data": {...} }`. Returns 404 for any non-published / non-public-or-mixed card, including draft, pending_review, hidden, archived, and members_only.

Homepage card projection (load-bearing — drift is enforced by `PublicHomepageCardApiTest::projection_excludes_internal_fields`):

```text
id              integer
section         string
eyebrow         string|null
title           string
body            string
link_label      string|null
link_url        string|null
visibility      "public" | "mixed"
published_at    ISO-8601 string|null
```

Internal fields (`created_by`, `creator`, `content_status`, `hidden_at`, `archived_at`, `sort_order`, `created_at`, `updated_at`) are intentionally never present.
