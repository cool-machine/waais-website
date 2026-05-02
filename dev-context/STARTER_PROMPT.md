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
Continue Azure production setup from `AZURE_PRODUCTION.md`. Azure account context was verified on May 3, 2026 as `g1900@whartonaistudio.onmicrosoft.com` in subscription `Azure subscription 1` (`a66b1770-137e-49cc-a9c2-0ab3186e9752`) and tenant `9d7271ab-ab49-4b9b-a134-6905a15fdb38`; use only that organization account, not George's startup Azure account. Existing Azure resources in `rg-waais-prod-weu`: App Service plan `asp-waais-prod-weu-b1` (Linux Basic B1), Web App `app-waais-api-prod-weu` (PHP 8.3, default host `app-waais-api-prod-weu.azurewebsites.net`, HTTPS-only/Always On/HTTP2/FTPS-only enabled), and Static Web App `swa-waais-prod-weu` (Free, default host `proud-moss-0ec457703.7.azurestaticapps.net`, no repo integration yet). PostgreSQL Flexible Server `psql-waais-prod-weu` was approved for West Europe `Standard_B1ms`, but creation failed after provider registration with `The location is restricted from performing this operation`; no PostgreSQL server exists. Next step is to resolve that West Europe PostgreSQL restriction or explicitly approve another Europe geography region such as North Europe. Do not create the database in another region without explicit approval. Discourse remains deferred to the final stage.
```

## Maintenance

Keep this file in sync with `DEV_CONTEXT.md` whenever:

- A slice ships and the next-slice direction changes — update the "Likely next immediate step" paragraph.
- The list of context files changes — update the read order.
- Working rules change — update the rules section.
