# Starter Prompt — WAAIS Continuation

Copy this prompt into a new LLM session when you need another model to continue the project.

```text
You are picking up an ongoing software project. Please do not start coding until you read the project context and verify the current state.

Project name: WAAIS — Wharton Alumni AI Studio platform
What it is: A community platform for Wharton alumni working in or interested in AI, with a public website, approved-member dashboard, admin dashboard, and future Discourse forum at forum.whartonai.studio.
My role: Founder / project owner.
Your role today: Pragmatic software engineer helping continue implementation without losing the existing project decisions.

Project root:
/Users/gg1900/coding/waais-website

Read these files first, in this order:
1. /Users/gg1900/coding/waais-website/dev-context/DEV_CONTEXT.md
2. /Users/gg1900/coding/waais-website/dev-context/CURRENT_STATE.md
3. /Users/gg1900/coding/waais-website/dev-context/PLATFORM_MODEL.md
4. /Users/gg1900/coding/waais-website/dev-context/BACKEND_HANDOFF.md
5. /Users/gg1900/coding/waais-website/dev-context/FRONTEND_HANDOFF_SUMMARY.md
6. /Users/gg1900/coding/waais-website/dev-context/VUE_FRONTEND_HANDOFF.md

Also check:
- /Users/gg1900/coding/waais-website/frontend/README.md
- /Users/gg1900/coding/waais-website/backend/README.md
- /Users/gg1900/coding/waais-website/mockups/README.md

Important current state:
- The Vue frontend exists under /frontend, is tracked, has been merged to main, and GitHub Pages serves the built Vue preview from the repository root.
- The root-level index.html, 404.html, assets/, favicon.svg, and icons.svg are generated deployment output from frontend/dist. Do not hand-edit those unless intentionally patching GitHub Pages output.
- The static mockups remain under /mockups as visual/product references only. They are not the production Vue app and not the Laravel backend.
- The Laravel backend scaffold exists under /backend on branch codex/backend-laravel-scaffold and draft PR #6.
- Backend work has started but is not runtime-validated yet. Local php and composer were unavailable, and a Homebrew install attempt failed with a macOS/Homebrew Ruby code-signing issue.
- Do not assume the backend works until composer install, php artisan test, and php artisan migrate:fresh have run.
- The old React app in /legacy/old-react-site is reference-only and ignored by git.

Core product/model decisions:
- Do not collapse access into one role field.
- Use separate backend fields: approval_status, affiliation_type, permission_role.
- Pending users do not get member/forum access.
- Only super_admin users can promote users to admin or remove admin privileges.
- Rejected applicants can reapply.
- Regular members cannot directly publish public content.
- Discourse will live later at forum.whartonai.studio; Laravel should eventually provide the SSO relay.

Before doing any work:
1. Run git status and tell me the current branch and whether the tree is clean.
2. Verify the key folders exist: /frontend, /backend, /mockups, /dev-context.
3. Summarize in 4–6 sentences what the project is, what has already been implemented, and what the next immediate step is.
4. Flag anything that looks stale, contradictory, missing, or risky.
5. Wait for my instruction unless I explicitly ask you to continue implementation.

Likely next immediate step:
Install or repair PHP 8.3+ and Composer, then from /backend run composer install, php artisan test, and php artisan migrate:fresh. Fix any Laravel scaffold/runtime issues before adding Google OAuth, controllers, API routes, or database-backed workflows.
```

## Context File Roles

The Markdown handoff is organized like this:

- `DEV_CONTEXT.md` — long-lived project context, locked decisions, current work, remaining phases, and session notes. It should stay curated: remove or mark stale direction changes instead of preserving confusing old guidance.
- `CURRENT_STATE.md` — concise present-state handoff: what exists now, what branch/work is active, what is mockup-only, and what comes next.
- `BACKEND_HANDOFF.md` — backend-specific state and validation gap.
- `FRONTEND_HANDOFF_SUMMARY.md` — frontend/deployment-specific state and useful route/build details.
- `PLATFORM_MODEL.md` — canonical vocabulary for access, membership, and content model decisions.
