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
- Membership application storage matching the documented v1 questionnaire.
- Application revision history.
- Generic audit log storage for role, application, profile, and content changes.
- Unit tests describing the first access rules.

Not implemented yet:

- Public read API for published startup listings (filtered by `content_status = published` + `visibility = public`).
- Email notifications.
- Event, partner, announcement, or homepage CMS APIs.
- Discourse SSO relay.

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
php artisan test       # last verified: 57 tests, 211 assertions (after super-admin role management slice)
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
