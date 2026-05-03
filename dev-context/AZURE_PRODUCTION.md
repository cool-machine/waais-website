# WAAIS Azure Production Plan

> Deployment/runbook draft for Wharton Alumni AI Studio and Research Center. This document describes the recommended production shape before any Azure resources are created. Keep secrets out of git.

## Decisions So Far

- Organization: Wharton Alumni AI Studio and Research Center.
- Public domain: `whartonai.studio`.
- Budget: Azure non-profit grant, about EUR 1,700/year, roughly USD 2,000/year. Target steady-state spend should stay well below USD 150/month before Discourse.
- Primary region: **West Europe** for app/static frontend. Database is **North Europe** because `Standard_B1ms` PostgreSQL Flexible Server creation in West Europe failed with `The location is restricted from performing this operation` on this subscription. North Europe was approved on May 3, 2026 as the database region. Both stay in the Azure Europe geography.
- Environment count: **production only for now**. Add staging later when traffic or operational risk justifies the added monthly cost.
- Primary data residency: keep Laravel app, PostgreSQL, storage, and email resources in the Azure Europe geography where available.
- Discourse: last stage, likely its own VM at `forum.whartonai.studio`.

## Recommended Architecture

| Surface | Azure service | Proposed hostname | Notes |
|---|---|---|---|
| Frontend Vue app | Azure Static Web Apps, or keep GitHub Pages temporarily | `whartonai.studio` | Static Web Apps gives Azure-native custom domain and SSL. GitHub Pages can remain a preview path until launch. |
| Laravel API | Azure App Service for Linux | `api.whartonai.studio` | Managed app hosting avoids VM patching/PHP-FPM/nginx ownership. |
| Database | Azure Database for PostgreSQL Flexible Server | `psql-waais-prod-neu.postgres.database.azure.com` | Deployed in **North Europe** (West Europe was restricted for `Standard_B1ms` on this subscription). Burstable `Standard_B1ms`, PostgreSQL 16, 32 GiB storage with auto-grow, 7-day backup retention, geo-redundant backup disabled. Public network access currently `Disabled`. Local dev/test stays SQLite. |
| Email | Azure Communication Services Email over SMTP | `noreply@whartonai.studio` | Domain is reported approved. SMTP secrets stay in App Service settings. |
| Scheduler | Azure App Service WebJob or cron-equivalent command runner | n/a | Must run `php artisan schedule:run` every minute. |
| Discourse | Azure VM with official Docker install | `forum.whartonai.studio` | Defer until final stage. |

Avoid running Laravel, PostgreSQL, and Discourse on one hand-managed VM for v1. A VM may look cheaper, but it shifts OS patching, TLS, backups, process supervision, database operations, security hardening, and scheduler reliability onto us. Use managed services for the main app; reserve VM complexity for Discourse.

## DNS Plan

Use three hostnames:

- `whartonai.studio` -> frontend.
- `api.whartonai.studio` -> Laravel backend.
- `forum.whartonai.studio` -> Discourse later.

Why `api.whartonai.studio` instead of `whartonai.studio/api`: it separates static frontend hosting from Laravel backend routing, makes CORS/Sanctum configuration explicit, and avoids reverse-proxy work during the first Azure launch.

Exact DNS record types depend on the Azure service selected during deployment. Azure will provide the target hostnames/verification records when the custom domains are added.

## Cost Guardrails

Start small and scale only after real usage:

- Use one production environment, not staging + production.
- Use the smallest reasonable App Service plan that supports the required PHP runtime and custom domains.
- Use the smallest PostgreSQL Flexible Server burstable SKU that supports the workload.
- Keep storage modest; avoid geo-redundant add-ons unless needed.
- Keep Discourse deferred because it adds another always-on compute resource.
- Set Azure budgets/alerts at conservative thresholds, for example 50%, 75%, and 90% of monthly target.

Before creating resources, check current prices in the Azure pricing calculator for **West Europe**. Pricing changes and grant currency conversion can move.

## Production Environment Variables

### Laravel Core

```env
APP_NAME=WAAIS
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://api.whartonai.studio
FRONTEND_URL=https://whartonai.studio
LOG_CHANNEL=stack
LOG_LEVEL=info
```

Generate `APP_KEY` in production with `php artisan key:generate --show`, then store it as an App Service application setting.

### Database

```env
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=waais_production
DB_USERNAME=...
DB_PASSWORD=...
```

Use Azure Database for PostgreSQL Flexible Server. Do not use SQLite in production. Keep migrations portable; the project rule remains no Postgres-only SQL unless we explicitly accept that tradeoff.

### Sanctum, Sessions, and CORS

```env
SESSION_DRIVER=database
SESSION_DOMAIN=.whartonai.studio
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=whartonai.studio,api.whartonai.studio
FRONTEND_URL=https://whartonai.studio
```

The frontend calls the API with browser credentials. Production custom domains must be configured before validating Google sign-in, email-link sign-in, and authenticated admin/member routes.

### Google OAuth

Production OAuth should be created under the organization Google for Nonprofits/admin account, not a personal testing account.

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://api.whartonai.studio/auth/google/callback
```

Google Cloud console setup:

- Authorized JavaScript origin: `https://whartonai.studio`.
- Authorized redirect URI: `https://api.whartonai.studio/auth/google/callback`.
- OAuth consent screen should use the organization identity and production domain.

### Email

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

Azure Communication Services Email setup remains outside code:

- Confirm the sending domain is verified/approved.
- Create or confirm SMTP credentials.
- Store credentials as App Service settings.
- Send one production smoke email to an admin before member launch.

### Discourse Placeholders

Discourse is final-stage work. Keep placeholders ready but do not enable production SSO until Discourse is provisioned.

```env
DISCOURSE_URL=https://forum.whartonai.studio
DISCOURSE_CONNECT_SECRET=...
```

Generate a strong `DISCOURSE_CONNECT_SECRET` later and store it in both Laravel and Discourse.

## Frontend Build Variables

For Azure Static Web Apps or any production frontend build:

```env
VITE_API_BASE_URL=https://api.whartonai.studio
```

After build/deploy, verify:

- Public routes load from `https://whartonai.studio`.
- Public API calls go to `https://api.whartonai.studio`.
- Authenticated requests include credentials.
- Deep links render correctly.

## Scheduler and Commands

The app uses Laravel scheduler registration in `backend/bootstrap/app.php`.

Scheduled commands:

- `announcements:send-emails` hourly.
- `events:send-reminders` daily at 09:00.

Production must run:

```sh
php artisan schedule:run
```

every minute. On Azure App Service, use a WebJob or equivalent scheduled runner. The App Service should have Always On enabled if the selected plan supports it; otherwise scheduled work can be unreliable.

## Deployment Steps

First production deployment should follow this order. Steps 1–3 are already done; step 2 was completed in North Europe rather than West Europe due to a regional restriction. Step 2a (database connectivity from the App Service) is the current open decision.

1. Create resource group in West Europe. (`rg-waais-prod-weu` — done.)
2. Create PostgreSQL Flexible Server. (`psql-waais-prod-neu` in North Europe — done. Application database `waais_production` not yet created via `az postgres flexible-server db create`.)
2a. Decide PostgreSQL connectivity from the App Service: Private Endpoint into a VNet shared with the App Service via Regional VNet Integration, or enabling public network access and adding firewall rules for the App Service outbound IPs / "Allow Azure services". Reset admin password (or move to Microsoft Entra auth + managed identity) and store in App Service settings or Key Vault, never in the repo.
3. Create Linux App Service for Laravel backend. (`asp-waais-prod-weu-b1` plan + `app-waais-api-prod-weu` web app — done.)
4. Configure backend App Service environment variables.
5. Deploy backend code.
6. Run `composer install --no-dev --optimize-autoloader`.
7. Run `php artisan migrate --force`.
8. Run `php artisan config:cache` and `php artisan route:cache` if compatible with the deployed environment.
9. Configure `api.whartonai.studio` custom domain and TLS.
10. Create production Google OAuth client and update App Service settings.
11. Configure ACS Email SMTP settings and send a smoke email.
12. Configure scheduler runner for `php artisan schedule:run`.
13. Deploy frontend with `VITE_API_BASE_URL=https://api.whartonai.studio`.
14. Configure `whartonai.studio` custom domain and TLS.
15. Run production smoke checks.

## Production Smoke Checks

Public checks:

- `GET https://api.whartonai.studio/up` returns healthy response.
- `GET https://api.whartonai.studio/api/public/events` returns JSON.
- `GET https://api.whartonai.studio/api/public/startup-listings` returns JSON.
- `https://whartonai.studio` loads the frontend.
- Deep link `https://whartonai.studio/membership` loads.

Auth checks:

- Google sign-in redirects through the organization OAuth client.
- Email-link sign-in sends to an admin-controlled mailbox.
- `/api/user` returns signed-in user data after auth.
- Pending users cannot access member routes.
- Approved member can load dashboard.
- Admin can load approvals and announcements.

Notification checks:

- Membership application submission writes a mail/log/send event.
- `announcements:send-emails` can run without error.
- `events:send-reminders` can run without error.

## Open Deployment Questions

- Final frontend hosting: Azure Static Web Apps vs GitHub Pages for first launch.
- Exact Azure subscription/resource group naming.
- Exact App Service plan/SKU after checking West Europe pricing.
- Exact PostgreSQL Flexible Server SKU/storage/backup retention.
- Whether to add staging after initial launch.
- Final privacy contact email, for example `privacy@whartonai.studio`.

## References

- [Microsoft Learn: Azure regions and geographies](https://learn.microsoft.com/en-us/azure/reliability/regions-overview) — data residency boundaries and region selection.
- [Microsoft Azure: Azure geographies](https://azure.microsoft.com/en-us/explore/global-infrastructure/geographies/) — compliance and data residency by geography.
- [Microsoft Learn: Azure Static Web Apps plans](https://learn.microsoft.com/en-us/azure/static-web-apps/plans).
- [Microsoft Azure: Azure App Service pricing](https://azure.microsoft.com/en-us/pricing/details/app-service/linux/).
- [Microsoft Azure: Azure Database for PostgreSQL Flexible Server pricing](https://azure.microsoft.com/en-us/pricing/details/postgresql/flexible-server/).
- [Microsoft Learn: Azure Communication Services Email overview](https://learn.microsoft.com/en-us/azure/communication-services/concepts/email/email-overview) and [SMTP quickstart](https://learn.microsoft.com/en-us/azure/communication-services/quickstarts/email/send-email-smtp/send-email-smtp).
