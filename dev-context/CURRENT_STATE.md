# WAAIS — Current State Handoff

Last updated: April 30, 2026

This file is the short version of the handoff. Read `DEV_CONTEXT.md` for the full source of truth.

## What This Project Is

WAAIS is the Wharton Alumni AI Studio platform: a public website, approved-member dashboard, admin dashboard, and future Discourse forum for Wharton alumni working in or interested in AI. The long-term production stack is Vue 3 for the frontend and Laravel/PHP for the backend, with Discourse hosted at `forum.whartonai.studio`.

## What Was Done This Session

- Isolated the old React/Vite site under `legacy/old-react-site/` as reference-only material.
- Reinitialized this folder as a fresh git repository and pushed it to `https://github.com/cool-machine/waais-website`.
- Added `legacy/` to `.gitignore`, so the old site remains local and is not tracked in the new repo.
- Created static design mockups in `mockups/`:
  - `mockups/design-system.html`
  - `mockups/public-site.html`
  - `mockups/app-dashboard-admin-auth.html`
- Added George's licensed hero video locally at `mockups/assets/waais-hero-video.mp4`.
- Enabled GitHub Pages at `https://cool-machine.github.io/waais-website/`.
- Changed root `index.html` so the GitHub Pages root redirects to the public-site mockup.

## Design Decisions Locked In

- Navbar/top shell uses water blue `#256F8F`.
- Wharton navy `#011F5B` remains a deep brand color for CTA bands, stats, and accents.
- Wharton crimson `#C41E3A` is the primary action/accent color.
- Homepage uses a dark video hero and dynamic scroll motion, followed by white/off-white readable content sections.
- Dashboard, admin, auth, and operational surfaces remain dark.
- Current logo is a placeholder; George will provide a real club brand mark later.
- Forum will use `forum.whartonai.studio`, not `whartonai.studio/forum`.
- Public navigation should include a Forum link. The current mockup links to `https://forum.whartonai.studio`.

## Membership Flow Now Designed

The public CTA should be `Become a member`, not a generic Get Involved button.

The Membership page should present:

- Existing members: Sign in.
- New applicants: Apply for membership.
- Non-members: Propose a topic, partner with WAAIS, or request a startup listing.

The application form should collect Wharton status, year/program, LinkedIn, AI category/interest, and reason for joining. Wharton status must distinguish alumni from current students because current students may need different access rules from alumni. After submission, the user lands in a pending approval state until an admin approves them.

The startup directory is designed as partially gated: public users can see teasers, but full member/startup profiles require approved member access.

## Admin / Content Management Now Designed

The admin mockup includes design-only screens for:

- Admin overview.
- Approval queue.
- User management.
- Event management.
- Public content management.
- Announcements.

The Public Content section is intended to let admins create, edit, publish, draft, hide, or remove cards shown on the public website, including events, startups, partners, homepage modules, and other small content cards.

Important: this is not implemented as working software yet. It is a static HTML prototype only. Real authentication, permissions, database persistence, and publishing logic still need the Vue + Laravel build.

## Discourse Direction

Discourse should be installed later, not inside this GitHub Pages/static mockup phase. It will run on its own Azure VM using the official Docker-based Discourse install, with DNS pointing `forum.whartonai.studio` to that VM.

The forum should imitate the structure people already understand from the WhatsApp groups:

- Region-based categories: New York, San Francisco, London, etc.
- Industry-based categories: Finance, Media & Entertainment, etc.

The forum UX target is similar to PyTorch forums or fast.ai forums: simple category lists, topic lists, and discussion threads. Moderation rules are still open: WAAIS needs to decide whether new topics require approval, whether replies require approval, and whether rules vary by category or member trust level.

## Current URLs

- GitHub repo: `https://github.com/cool-machine/waais-website`
- GitHub Pages root: `https://cool-machine.github.io/waais-website/`
- Public mockup: `https://cool-machine.github.io/waais-website/mockups/public-site.html`
- App/admin/auth mockup: `https://cool-machine.github.io/waais-website/mockups/app-dashboard-admin-auth.html`
- Design system: `https://cool-machine.github.io/waais-website/mockups/design-system.html`

## What We Are Doing Now

We are documenting the full session state so the project can continue cleanly after the context limit. The next practical step is design review, then implementation of the Vue frontend from these mockups.

## Remaining Next Steps

1. George reviews the current mockups:
   - Homepage video and motion.
   - Membership/sign-in/application flow.
   - Gated startup directory treatment.
   - Admin public-content management concept.
   - Dashboard/admin navigation.
   - Whether event, startup, and partner card detail-page behavior is complete enough for implementation.
2. Replace placeholder logo/brand mark when George provides it.
3. Scaffold `/frontend/` with Vue 3, Vite, Tailwind, Vue Router, and likely Pinia.
4. Convert the static mockups into reusable Vue routes and components.
5. Temporarily deploy the Vue frontend to GitHub Pages while backend work is not ready.
6. Scaffold `/backend/` with Laravel/PHP.
7. Implement Google OAuth, pending approval, roles, admin permissions, CMS persistence, events, startups, partners, and Discourse SSO.
8. Later deploy the production app/backend to Azure and Discourse to an Azure VM at `forum.whartonai.studio`.

## Watch Outs

- The deployed site is static HTML/CSS/JS, not Vue and not PHP.
- The admin page is reached through `mockups/app-dashboard-admin-auth.html`, not from the public mockup root.
- Browser cache may show an old GitHub Pages version; hard refresh or open the direct mockup URLs.
- `legacy/` exists locally but is ignored by git.
- `/frontend/` and `/backend/` do not exist yet; that is expected.
