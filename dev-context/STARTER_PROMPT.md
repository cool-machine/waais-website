# Starter Prompt — WAAIS Continuation

Copy the block below into a new LLM session when the current one ends. Update the "Likely next immediate step" line at the bottom whenever a slice ships so the next LLM picks up the right work.

```text
You are picking up an ongoing software project. Don't start coding until you have read the project context and verified the current state.

Project: WAAIS — Wharton Alumni AI Studio.
Project root: /Users/gg1900/coding/waais-website
Repository: https://github.com/cool-machine/waais-website
Owner: George (cool.lstm@gmail.com).

Read these files before doing anything else, in order:
1. /Users/gg1900/coding/waais-website/dev-context/PRODUCT.md          — what we're building (stable)
2. /Users/gg1900/coding/waais-website/dev-context/PLATFORM_MODEL.md   — data/access contract
3. /Users/gg1900/coding/waais-website/dev-context/DEV_CONTEXT.md      — past, present, future, working rules, session log
4. /Users/gg1900/coding/waais-website/dev-context/AZURE_PRODUCTION.md — Azure production deployment plan
5. /Users/gg1900/coding/waais-website/dev-context/PRIVACY_READINESS.md — privacy/legal launch checklist
6. /Users/gg1900/coding/waais-website/backend/README.md               — backend validation status & commands
7. /Users/gg1900/coding/waais-website/frontend/README.md              — frontend run/build/deploy commands

Then, before writing any code:
- Run git status; report current branch and whether the tree is clean.
- Verify /frontend, /backend, /mockups, /dev-context all exist.
- Summarize in 4–6 sentences what the project is, what's already shipped, and what the next slice is.
- Flag anything stale, contradictory, or risky in the docs.
- Wait for explicit instruction unless told to continue immediately.

Working rules (also documented in DEV_CONTEXT.md):
- Small slices, one concern per branch.
- After every code slice: composer validate --strict, php artisan test, php artisan migrate:fresh — all must pass before commit.
- Update DEV_CONTEXT.md (and STARTER_PROMPT.md whenever the next-slice direction shifts) in the same commit as the code.
- Commit → push branch → merge to main → push main at the end of every slice.
- Local dev/test stays on SQLite. Production target is Azure Database for PostgreSQL Flexible Server. Do not introduce Postgres-only SQL.
- If a slice would need human visual or manual testing to verify, stop and ask the user before continuing.

Likely next immediate step:
Continue Azure production setup from `AZURE_PRODUCTION.md`. Azure account context was verified on May 3, 2026 as `g1900@whartonaistudio.onmicrosoft.com` in subscription `Azure subscription 1` (`a66b1770-137e-49cc-a9c2-0ab3186e9752`) and tenant `9d7271ab-ab49-4b9b-a134-6905a15fdb38`; use only that organization account, not George's startup Azure account. Existing Azure resources in `rg-waais-prod-weu`: App Service plan `asp-waais-prod-weu-b1` (Linux Basic B1, West Europe), Web App `app-waais-api-prod-weu` (PHP 8.3, West Europe, default host `app-waais-api-prod-weu.azurewebsites.net`, HTTPS-only/Always On/HTTP2/FTPS-only enabled, basic auth disabled on both SCM and FTP, with `DB_*` settings already populated and pointing at the production database), Static Web App `swa-waais-prod-weu` (Free, West Europe, default host `proud-moss-0ec457703.7.azurestaticapps.net`, no repo integration yet), and PostgreSQL Flexible Server `psql-waais-prod-neu` (Burstable `Standard_B1ms`, PostgreSQL 16, 32 GiB auto-grow, 7-day backups, geo-redundant disabled, FQDN `psql-waais-prod-neu.postgres.database.azure.com`, admin user `waaisadmin`, public network access `Enabled` with the `AllowAllAzureServicesAndResourcesWithinAzureIps` firewall rule, application database `waais_production` created and migrated, state `Ready`). The database is in **North Europe** because the West Europe restriction is subscription-wide for Azure Database for PostgreSQL Flexible Server (verified via `az postgres flexible-server list-skus --location westeurope` returning empty `supportedServerEditions` with the explicit "Subscriptions are restricted from provisioning in this region" reason); both regions are inside the EU. The PostgreSQL admin password was rotated and written into App Service application settings — not stored elsewhere. Subscription-level monthly budget `waais-monthly-grant-pace` is live at amount `167` (Sponsorship subscription, USD) with notifications at 50/75/90/100% actual and 100% forecasted, sent to `cool.lstm@gmail.com` and `george@whartonai.studio`. Core Laravel App Service settings are populated: `APP_NAME=WAAIS`, `APP_ENV=production`, `APP_KEY` (base64 32-byte random, only in App Service), `APP_DEBUG=false`, `APP_URL=https://api.whartonai.studio`, `FRONTEND_URL=https://whartonai.studio`, `LOG_CHANNEL=stack`, `LOG_LEVEL=info`, `SESSION_DRIVER=database`, `SESSION_DOMAIN=.whartonai.studio`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS=whartonai.studio,api.whartonai.studio`, `FRONTEND_CORS_ORIGINS=https://whartonai.studio`. Backend is live in production: `https://app-waais-api-prod-weu.azurewebsites.net/up` returns HTTP 200, `/api/public/events` and `/api/public/startup-listings` return HTTP 200 with empty paginated envelopes (schema present, no published data yet). Backend deploy pipeline is wired: Azure AD app `gh-waais-deploy` (appId `47ab24d1-5d10-477e-b493-c29728910f3d`) with federated OIDC trust on `cool-machine/waais-website`'s `main` branch, narrowly-scoped Contributor on the App Service, and `.github/workflows/deploy-backend.yml` that builds Composer (no dev), zips `backend/`, and zip-deploys via `azure/login@v2` + `azure/webapps-deploy@v3`. App Service startup command points to `/home/site/wwwroot/startup.sh`, which copies `backend/nginx-default.conf` over `/etc/nginx/sites-available/default` (so requests are routed through `public/`) and runs Laravel optimizations on container boot. The first production `php artisan migrate --force` ran on May 3, 2026 from inside the App Service container via `az webapp ssh` driven non-interactively by `expect` (Kudu's `/api/command` runs in the Kudu sidecar where PHP isn't installed, basic auth is off so publishing creds aren't an option, so `az webapp ssh` was the path); 16 migrations applied cleanly in batch 1, log saved to `/home/LogFiles/first-prod-migrate.log`. Next step is the custom domain + TLS for `api.whartonai.studio` (sessions will not work on the default `*.azurewebsites.net` host because `SESSION_DOMAIN=.whartonai.studio`, and Laravel's URL generator currently emits `http://app-waais-api-prod-weu.azurewebsites.net` in pagination payloads because the request host isn't the configured `APP_URL`). After that: frontend production deploy + `whartonai.studio` custom domain, then production Google OAuth client and the `GOOGLE_*` settings, then ACS Email setup, then the scheduler runner for `php artisan schedule:run`, then the smoke checks in `AZURE_PRODUCTION.md`. Discourse remains deferred to the final stage.
```

## Maintenance

Keep this file in sync with `DEV_CONTEXT.md` whenever:

- A slice ships and the next-slice direction changes — update the "Likely next immediate step" paragraph.
- The list of context files changes — update the read order.
- Working rules change — update the rules section.
