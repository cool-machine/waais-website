# Backend Handoff

Last updated: May 1, 2026

## Status

`/backend/` now contains a Laravel application scaffold with WAAIS-specific domain vocabulary and model stubs. It is not a running backend yet because local PHP and Composer were unavailable when the scaffold was created.

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

## Validation Gap

The scaffold has not been executed with Laravel because:

- `php` is not installed locally.
- `composer` is not installed locally.
- `brew install php composer` failed due a Homebrew embedded Ruby code-signing problem.

Before continuing backend implementation, run from `/backend/` on a machine with PHP 8.3+ and Composer:

```bash
composer install
php artisan test
php artisan migrate:fresh
```

Then fix any Laravel 13 skeleton compatibility issues before adding controllers or API routes.

## Next Backend Slice

1. Install/repair local PHP and Composer.
2. Run Composer install and Laravel tests.
3. Add Sanctum or the selected API auth package.
4. Implement Google OAuth identity creation and pending-user creation.
5. Implement membership application submit/update/reapply endpoints.
6. Implement admin approval/request-more-info/reject flows.
7. Add email notifications for new application, applicant thank-you, approval, and request-more-info.
8. Add event/startup/content tables and APIs after membership flow is stable.
