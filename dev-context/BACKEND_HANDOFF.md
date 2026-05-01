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
- `php artisan test` passed: 7 tests, 17 assertions.
- `php artisan migrate:fresh` passed against the local SQLite database.
- Local ignored artifacts now include `.env`, `vendor/`, and `database/database.sqlite`.

## Next Backend Slice

1. Add Sanctum or the selected API auth package.
2. Implement Google OAuth identity creation and pending-user creation.
3. Implement membership application submit/update/reapply endpoints.
4. Implement admin approval/request-more-info/reject flows.
5. Add email notifications for new application, applicant thank-you, approval, and request-more-info.
6. Add event/startup/content tables and APIs after membership flow is stable.
