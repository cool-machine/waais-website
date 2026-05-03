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
| Database | Azure Database for PostgreSQL Flexible Server | `psql-waais-prod-neu.postgres.database.azure.com` | Deployed in **North Europe** because the West Europe restriction on this subscription is subscription-wide for Flexible Server (all editions blocked, not just `Standard_B1ms`). Burstable `Standard_B1ms`, PostgreSQL 16, 32 GiB storage with auto-grow, 7-day backup retention, geo-redundant backup disabled. Public network access `Enabled` with one firewall rule `AllowAllAzureServicesAndResourcesWithinAzureIps` (`0.0.0.0`–`0.0.0.0`); auth + TLS still required. Application database `waais_production` exists. Local dev/test stays SQLite. |
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
- Set Azure budgets/alerts at conservative thresholds, for example 50%, 75%, and 90% of monthly target. Status: live as of May 3, 2026 — subscription-level monthly budget `waais-monthly-grant-pace` at amount `167` (subscription currency) with five notifications (actual at 50/75/90/100%, forecasted at 100%) routed to `cool.lstm@gmail.com` and `george@whartonai.studio`. Spending limit is `Off` on the Sponsorship subscription, so the budget is alert-only.

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

First production deployment should follow this order. Steps 1–4 (database half) are already done. The remaining work is the rest of the App Service application settings, the deploy pipeline, the first migration, and custom domains.

1. Create resource group in West Europe. (`rg-waais-prod-weu` — done.)
2. Create PostgreSQL Flexible Server. (`psql-waais-prod-neu` in North Europe — done. Public access `Enabled` with `AllowAllAzureServicesAndResourcesWithinAzureIps` firewall rule. Application database `waais_production` created.)
3. Create Linux App Service for Laravel backend. (`asp-waais-prod-weu-b1` plan + `app-waais-api-prod-weu` web app — done.)
4. Configure backend App Service environment variables. DB block done: `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE=waais_production`, `DB_USERNAME=waaisadmin`, `DB_PASSWORD` (rotated), `DB_SSLMODE=require`. Core Laravel block done: `APP_NAME=WAAIS`, `APP_ENV=production`, `APP_KEY=base64:…` (32-byte random generated directly into App Service settings, never stored elsewhere), `APP_DEBUG=false`, `APP_URL=https://api.whartonai.studio`, `FRONTEND_URL=https://whartonai.studio`, `LOG_CHANNEL=stack`, `LOG_LEVEL=info`, `SESSION_DRIVER=database`, `SESSION_DOMAIN=.whartonai.studio`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS=whartonai.studio,api.whartonai.studio`, `FRONTEND_CORS_ORIGINS=https://whartonai.studio`. Remaining blocks: Google OAuth (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`) and ACS Email (`MAIL_MAILER`, `ACS_MAIL_HOST`, `ACS_MAIL_PORT`, `ACS_MAIL_USERNAME`, `ACS_MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_EHLO_DOMAIN`).
5. Deploy backend code. Done — automated via `.github/workflows/deploy-backend.yml`. AAD app `gh-waais-deploy` (appId `47ab24d1-5d10-477e-b493-c29728910f3d`, sp `76135382-a0fe-4a3d-a939-0df6f7c5f8b1`) holds Contributor narrowly on the App Service; federated credential `github-cool-machine-waais-website-main` trusts `repo:cool-machine/waais-website:ref:refs/heads/main`. First deploy ran green on May 3, 2026 (run `25277933567`); `https://app-waais-api-prod-weu.azurewebsites.net/up` returns HTTP 200.
6. Run `composer install --no-dev --optimize-autoloader`. Done — runs in the GitHub Actions workflow.
7. Run `php artisan migrate --force`. Done on May 3, 2026 — 16 migrations applied cleanly in batch 1 against `waais_production`. Subsequent migrations must run inside the App Service container because the GitHub runner can't reach PostgreSQL through the firewall (allows only Azure services) and Kudu's `/api/command` endpoint runs in the Kudu sidecar without PHP. The `az webapp ssh` command is interactive-only on Linux App Service; drive it non-interactively with `expect`. Output of the first run is in `/home/LogFiles/first-prod-migrate.log` on the App Service.
8. Run `php artisan config:cache` and `php artisan route:cache` if compatible with the deployed environment. Done — `backend/startup.sh` runs `config:cache`, `route:cache`, `view:cache`, and `storage:link` on every container boot. App Service startup command points to `/home/site/wwwroot/startup.sh`. The same script also copies `backend/nginx-default.conf` over `/etc/nginx/sites-available/default` so requests are routed through `public/`. The deploy zip includes `mkdir -p storage/framework/{cache/data,sessions,views,testing}` + `bootstrap/cache` so the Laravel view compiler has writable directories — without this step the first deploy returned HTTP 500.
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

## Security & Maintenance Cadence

> Recurring tasks that keep the production system safe and compliant. Each item has an owner column once George designates owners; until then, all owners are George. "Action" means an actual hands-on task, not just observing a green dashboard.

### Daily (automated; review only if alerted)

- Azure Service Health alerts for `westeurope` and `northeurope` Flexible Server, App Service, and Static Web Apps. Configure alert email to George on first deploy.
- Azure Monitor / App Service alerts for HTTP 5xx error rate, response time p95, and CPU/memory pressure on `app-waais-api-prod-weu` and `psql-waais-prod-neu`.
- Laravel queued/scheduled command failures: surface via `LOG_CHANNEL=stack` to App Service log stream; alert if `events:send-reminders` or `announcements:send-emails` errors.

### Weekly

- Skim Azure Cost Management against the EUR 1,700/year (roughly USD 167/month) grant cap. Investigate any week with month-to-date trending above pace.
- Skim Laravel application logs for unhandled exceptions, repeated 5xx, or auth/Sanctum errors. Open issues for any pattern.
- Confirm scheduled jobs ran: there should be daily `events:send-reminders` rows in `event_reminder_deliveries` and hourly `announcements:send-emails` retry attempts as expected.
- Review the admin approvals queue (`/app/approvals`) and startup-listing review queue (`/app/startup-review`); pending applications should not languish.

### Monthly

- Apply Composer security updates: `composer update --with-dependencies` against the backend, then run `composer validate --strict`, `php artisan test`, and `php artisan migrate:fresh` locally before deploying.
- Apply npm security updates: `npm audit` and `npm update` in `frontend/`, then `npm test`, `npm run build`, and `npm run test:routes`.
- Patch PHP runtime version on App Service if Microsoft has issued a new 8.3.x. App Service surfaces this in the runtime stack settings.
- Review Sanctum personal access tokens table for stale entries; revoke any that were issued for one-off scripts or testing.
- Review the application audit log for any unexpected role transitions, content publish/hide actions, or admin operations.
- Verify Azure backup retention on `psql-waais-prod-neu`: 7-day point-in-time backups should be present and recent.
- Verify App Service managed TLS certificate for `api.whartonai.studio` is not within 30 days of expiry. App Service auto-renews, but a stuck renewal is worth catching before the cert expires.
- Verify Static Web Apps managed TLS certificate for `whartonai.studio` is not within 30 days of expiry. Same reasoning.
- Review Azure RBAC on the subscription and `rg-waais-prod-weu`: ensure only the `g1900@whartonaistudio.onmicrosoft.com` organization account (and any explicitly-approved co-admins) have Owner/Contributor access.

### Quarterly

- Rotate the PostgreSQL admin password: `az postgres flexible-server update --admin-password <new>` and `az webapp config appsettings set --settings DB_PASSWORD=<new>` in one shell session, never echoing the value. Confirm the App Service can still reach the database after rotation.
- Rotate ACS Email SMTP credentials. Update `ACS_MAIL_USERNAME` / `ACS_MAIL_PASSWORD` in App Service settings. Send a test email afterward.
- Rotate `DISCOURSE_CONNECT_SECRET` once Discourse is deployed. Update both Laravel and Discourse in lockstep so SSO never breaks.
- Test database restore: do a point-in-time-restore to a throwaway server name in the same region, run `\dt` to verify schema and a row count on `users`, then delete the restore. This proves the backups are usable.
- Review the Google OAuth client: confirm authorized origins and redirect URIs still match production hostnames; remove any test/dev origins that crept in.
- Review long-suspended or never-approved user records; decide whether to delete per the privacy policy retention approach.
- Test the GDPR data-rights request flow end to end: an internal test request should produce export and deletion results within the documented turnaround.

### Annually (or on major Laravel/PHP/Vue release)

- Audit production secrets inventory: every App Service application setting documented above plus any added later. Confirm none have leaked into git history (`git log -p -- backend/.env` should return nothing).
- Re-evaluate Azure region strategy: the West Europe Flexible Server restriction may have lifted. Run `az postgres flexible-server list-skus --location westeurope` and check `supportedServerEditions`. If lifted and migration is desirable, plan a maintenance window — but only if the operational benefit clears the migration cost.
- Re-evaluate Azure App Service plan tier (currently B1) and PostgreSQL SKU (currently `Standard_B1ms`). Right-size based on actual CPU/memory/IOPS metrics, not headroom anxiety.
- Right-size PostgreSQL storage. Auto-grow is enabled, so over-provisioning is unlikely, but check actual usage.
- Plan PHP minor/major version bump (e.g., 8.3 → 8.4) once Laravel and the Composer lockfile support it cleanly. Update `composer.json` platform pin in the same slice.
- Plan Laravel and Vue minor/major version bumps. Test full coverage before deploying.
- Counsel review of `/legal` privacy/cookie/data-rights copy. Update the privacy acknowledgement version string only if substantive changes warrant re-acknowledgement.
- Confirm the Azure non-profit grant is still active and the EUR 1,700/year balance is intact. Renew Wharton Alumni AI Studio and Research Center organizational records as needed.

### On-change (event-driven, not time-based)

- Whenever the App Service plan tier or scale unit changes, refresh the firewall posture. Today's `AllowAllAzureServicesAndResourcesWithinAzureIps` rule survives plan changes, but a future move to Private Endpoint requires removing that rule and provisioning the VNet/Endpoint plumbing.
- Whenever a new admin or super-admin is added, confirm the audit log row landed (`role.promote_admin` or `role.promote_super_admin`) and that there are still at most three super-admins per the product rules.
- Whenever a new processor/vendor is added (new email provider, new analytics, new file store, etc.), update `PRIVACY_READINESS.md` and the `/legal` Privacy Policy to reflect the change before going live.
- Whenever a secret may have been exposed (laptop loss, accidental git commit, screen share leak), rotate that specific secret immediately and audit the App Service settings change history.
- Whenever Discourse goes live, finish the Discourse SSO admin/group sync configuration in lockstep with Laravel's relay.
- Whenever a member submits a GDPR request, log it, action it within the policy's stated turnaround, and capture the resolution in the audit trail.

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
