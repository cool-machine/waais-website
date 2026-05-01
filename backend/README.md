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
- Membership application storage matching the documented v1 questionnaire.
- Application revision history.
- Generic audit log storage for role, application, profile, and content changes.
- Unit tests describing the first access rules.

Not implemented yet:

- Admin membership application review controllers.
- Admin approval, role-management, event, startup, partner, announcement, or CMS APIs.
- Email notifications.
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
php artisan test       # passed: 19 tests, 79 assertions
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
