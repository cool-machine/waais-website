# WAAIS Platform — Developer Context

> **Single source of truth for any developer or LLM picking up this project.**
> Read this file first. Do not start working until you have completed the checklist below.

---

## ⚡ Start of Session Checklist

- [ ] Read all three sections of this file
- [ ] Verify these key paths still exist on disk:
  - Project root: `/Users/gg1900/coding/waais-website/`
  - Old React source (reference only): `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/`
  - Old React app root (reference only): `/Users/gg1900/coding/waais-website/legacy/old-react-site/`
  - Dev context folder: `/Users/gg1900/coding/waais-website/dev-context/`
  - Vue frontend (to be created): `/Users/gg1900/coding/waais-website/frontend/`
  - Laravel backend (to be created): `/Users/gg1900/coding/waais-website/backend/`
  - This file: `/Users/gg1900/coding/waais-website/dev-context/DEV_CONTEXT.md`
- [ ] Read the Session Notes at the bottom — they reflect the most recent state of work
- [ ] Note anything that has moved or changed, and update this file before starting

---

## 1. Recent Decisions

> Locked in. Don't revisit without good reason.

**Project**
- Platform for the **Wharton Alumni AI Studio and Research Center** — community of Wharton alumni working in AI
- Domain: `whartonai.studio`
- Forum at `whartonai.studio/forum` (Discourse, path-based via nginx reverse proxy)
- API at `whartonai.studio/api` — exact subdomain vs. path still TBD
- Old broken site: `https://cool-machine.github.io/waais-v2/` — reference only
- Old React app isolated in `/legacy/old-react-site/` for content, copy, and page structure reference

**Tech stack**
- Frontend: **Vue 3** (Composition API) + Vite + Tailwind CSS
- Backend: **Laravel (PHP)** — REST API, auth, events, Discourse SSO relay
- Forum: **Discourse** (self-hosted on Azure VM)
- Auth: **Google OAuth** via Laravel Socialite — new accounts start as `pending`, require admin approval
- Deployment: **Microsoft Azure** non-profit grant (~$2,000/year, ~$167/month cap)
- Database: **Azure Database for MySQL**

**Design — locked in**
- Dark mode first — near-black navy background `#050E20`, high contrast
- Prestigious and modern — closer to OpenAI / Linear than a typical university site
- **Water blue** `#256F8F` — selected navbar / top app shell color, with white text
- **Wharton navy** `#011F5B` — stats bar, CTA band, card accents, and deep brand surfaces
- **Wharton crimson** `#C41E3A` — primary accent: buttons, tags, eyebrow labels, category dots
- Card background: `#0A1833` with border `rgba(100,130,200,0.14)`
- Body text: white / `#94A3B8` muted
- Subtle animations only — no particle backgrounds
- Logo: **placeholder for now** — George will supply a custom brand mark for the club (separate from the main Wharton logo, which cannot be used)
- Homepage page-body direction: use a hybrid public-site treatment — dark hero/top shell, then selected white/off-white scroll sections for readability and contrast. Keep dashboard/admin/auth dark.

**Users & roles**
- `pending` → registers via Google OAuth, cannot access dashboard or forum yet
- `member` → approved by admin, gets dashboard + forum access
- `admin` → all member features + user management, event management, announcements, moderation
- Discourse SSO is automatic — no separate forum account needed
- Admins: George + a small group (names TBD)

**Page inventory — ~20 pages total**

| Section | Pages |
|---|---|
| Public site | Home, Events, Startups, About / Team, Partners, Get Involved, Contact |
| Legal | Privacy Policy, Cookie Policy, GDPR Request |
| Auth | Sign In, Registration / Pending Approval screen |
| Member dashboard | Overview, Profile, My Events, Forum Feed |
| Admin dashboard | Approvals Queue, User Management, Event Management, Announcements |
| Forum | `/forum` — Discourse app, not a Vue page |

**Azure cost estimate**
- App Service (B2): ~$15–20/mo
- VM (B2s) for Discourse: ~$15–20/mo
- MySQL Flexible Server (B1): ~$10–15/mo
- Blob Storage: ~$2–5/mo
- **Total: ~$42–60/month** — well within the $167/month cap

**Project contacts**
- Owner: George (cool.lstm@gmail.com)
- GitHub: https://github.com/cool-machine
- Developer: PHP + Vue background

---

## 2. Current Work

**Task: UI design — mockups for all pages**

The design phase is in progress. A first-pass interactive mockup of all 5 main pages has been built and reviewed. The next step is to refine the design before any code is written.

- [x] Audit existing React codebase
- [x] Define stack, domain, architecture, design direction
- [x] Create developer context file and handover templates
- [x] Build interactive mockup: Home, Events, Startups, About, Forum (all 5 pages, clickable tabs)
- [x] George reviewed mockup — colors approved, layout direction approved
- [x] Isolate old React/Vite website under `/legacy/old-react-site/` as reference-only material
- [x] Create interactive app/auth/admin mockup: Sign In, Pending Approval, member dashboard pages, admin dashboard pages
- [x] Create visual design system mockup: colors, surfaces, typography, buttons, cards, tables, forms, tags
- [x] Create public-site mockup: Home, Events, Startups, About, Partners, Get Involved, Contact, Legal
- [x] Add homepage/public-site scroll motion: reveal-on-scroll, left/right converging sections, stat count-ups, floating topic cluster, subtle hero parallax, reduced-motion fallback
- [x] Add local homepage hero video asset from George's licensed YouTube upload
- [x] Resolve navbar color: selected steel water blue `#256F8F`
- [x] Finalise first-pass design system: confirm colors, typography direction, spacing, components, dark/light surface rules
- [x] Extend mockup to cover Dashboard (member), Dashboard (admin), Sign In, Get Involved
- [ ] George supplies brand/logo asset — drop into mockup
- [ ] Hand mockup to developer as the visual spec for the Vue build

**Design decisions from mockup review**
- Wharton colors (#011F5B navy, #C41E3A crimson) are confirmed — do not change these
- Navbar uses steel water blue `#256F8F` with white text
- Logo is a placeholder — do not design around it yet
- Forum page (Discourse-style) design approved: category grid + topic list, same dark theme as main site
- Public homepage can use white/off-white scroll sections after the dark hero; app/dashboard/admin/auth should remain dark for consistency and focus

**Relevant files**
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/pages/` — old page structure to reference
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/data/` — events, startups, partners, team data
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/components/` — old components for layout reference
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/package.json` — old React/Vite dependency manifest, reference only
- `/Users/gg1900/coding/waais-website/mockups/design-system.html` — visual design system and component rules
- `/Users/gg1900/coding/waais-website/mockups/public-site.html` — clickable public website prototype
- `/Users/gg1900/coding/waais-website/mockups/assets/waais-hero-video.mp4` — local homepage hero video asset
- `/Users/gg1900/coding/waais-website/mockups/app-dashboard-admin-auth.html` — interactive mockup for auth, member dashboard, and admin dashboard; includes navbar color swatches
- `/Users/gg1900/coding/waais-website/index.html` — GitHub Pages landing page linking to all mockups

---

## 3. Remaining Steps

### Design (current phase)
- [x] Navbar water blue color decision
- [x] First-pass visual design system
- [x] Mockups for: member dashboard, admin dashboard, sign-in page
- [x] Mockups for: public pages including get involved page
- [ ] Logo asset from George → place in all mockups
- [ ] Final design sign-off before dev starts

### Phase 1 — Public site (Vue frontend)
- [ ] Scaffold Vue 3 project (Vite + Tailwind + Vue Router + Pinia)
- [ ] Homepage: hero, mission, stats, events preview, CTA
- [ ] Events page: upcoming and past, filters
- [ ] Startups directory: grid + search
- [ ] About / Team
- [ ] Partners
- [ ] Get Involved
- [ ] Contact

### Phase 2 — Auth & accounts (Laravel)
- [ ] Google OAuth (Laravel Socialite)
- [ ] User model: name, email, role, status
- [ ] Approval flow: pending → admin approves → active
- [ ] Session management (Laravel Sanctum)
- [ ] Discourse SSO relay endpoint

### Phase 3 — Member dashboard (`/dashboard`)
- [ ] Overview: welcome panel, forum activity, upcoming events
- [ ] Profile: bio, LinkedIn, expertise tags
- [ ] My Events: registered events, calendar
- [ ] Forum feed: recent threads via Discourse API

### Phase 4 — Admin dashboard (`/dashboard/admin`)
- [ ] Pending approvals queue
- [ ] User list: view, suspend, promote
- [ ] Event management: create, edit, publish, cancel
- [ ] Announcements: broadcast to all or segments
- [ ] Forum moderation shortcuts
- [ ] Basic analytics

### Phase 5 — Discourse (`/forum`)
- [ ] Provision Azure VM, install Discourse with `DISCOURSE_RELATIVE_URL_ROOT=/forum`
- [ ] nginx reverse proxy config
- [ ] Custom dark theme matching site design
- [ ] Discourse Connect (SSO) → Laravel relay
- [ ] Seed initial forum categories (George to define — based on WhatsApp group topics)
- [ ] WhatsApp group member invite / migration flow

### Phase 6 — Deployment & launch
- [ ] Azure App Service, MySQL, Blob Storage setup
- [ ] CI/CD: GitHub Actions → Azure
- [ ] DNS: point `whartonai.studio` to Azure
- [ ] SSL certificates
- [ ] End-to-end smoke test
- [ ] WhatsApp group migration

### Open questions
- [ ] What are the initial Discourse forum categories?
- [ ] Who are the other admins besides George?
- [ ] API location: `whartonai.studio/api` or `api.whartonai.studio`?
- [ ] Email provider for transactional mail (approvals, event reminders)?

---

## Session Notes

> Newest entry at the top. Update this at the end of every session.

**April 30, 2026 — GitHub Pages prep**
- Did: added root `/index.html` as a static GitHub Pages landing page linking to the public-site, app/admin/auth, and design-system mockups; added `.nojekyll`
- Left off at: ready to push to GitHub and enable Pages from `main` root, but local `gh` authentication tokens are currently invalid
- Watch out for: `legacy/` remains ignored and should not be pushed

**April 30, 2026 — Public site mockup**
- Did: added `/mockups/public-site.html`, a clickable public website prototype covering Home, Events, Startups, About, Partners, Get Involved, Contact, and Legal with Privacy/Cookie/GDPR sub-tabs
- Did: added old-site-inspired scroll motion to `/mockups/public-site.html`: reveal-on-scroll sections/cards, left/right converging card motion, staggered delays, count-up metrics, floating topic cluster, hero background parallax, and `prefers-reduced-motion` support
- Did: extended the homepage length with What We Do, featured startups, community voice/testimonials, newsletter, partners, and CTA-style sections
- Did: downloaded George's licensed YouTube video into `/mockups/assets/waais-hero-video.mp4` and wired it into the home hero with a dark overlay, poster fallback, and reduced-motion fallback
- Did: updated public-site top-right CTAs to use `Become a member` linking to Get Involved plus `Member sign in` for existing users; Events remains in the main nav
- Left off at: public-site design uses water-blue navbar, video-backed dark home hero, light/off-white content sections, and dark navy CTA bands
- Watch out for: some page content and team/partner examples still use legacy placeholder data and should be replaced with final WAAIS copy before implementation

**April 30, 2026 — Visual design system**
- Did: added `/mockups/design-system.html` for colors, surface rules, typography direction, buttons, cards, metrics, tags, tables, forms, and dashboard/public-page usage rules; added `/mockups/README.md`
- Did: added `legacy/` to `.gitignore` so the old React site remains local reference-only and is not included in the new repository
- Left off at: ready for design review and final copy/logo replacement before implementation
- Watch out for: visual system is first-pass design spec, not production CSS

**April 30, 2026 — Navbar color selected**
- Did: selected steel water blue `#256F8F` for the navbar/top app shell; updated `/mockups/app-dashboard-admin-auth.html` so it is the default active swatch
- Left off at: homepage body treatment should be tested as dark hero plus white/off-white scroll sections; dashboard/admin/auth remain dark
- Watch out for: Wharton navy `#011F5B` is still retained for deep brand surfaces, stats bars, CTA bands, and accents where appropriate

**April 30, 2026 — App/auth/admin mockups**
- Did: created `/mockups/app-dashboard-admin-auth.html`, a static interactive prototype covering Sign In, Pending Approval, member Overview/Profile/My Events/Forum Feed, and admin Overview/Approvals/User Management/Event Management/Announcements
- Left off at: mockup includes three switchable navbar options for comparison; steel water `#256F8F` was later selected as the default
- Watch out for: this is still a design artifact, not production Vue/Laravel code

**April 30, 2026 — Git reinitialization**
- Did: removed the broken partial `.git` directory, added a root `.gitignore`, and initialized a fresh git repository on `main`
- Left off at: repository is cleanly initialized but no initial commit has been created yet
- Watch out for: `.DS_Store` is ignored; legacy React reference files remain under `/legacy/old-react-site/`

**April 30, 2026 — Legacy site isolation**
- Did: moved the old React/Vite website out of the project root and into `/legacy/old-react-site/`; added a legacy README; updated this context file so future work references the isolated old app instead of `/src/`
- Left off at: root is now clear for the planned Vue frontend and Laravel backend folders; no new frontend/backend scaffolding has been created yet
- Watch out for: `/frontend/` and `/backend/` are still intentionally missing until scaffolded; `/tmp/waais-v2` no longer exists because it was a temporary clone
- Watch out for: `.git` still contains lock/temp files from the prior partial init, so inspect git state carefully before staging or committing

**April 30, 2026 — Design mockups**
- Did: built full interactive mockup (Home, Events, Startups, About, Forum) with Wharton colors and dark theme; George reviewed and approved overall direction; confirmed page inventory (~20 pages); created HANDOVER_TEMPLATE.md and STARTER_PROMPT.md in dev-context/
- Left off at: one pending design change — George wants to try a water blue (lighter steel blue) on the navbar/CTA band instead of the current Wharton navy. Not implemented yet. Offer 2–3 color swatches at the start of the next design session before touching anything
- Watch out for: logo is a placeholder — George will supply the club's custom brand asset; do not design around a specific logo shape yet
- Watch out for: the `/waais-website/` folder has a partial `.git` init that could not be cleaned up — original source was cloned to `/tmp/waais-v2` and key files were copied over manually

**April 30, 2026 — Project setup**
- Did: cloned repo from https://github.com/cool-machine/waais-v2, audited existing React site, defined full stack and architecture, confirmed domain (whartonai.studio), created dev-context folder with DEV_CONTEXT.md
- Left off at: architecture and stack defined, ready to move into design

---

*Last updated: April 30, 2026*
