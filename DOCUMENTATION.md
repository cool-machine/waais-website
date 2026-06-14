# Wharton Alumni AI Studio (WAAIS) — Project Documentation

_Last updated: June 2026._

This document is the single reference for the WAAIS platform: what it is, how the
website (frontend + backend) and the Discourse forum are built, how content and
roles are managed, how it deploys, and a changelog of work done. It is meant to
be readable by a non-developer admin **and** by a developer picking up the code.

There is also a `dev-context/` folder in the repo with the original design notes
and the Discourse provisioning scripts (`AZURE_PRODUCTION.md`,
`PLATFORM_MODEL.md`, `PRODUCT.md`, `PRIVACY_READINESS.md`, plus the
`*-discourse-*.sh` / `configure-sso.sh` scripts). This file summarizes and
updates that context.

---

## 1. What WAAIS is

The **Wharton Alumni AI Studio** is an applied-AI community, founded in 2020 in
collaboration with the **Wharton Club of the United Kingdom**. In the header it
appears short ("Wharton Alumni AI Studio" / "Affinity Group · Wharton Club of
the UK"); the full name ("Affinity Group of the Wharton Club of the United
Kingdom") is used where there is room, e.g. the footer and the About page.

The platform has three public-facing surfaces:

- **The website** — `https://whartonai.studio` (marketing/public pages + a member dashboard).
- **The API** — `https://api.whartonai.studio` (Laravel backend the website talks to).
- **The forum** — `https://forum.whartonai.studio` (self-hosted Discourse).

---

## 2. Architecture & hosting

| Component | Technology | Hosting | URL |
|---|---|---|---|
| Frontend | Vue 3 + Vite + Pinia + Vue Router | Azure Static Web Apps | `whartonai.studio` |
| Backend API | Laravel 11 (PHP 8.5) | Azure App Service (Linux, **Free tier**) | `api.whartonai.studio` |
| Database | SQL (managed on Azure in prod; SQLite in local dev) | Azure | — |
| Forum | Discourse (official Docker install) | Azure VM, self-managed (user `waaisops`, `/var/discourse`) | `forum.whartonai.studio` |
| Email | Azure Communication Services Email | Azure | — |
| Auth | Google OAuth + email/password | — | — |

Sign-in: members authenticate (Google OAuth or email) on the website; the Laravel
backend issues sessions and also acts as the **DiscourseConnect (SSO) provider**
for the forum, so one account works across the dashboard and the forum.

A note on the Free App Service tier: the backend "cold-starts" after idle, so the
first request can take a couple of seconds before it's fast again. This is normal
for that tier (Always On isn't available below Basic). The frontend mitigates the
*perceived* delay with a small browser cache (see §5).

---

## 3. Repository layout

```
waais-website/
├── backend/                 # Laravel 11 API + Discourse SSO + content models
│   ├── app/
│   │   ├── Console/Commands/ # importers, ensure-super-admin, email senders
│   │   ├── Enums/            # PermissionRole, ContentStatus, ContentVisibility, ...
│   │   ├── Http/Controllers/ # Api/ (public), Api/Admin/ (admin), Auth/
│   │   ├── Http/Middleware/  # access gates (admin/super-admin/member/area)
│   │   └── Models/
│   ├── database/
│   │   ├── data/            # one-time seed files (events, startups, partners, team)
│   │   └── migrations/
│   ├── routes/api.php
│   └── startup.sh           # runs on every App Service boot
├── frontend/                # Vue 3 SPA
│   ├── public/
│   │   ├── brand/           # site logo mark
│   │   ├── logos/           # partner + startup logos
│   │   └── team/            # founder/advisor photos
│   └── src/
│       ├── components/      # PublicLayout, PageHero, CardGrid, InfoCard
│       ├── lib/             # api client, persistentCache
│       ├── pages/           # all routed views
│       ├── router/
│       └── stores/          # Pinia stores (public + admin + auth)
├── dev-context/             # original design docs + Discourse provisioning scripts
└── .github/workflows/       # ci.yml, deploy-backend.yml, deploy-frontend.yml
```

---

## 4. Backend (Laravel API)

### 4.1 Authentication & accounts

- **Google OAuth** (`Auth/GoogleAuthController`) and **email/password**
  (`Auth/EmailAuthController`, `Auth/PasswordAuthController`).
- New accounts start as **pending** and must be approved by an admin before they
  can access member areas or the forum.
- `Auth/DiscourseSsoController` is the **forum SSO relay** (see §6).

### 4.2 Roles & permissions (RBAC)

`Enums/PermissionRole`: `public`, `pending_user`, `member`, `admin`,
`super_admin`. Plus per-area boolean flags on the user: `can_manage_events`,
`can_manage_partners`, `can_manage_startups`.

`User` model helpers: `isAdmin()`, `isSuperAdmin()`, `canAccessMemberAreas()`,
`canManageEvents()/Partners()/Startups()`, scopes `admins()`, `superAdmins()`.

Access gates (middleware):

- `EnsureMemberAccess` (`member.access`) — approved members.
- `EnsureAdminAccess` (`admin.access`) — any admin.
- `EnsureSuperAdminAccess` (`super_admin.access`) — super admins only.
- `EnsureAreaAccess` (`area:events|partners|startups`) — per-area content admins.

Who can do what:

- **Super admin** — everything, including managing other admins' roles/areas, plus
  super-admin-only content (homepage cards, announcements, team, membership
  approvals, user directory).
- **Area admin** (events / partners / startups) — manage just that content area.
- **Member** — member dashboard + forum (post/reply).
- **Pending / rejected** — no member or forum access.

### 4.3 Content models

`Models/`: `Event`, `Partner`, `StartupListing` (+`StartupListingRevision`),
`TeamMember`, `HomepageCard`, `Announcement` (+`AnnouncementEmailDelivery`),
`MembershipApplication` (+`ApplicationRevision`), `ContactMessage`, `AuditLog`,
`EventReminderDelivery`, `User`.

Shared content lifecycle via two enums:
- `ContentStatus`: `draft`, `pending_review`, `published`, `hidden`, `archived`.
- `ContentVisibility`: `public`, `members_only`, `mixed`.

Public surfaces only ever show **published** + **public/mixed** items; everything
else is invisible to anonymous visitors. Admin write actions are recorded in
`AuditLog`.

### 4.4 Public API (`/api/public/...`)

Anonymous, read-only, allow-list projections (internal fields never leak):

- `GET /events`, `GET /events/{id}` — `?time=upcoming|past|all`.
- `GET /startup-listings`, `GET /startup-listings/{id}`.
- `GET /partners`, `GET /partners/{id}`.
- `GET /team-members` — founders first, then advisors, by sort order.
- `GET /news` — AI/analytics news aggregator (see §4.6).
- `GET /homepage-cards` — homepage CMS cards.

### 4.5 Admin API (`/api/admin/...`, gated)

- Events (`area:events`): full CRUD + publish/hide/archive/cancel.
- Partners (`area:partners`): full CRUD + publish/hide/archive.
- Team members (super admin): full CRUD + publish/hide/archive.
- Homepage cards, announcements, membership applications, users + role/area
  assignment: **super admin only**.
- Startup listings: review workflow (approve / reject / request info). Listing
  content is owner-editable via the member endpoints; the imported startups are
  owned by the super-admin account.

### 4.6 News aggregator (`NewsController`)

- Fetches official RSS feeds (Knowledge@Wharton, Penn Today) server-side with a
  browser User-Agent.
- Scores items by relevance — strong AI terms (artificial intelligence, machine
  learning, generative, LLM/GPT…) rank highest, then medium terms incl.
  **quantitative finance / analytics / equity research**, then weak ("analytics"
  alone). AI/analytics items are shown first; recent general items backfill so the
  feed is never empty.
- Output: title, source, date, short excerpt, outbound link (we never republish
  full articles).
- Cached ~3 hours in the database cache store, so it's fast and makes no
  per-request external calls (no scheduler needed).

### 4.7 One-time importers (content seeds)

`Console/Commands/`: `ImportEvents`, `ImportStartups`, `ImportPartners`,
`ImportTeam`. Each reads a JSON file from `backend/database/data/` and is
**create-only / idempotent** (keyed on `external_ref` for events, `website_url`
for startups/partners, `name` for team). They were used to seed initial content,
then **disabled in `startup.sh`** so they never re-run on deploy and never clobber
edits made in the dashboard. They remain in the repo as a re-runnable archive
(run by hand, optionally with `--update` to overwrite, `--dry-run` to preview).

### 4.8 Scheduled / background work

- `SendAnnouncementEmails`, `SendEventReminders` — outbound member emails.
- `EnsureSuperAdmin` — promotes the configured super-admin if missing (command
  kept; the old every-minute schedule was removed).
- All transactional email is **sent after the HTTP response** (via
  `App::terminating()`), so requests aren't blocked by SMTP latency. Notification
  links point at the frontend (`config('app.frontend_url')`), not the API host.

### 4.9 Deploy boot script (`startup.sh`)

Runs on every App Service boot: reconfigures nginx to serve Laravel's `public/`,
then `migrate --force`, `config:cache`, `route:cache`, `view:cache`,
`storage:link`. The content-import lines are present but commented out (one-time
seeds already applied).

---

## 5. Frontend (Vue SPA)

### 5.1 Pages (`src/pages/`) & routes (`src/router/index.js`)

Public: `HomePage`, `EventsPage` + `EventDetailPage`, `StartupsPage` +
`StartupDetailPage`, `PartnersPage` + `PartnerDetailPage`, `NewsPage`,
`AboutPage`, `MembershipPage`, `ForumPreviewPage` (`/forum`), `ContactPage`,
`LegalPage`, `SignInPage`, `ResetPasswordPage`.
App/member + admin: `AppMockupPage` (`/app/:view?`) — the dashboard.

Public nav: Home · Events · Startups · News · About · Partners · Forum · Contact.

### 5.2 Components

- `PublicLayout` — header (brand lockup + nav + auth-aware CTAs) and footer.
  The header is session-aware: signed-in members see "Member dashboard"; visitors
  see "Become a member" / "Member sign in".
- `PageHero` — page hero (optional eyebrow, title, lede, optional video/poster).
- `CardGrid` — responsive card grid.
- `InfoCard` — the shared card (logo/photo "chip" with monogram fallback,
  title, meta, body, bottom-aligned action button).

### 5.3 Stores (`src/stores/`, Pinia)

- Public: `publicEvents`, `publicStartups`, `publicPartners`, `publicTeam`,
  `publicNews`, `publicHomepageCards`.
- Member/admin: `authUser`, `membershipApplication`, `myStartups`,
  `adminEvents`, `adminPublicContent` (homepage cards / partners / **team**),
  `adminStartupListings`, `adminAnnouncements`, `adminMembershipApplications`,
  `adminUsers`, `memberAnnouncements`, `contactMessage`.
- `src/lib/persistentCache.js` — small **localStorage stale-while-revalidate**
  cache used by the public list stores. On reload it paints the last-seen
  content instantly, then revalidates with the normal single API call. No extra
  requests, no impact on the backend — purely a perceived-speed improvement for
  the Free-tier cold start.

### 5.4 Key UX conventions

- **Branding**: short name in tight spots (header), full name where there's room
  (footer / About).
- **Logos**: each logo/photo renders as a centered, contained "chip" with a thin
  border on a soft tile, so light/white logos are separated from the background;
  anything missing falls back to a clean initials **monogram** (and a broken
  image auto-falls back too).
- **Responsive grids**: card rows are 3-up by default; the homepage feature rows
  use a `.grid.show-4` (4-up on desktop/tablet → 2 on large phones → 1 on
  phones).
- **Card buttons** are pinned to the bottom of each card so they line up across a
  row regardless of text length.

### 5.5 Admin dashboard (`AppMockupPage`)

Per-area gated. Super admins see everything; area admins see only their area.
Includes: overview/quick actions, events management, **content admin** (Homepage
cards / Partners / Team — add/edit/reorder/publish/hide/archive), startup review
queue, announcements, and user + role/area management (super admin).

---

## 6. Forum (Discourse)

- **Where**: `forum.whartonai.studio`, a self-managed **Azure VM** running the
  official Discourse **Docker** install (`/var/discourse`, server user
  `waaisops`). Provisioning/SSO scripts are in `dev-context/`
  (`vm-prep.sh`, `provision-discourse-*.sh`, `configure-sso.sh`,
  `create-first-admin.sh`). Forum title: "WAAIS Forum".

- **Login & access**: Discourse uses **DiscourseConnect (SSO)** pointed at the
  Laravel relay. `DiscourseSsoController` only lets users who pass
  `canAccessMemberAreas()` (approved members) through; pending/rejected users are
  bounced to a pending page. So **approved = in, not-approved = out**.

- **Role mapping** (driven by the website, applied on each SSO login):
  - **Super admin** → Discourse **admin** (`admin: true`) — full control,
    promote/demote, manage groups.
  - **Admin** → Discourse **moderator** (`moderator: true`) — moderation.
  - **Member** → regular user — post and reply.
  - SSO also sends groups `waais_members` / `waais_admins`. **Group sync is
    currently off** (`discourse_connect_overrides_groups = false`) to avoid login
    errors until those groups exist in Discourse — admin/moderator status is
    independent and already works. Re-enable group sync after creating the
    groups, which is also the path to future **domain-specific (category)
    moderators**.

- **Updates** (self-managed): routine version/plugin updates are one-click at
  `forum.whartonai.studio/admin/upgrade`; updates that need a base-image change
  are `cd /var/discourse && git pull && ./launcher rebuild app` over SSH. **None
  of this deletes data** (it lives in PostgreSQL + the uploads volume).

- **Backups**: Discourse takes automatic backups and auto-prunes old ones
  (Admin → Settings → Backups). Keep automatic backups on; periodically keep one
  copy **off the VM** (download the `.tar.gz` or push to storage) so a lost VM
  doesn't lose the backups too. Always back up before a manual rebuild.

---

## 7. Content management model

Day-to-day content is managed **in the admin dashboard**, not in code:

| Content | Managed in | Visibility gate |
|---|---|---|
| Events | Admin → Events (events admins / super admin) | published + public |
| Partners | Admin → Content → Partners (partners admins / super admin) | published + public/mixed |
| Startups | Admin → Startups (review) + owner editing | published + public |
| Team (founders/advisors) | Admin → Content → Team (super admin) | published + public/mixed |
| Homepage cards | Admin → Content → Homepage cards (super admin) | published |
| News | Automatic (RSS aggregator) | n/a |

The JSON files under `backend/database/data/` are the **initial seed archive**
only; live content lives in the database and is edited in the dashboard. Logos go
in `frontend/public/logos/`, team photos in `frontend/public/team/`, referenced
as `/logos/...` or `/team/...`.

---

## 8. Deployment & operations

- **CI/CD** via GitHub Actions: `deploy-frontend.yml` (on `frontend/**` push to
  main → builds and deploys the SPA), `deploy-backend.yml` (on `backend/**` →
  runs the test suite, then deploys; `startup.sh` runs on boot), and `ci.yml`.
- **Deploys are triggered by pushing to `main`.** Frontend changes propagate in a
  couple of minutes; a content/CSS change may need a hard refresh (Cmd/Ctrl +
  Shift + R) to drop the cached bundle. Backend deploys briefly 502 while the
  container restarts.
- **Tests**: Laravel feature tests (`backend/tests/`) and Vitest unit tests
  (`frontend/src/**/*.test.js`) run before/with deploys.
- **Free-tier cold start**: first backend request after idle is slow, then fast.
  Options to remove it entirely: upgrade the App Service to Basic (B1) and enable
  "Always On". The frontend localStorage cache already hides the delay for
  returning visitors.

---

## 9. Current content inventory (as of June 2026)

- **Events — 23**, spanning 2020–2026 (AI Studio chapters/research salons, AI in
  finance, the Stefano Puntoni book launch, edge-AI fireside, Civic/Government,
  Steve Smith's Project Ollie, Lauren Cantor's "AI with Claude", the two June
  2026 Wharton Club UK events — Lunch with Prof. Kartik Hosanagar and "Investing
  in the Age of AI", etc.).
- **Startups — 9**: Rockfish Data, LotusAI, Civic, AndesML, Chapter, ficc.ai,
  Qritive, HAI Clean, Fairbanc. Homepage features 4 (Rockfish, HAI Clean,
  Qritive, Civic).
- **Partners — 6**: University of Pennsylvania, The Wharton School, Wharton Club
  of the United Kingdom, Wharton Club of France, Wharton Club of Germany &
  Austria, Edge AI Foundation.
- **Team**: founder card seeded (George Gvishiani — Founder & Chairman) as a
  draft; co-founder + board advisors to be added in the dashboard.
- **News**: live AI/analytics feed from Knowledge@Wharton (Penn Today configured;
  its CDN currently blocks the server, so it isn't contributing yet).

---

## 10. Changelog

### 10.1 Platform baseline (before this work)

Public marketing site + member dashboard; Google OAuth and email auth; membership
application + admin approval flow; Discourse SSO relay; content models and admin
CMS for events, partners, startups, homepage cards, and announcements; Azure
hosting (Static Web Apps + App Service + managed DB + ACS email) and the
self-hosted Discourse VM with DiscourseConnect SSO.

### 10.2 This work

**Auth, roles & admin**
- Header made session-aware; fixed stale signed-in content lingering after
  sign-out across public pages; added pointer cursor to buttons.
- Gated the admin dashboard nav/views and "Admin overview" to admins only.
- Built **per-area RBAC**: `can_manage_events/partners/startups` columns,
  `EnsureAreaAccess` middleware, re-gated admin routes, a set-admin-areas
  endpoint, and per-area gating + role toggles in the dashboard.
- Removed the every-minute ensure-super-admin scheduled job (kept the command).

**Performance & email**
- Deferred all transactional email to after the HTTP response (fixed ~10s
  request latency); pointed notification links at the frontend domain.
- Added a localStorage stale-while-revalidate cache for public lists (instant
  paint on reload despite Free-tier cold start).

**Public-page polish**
- Removed leftover red "eyebrow" labels and placeholder/dev copy across all
  public pages (made the eyebrow optional in `PageHero` and `InfoCard`), incl. the
  event/startup/partner **detail** pages.
- Relocated CTAs to their proper pages (Partner-with-us → Partners; Propose a
  topic → Events); cleaned the Partners page; stripped the Forum page to a tidy
  placeholder with a "Recent discussions" stub.

**Content: events, startups, partners, team**
- Imported **23 past events** (scraped from Wharton Club UK/France and related
  pages) via an idempotent importer; defaulted the Events page to show all
  (past) events; later added the two June 2026 events.
- Imported **9 alumni startups** and **6 partners**; built importers + public
  display; added real logos (chip treatment + monogram fallback); aligned card
  action buttons; removed the "Discuss partnership" button on partner detail.
- Built the **Team** feature end-to-end: `TeamMember` model/migration, public +
  admin endpoints, an About-page Founders/Advisors section, and dashboard CRUD
  (Admin → Content → Team); seeded the founder card.
- Switched every one-time importer to a "seed once, then manage in the dashboard"
  model (auto-import disabled on deploy, kept as an archive).

**News**
- Built an AI/analytics **news aggregator** (RSS → relevance-ranked, quant-finance
  included, cached) with a `/news` page, nav entry, and a homepage teaser, in a
  clean "headline list" style.

**Homepage & About**
- Corrected hero stats (740+ members, 30+ startups, 24 events/yr, 6 partners);
  replaced vague cards with clear Events/Startups/Partners cards; replaced the
  login-flow band with a **mission + member-value** section grounded in the real
  WAAIS story; featured 4 startups and 4 recent events in a responsive 4-up grid;
  moved the news section directly below the hero stats.
- Rewrote the **About page** with the full WAAIS story (2020 origin with the
  Wharton Club of the UK, the 2023 Expert Network, purpose, vision, founder) +
  a join CTA.

**Branding**
- Header uses the short subtitle ("Affinity Group · Wharton Club of the UK");
  the footer uses the full "Affinity Group of the Wharton Club of the United
  Kingdom".

**Forum**
- Verified the Discourse SSO role mapping (super admin → admin, admin →
  moderator, member → regular), and documented the safe update/backup process for
  the self-managed instance.

---

## 11. Open items / future work

- **Forum branding**: swap the header mark for a text-free Wharton Club UK shield
  (drop a transparent PNG into `frontend/public/brand/`).
- **Team**: add the co-founder and board advisors in the dashboard; add their
  photos to `frontend/public/team/`.
- **News sources**: Penn Today's CDN blocks the server — add more AI-specific
  feeds (e.g., a Wharton AI & Analytics or Kartik Hosanagar blog feed) or an
  admin "add news item" curation lane if desired.
- **Forum groups**: create `waais_members` / `waais_admins` (and area groups) in
  Discourse, then re-enable group sync for category-level (domain-specific)
  moderators.
- **Hosting**: optionally move the App Service to Basic + Always On to remove the
  cold-start delay.
- **Verification**: end-to-end Discourse role check via a test promotion.
```
