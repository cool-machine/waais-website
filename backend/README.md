# WAAIS Backend

Laravel backend scaffold for the Wharton Alumni AI Studio platform.

## Current Scope

This directory is intentionally only the backend foundation. It pins the WAAIS access model before OAuth, controllers, API routes, or database-backed admin workflows are implemented.

Implemented in this scaffold:

- `approval_status`, `affiliation_type`, and `permission_role` as separate enum vocabularies.
- User identity and access fields for Google OAuth, approval state, affiliation, and permissions.
- Membership application storage matching the documented v1 questionnaire.
- Application revision history.
- Generic audit log storage for role, application, profile, and content changes.
- Unit tests describing the first access rules.

Not implemented yet:

- Google OAuth login.
- Sanctum/API authentication.
- Membership application submit/review controllers.
- Admin approval, role-management, event, startup, partner, announcement, or CMS APIs.
- Email notifications.
- Discourse SSO relay.

## Local Setup

This scaffold requires PHP and Composer before Laravel commands can run:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
```

At the time this scaffold was created, PHP and Composer were not available in the local Codex environment, and Homebrew installation was blocked by a macOS/Homebrew Ruby code-signing error. The source files were therefore created without running Composer, migrations, Pint, or PHPUnit.

## Model Contract

The backend must stay aligned with:

- `../dev-context/PLATFORM_MODEL.md`
- `../frontend/src/data/platformModel.js`

Do not collapse access back into one overloaded `role` field. Laravel policies and controllers should use:

- `approval_status` for application/account review state.
- `affiliation_type` for Wharton/Penn/community relationship.
- `permission_role` for product permissions.
