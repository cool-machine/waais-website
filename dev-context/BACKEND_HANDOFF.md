# Backend Handoff

Last updated: May 1, 2026

## Status

`/backend/` now contains a Laravel application scaffold with WAAIS-specific domain vocabulary and model stubs. Local PHP/Composer were repaired after scaffold creation, dependencies were installed, and the first test/migration validation now passes.

## What Was Added

- Laravel skeleton under `/backend/`.
- PHP enums matching the platform contract:
  - `ApprovalStatus`
  - `AffiliationType`
  - `PermissionRole`
  - `ContentStatus`
  - `ContentVisibility`
- User model access helpers:
  - `isPending()`
  - `isMember()`
  - `isAdmin()`
  - `isSuperAdmin()`
  - `canAccessMemberAreas()`
  - `canPublishPublicContent()`
  - `canManageAdminPrivileges()`
- Sanctum auth foundation:
  - API route loading in `bootstrap/app.php`
  - Sanctum config and personal access token migration
  - `HasApiTokens` on `User`
  - authenticated `/api/user` endpoint
  - `member.access` middleware for member-only API routes
- Google OAuth foundation:
  - Socialite installed and configured for Google
  - `/auth/google/redirect` and `/auth/google/callback` routes
  - callback provisions new Google users as `approval_status = submitted` and `permission_role = pending_user`
  - approved members keep member access when signing in again
  - linked-email conflict handling is covered by tests
- Membership application API foundation:
  - authenticated applicant endpoint to fetch their current application
  - submit endpoint for draft/new applications
  - update endpoint for applicant-owned non-approved applications
  - reapply endpoint for rejected applications
  - application revision records for changed fields
- Database structure for:
  - Google/social identity fields on users.
  - Approval, affiliation, and permission fields on users.
  - Membership applications.
  - Application revisions.
  - Audit logs.
- Unit tests documenting first access rules.

## Validation Status

Validated locally on May 1, 2026 with:

```text
PHP 8.5.5
Composer 2.9.7
composer install
php artisan test
php artisan migrate:fresh
```

Results:

- `composer install` completed and generated `composer.lock`.
- `composer.json` pins Composer platform PHP to `8.3.0` so local PHP 8.5 does not lock PHP 8.4+ dependencies.
- `php artisan test` passed after membership application API foundation: 19 tests, 79 assertions.
- `php artisan migrate:fresh` passed against the local SQLite database.
- Local ignored artifacts now include `.env`, `vendor/`, and `database/database.sqlite`.

## Next Backend Slice

1. Implement admin approval/request-more-info/reject flows.
2. Add email notifications for new application, applicant thank-you, approval, and request-more-info.
4. Add event/startup/content tables and APIs after membership flow is stable.
