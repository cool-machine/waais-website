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
- Admin membership-application review (canonical first implementation of the Submission & Admin Review Pattern):
  - `admin.access` middleware backed by `User::isAdmin()` (admins and super_admins both pass)
  - `GET /api/admin/applications` filterable approval queue
  - `GET /api/admin/applications/{application}` single-application detail with reviewer and revisions
  - `POST /api/admin/applications/{application}/approve` — application + applicant become approved, pending applicants are promoted to `Member`, existing `Admin`/`SuperAdmin` are not downgraded, applicant's `affiliation_type` synced from the application, `approved_at` stamped
  - `POST /api/admin/applications/{application}/reject` — `review_notes` required, applicant stays `PendingUser`, `rejected_at` stamped
  - `POST /api/admin/applications/{application}/request-info` — `review_notes` required, applicant becomes `NeedsMoreInfo` and stays `PendingUser`
  - every admin action writes one `AuditLog` row capturing both the application and applicant before/after state, plus `ip_address` and `user_agent`
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
- `php artisan test` passed after the admin membership review slice: 28 tests, 119 assertions (9 new feature tests in `tests/Feature/AdminMembershipApplicationApiTest.php`).
- `php artisan migrate:fresh` passed against the local SQLite database.
- Local ignored artifacts now include `.env`, `vendor/`, and `database/database.sqlite`.

## Next Backend Slices

The backend uses one reusable **Submission & Admin Review Pattern** (see `DEV_CONTEXT.md`): the same `ApprovalStatus` enum, the same `submitted_at`/`reviewed_at`/`reviewed_by`/`review_notes` columns, the same `AuditLog` entries, and the same `admin.access` middleware are intended to serve every surface where members or visitors submit something for admin review. Slice the work so the membership-review slice becomes the canonical implementation, then mirror it.

1. ~~**Membership-application admin review**~~ — done in this slice. Implementation lives in `app/Http/Controllers/Api/Admin/AdminMembershipApplicationController.php` and `app/Http/Middleware/EnsureAdminAccess.php`; tests in `tests/Feature/AdminMembershipApplicationApiTest.php`. This is the canonical first implementation of the Submission & Admin Review Pattern that later slices should mirror.
2. **Startup-listing submission + admin review** (next slice):
   - Member-submitted startup listing endpoints (approved members only): create draft, update own draft/submitted listing, submit for review, withdraw before review.
   - Admin review endpoints mirroring the membership review shape: queue, approve (publishes), reject (with notes), request-info.
   - Reuse the same `ApprovalStatus` vocabulary plus `ContentStatus`/`ContentVisibility` for published lifecycle.
   - Reuse `admin.access` middleware. Reuse audit-log shape (`actions: startup_listings.approve` / `.reject` / `.request_info` / `.publish`).
3. **Super-admin role management** (small follow-up): promote/demote admin, prevent self-demotion of the last super_admin, audit-log every change.
4. **Email notifications** (after the two review slices work): applicant thank-you, admin new-application notice, approval, request-more-info; rejection optional.
5. **Events / partners / homepage CMS APIs** after the patterns above are stable.

Outside the API surface, the same Submission & Admin Review Pattern will eventually be reused for forum public-discussion requests and topic proposals from non-members; both are documented in `DEV_CONTEXT.md` and not in scope for the current slices.
