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
- `php artisan test` passed after auth foundation: 11 tests, 23 assertions.
- `php artisan migrate:fresh` passed against the local SQLite database.
- Local ignored artifacts now include `.env`, `vendor/`, and `database/database.sqlite`.

## Next Backend Slice

1. Implement Google OAuth identity creation and pending-user creation.
2. Implement membership application submit/update/reapply endpoints.
3. Implement admin approval/request-more-info/reject flows.
4. Add email notifications for new application, applicant thank-you, approval, and request-more-info.
5. Add event/startup/content tables and APIs after membership flow is stable.
