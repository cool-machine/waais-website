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
4. /Users/gg1900/coding/waais-website/backend/README.md               — backend validation status & commands
5. /Users/gg1900/coding/waais-website/frontend/README.md              — frontend run/build/deploy commands

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
Wire the **events frontend** to the live API. The events backend shipped in the previous slice — `/api/public/events` (index + show) is documented in `backend/README.md` ("Public Events API Shape"). Add `frontend/src/stores/publicEvents.js` as a sibling of `publicStartups.js` (same shape — `loadList({ time, page, perPage, force })`, `loadOne(id)`, TTL cache, optimistic placeholder on detail). Wire `EventsPage.vue`, `EventDetailPage.vue`, and the homepage's "Selected events" section to the store; delete `frontend/src/data/events.js`. Make the existing "All / Upcoming / Past" filter buttons functional by passing `time` to `loadList`. Add Vitest specs mirroring the publicStartups spec shape. Reuse the `getJson()` client in `frontend/src/lib/api.js` and follow the conventions in `frontend/src/stores/README.md`. After this slice: partners backend + frontend (same pattern), then homepage CMS, then Sanctum auth in the HTTP client (which unblocks all dashboard slices).
```

## Maintenance

Keep this file in sync with `DEV_CONTEXT.md` whenever:

- A slice ships and the next-slice direction changes — update the "Likely next immediate step" paragraph.
- The list of context files changes — update the read order.
- Working rules change — update the rules section.
