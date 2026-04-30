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
  - Vue frontend (created locally; untracked until committed): `/Users/gg1900/coding/waais-website/frontend/`
  - Laravel backend (to be created; missing is expected): `/Users/gg1900/coding/waais-website/backend/`
  - This file: `/Users/gg1900/coding/waais-website/dev-context/DEV_CONTEXT.md`
- [ ] Also read `/Users/gg1900/coding/waais-website/dev-context/CURRENT_STATE.md` for the latest concise handoff
- [ ] If Vue implementation is being continued, read `/Users/gg1900/coding/waais-website/dev-context/VUE_FRONTEND_HANDOFF.md`
- [ ] Read the Session Notes at the bottom — they reflect the most recent state of work
- [ ] Note anything that has moved or changed, and update this file before starting

---

## 1. Recent Decisions

> Locked in. Don't revisit without good reason.

**Project**
- Platform for the **Wharton Alumni AI Studio and Research Center** — community of Wharton alumni working in AI
- Domain: `whartonai.studio`
- Forum at `forum.whartonai.studio` (Discourse on subdomain)
- API at `whartonai.studio/api` — exact subdomain vs. path still TBD
- Old broken site: `https://cool-machine.github.io/waais-v2/` — reference only
- New design mockup repo: `https://github.com/cool-machine/waais-website`
- GitHub Pages mockup URL: `https://cool-machine.github.io/waais-website/`
- Old React app isolated in `/legacy/old-react-site/` for content, copy, and page structure reference

**Tech stack**
- Frontend: **Vue 3** (Composition API) + Vite + Tailwind CSS
- Backend: **Laravel (PHP)** — REST API, auth, events, Discourse SSO relay
- Forum: **Discourse** (self-hosted on Azure VM)
- Auth: **Google OAuth** via Laravel Socialite — new accounts start as `pending`, require admin approval
- Future auth possibility: PennKey could be ideal for University of Pennsylvania / Wharton affiliation verification, but it likely requires Penn/Wharton IT approval. Do not block the first implementation on PennKey; keep Google OAuth for now and document PennKey as a future institutional integration.
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
- `anonymous visitor` → logged-out public visitor; can see public pages only
- `pending` → registered / application submitted / Google identity known, but not approved; not shown in directory or forums, including private forums
- `member` → approved alumni member; gets dashboard + forum access
- `student` → current student; must be tracked distinctly from alumni because access rules may differ
- `partner_guest` → invited non-alumni partner/guest; can receive full access when approved/invited
- `admin` → all member features + user management, event management, announcements, moderation
- `super_admin` → George plus at most two designated super admins; only super admins can promote a user to admin or remove admin privileges
- Discourse SSO is automatic — no separate forum account needed
- Admins: George + a small group (names TBD)
- Application editing: applicants/members should be able to edit submitted application/profile fields, similar to the current Google Forms workflow, but should not be able to delete their application.
- Application audit history: keep a change history so admins can see what changed, when, and by whom.
- Identity fields: after account creation, users should not be able to freely change legal identity fields such as first name, last name, verified email, or linked Google identity without admin review.
- Display identity: users may set a display name/username for public/member-facing visibility where appropriate. Admins should still see the real identity and audit history.
- Anonymous posting: approved members may be able to choose anonymous display for individual posts/comments where enabled. This is separate from `anonymous visitor`; admins/super admins should retain the ability to audit the real identity if needed.
- Rejected applicants can reapply; they do not necessarily need a new invitation.
- Form/settings governance: admins or super admins should be able to configure which application fields are visible/required where allowed, but members/non-admins cannot. Super admins can override admin settings and lock critical settings.
- Content governance: admins can edit, publish, hide, archive/remove events, startups, partners, homepage cards, and announcements. Super admins can override admin changes and change whether admins may publish directly.
- Team member profiles: team members should be able to edit their own names/profile details. Admins can manage team member records as needed, but team members should own routine self-updates.
- Data ownership: after public content is approved/published, only admins or super admins can edit events, startups, partners, homepage cards, announcements, and other public content. Regular members cannot directly change public content.
- Admin permissions: at launch, all admins have equal permissions. Super admins have higher privileges and can override anything admins can do.
- Remove behavior: remove should first mean hidden/archived for a retention period; hard deletion can happen later according to policy.
- Audit trail: record who did what and when for application edits, admin settings changes, content changes, publish/hide/archive/remove actions, and role changes.
- Public content status: all public content should support draft, published, hidden, and archived statuses, at least visible to super admins.
- Email/notifications: application submission sends an automatic email thanking the applicant by name and saying WAAIS will get back to them as soon as possible.
- Email/notifications: admins receive an email when a new application is submitted.
- Email/notifications: approval emails and request-more-information emails should be supported.
- Email/notifications: rejection emails are optional and only sent if an admin chooses to send one.
- Email/notifications: event registration sends confirmation and reminder emails; reminder timing is admin-configurable and defaults to two days before the event.
- Email/notifications: announcements go by both email and dashboard notification.
- Event visibility: each event must support a visibility setting of public, members-only, or mixed.
- Event registration: current registration may happen through external tools such as NationBuilder; WAAIS should support external registration links now and can add internal RSVP/registration later.
- Events need capacity limits and waitlists.
- Cancelled events should be hidden from public views but remain visible to admins.
- Past events should support recap pages.
- Forum visibility: public users can see some public forum content; approved members should be able to see all member forums.
- Startup submissions: approved members can submit startup listings; admins review/approve before they are published on the website.
- Startup directory: every published startup should have a dedicated detail page.
- Forum URL decision: use `forum.whartonai.studio`, not `/forum`, to avoid fragile subfolder/reverse-proxy complexity
- Public site may keep a `/forum` route or nav link that redirects users to `https://forum.whartonai.studio`
- Public navigation should include a Forum item. In mockups, it opens an internal forum preview page so review does not navigate to the not-yet-installed `forum.whartonai.studio`.
- Forum taxonomy should imitate the current WhatsApp structure with two major category families:
  - Industry-based groups are primary.
  - Region-based groups also exist, but are secondary.
  - Users should be able to define/propose regions and industries even if they are not in the launch list.
  - Launch industry examples: Finance, Fintech, Investments in AI, AI Engineering, AI Theory, AI in Business, Publishing.
- Discourse UX target: close to PyTorch forums / fast.ai forums — category grid, topic lists, threaded discussion feel, straightforward technical-community navigation
- Topic creation and replies inside approved industry/category spaces should not require pre-approval by default.
- Admins must be able to remove or moderate posts/topics if behavior or content is inappropriate.
- Forum public/private visibility: publishers can request that a discussion be public, but admins must approve whether it becomes public.
- Public site forum teasers: show latest/selected public forum topics as teasers, but admins must approve or curate which topics appear publicly.

**Page inventory — ~20 pages total**

| Section | Pages |
|---|---|
| Public site | Home, Events, Startups, About / Team, Partners, Membership, Contact |
| Legal | Privacy Policy, Cookie Policy, GDPR Request |
| Auth | Sign In, Membership Application, Pending Approval screen |
| Member dashboard | Overview, Profile, My Events, Forum Feed |
| Admin dashboard | Approvals Queue, User Management, Event Management, Public Content Management, Announcements |
| Forum | `forum.whartonai.studio` — Discourse app, not a Vue page |

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

**Task: Vue frontend scaffold started, paused for handoff**

The design phase has produced static HTML/CSS/JS prototypes for the public site, auth/member/admin app, and visual design system. George reviewed the key product flows and access rules. Vue frontend scaffolding has started on branch `codex/vue-frontend-scaffold`, but implementation was paused due to token limits.

Important current implementation state:
- `frontend/` exists locally and is untracked in git.
- `frontend/` was generated with Vite/Vue and then modified into a first WAAIS route/component scaffold.
- `npm run build` passed inside `/frontend/`.
- The Vue dev server was started on `http://127.0.0.1:5174/` and then stopped before handoff.
- Read `/Users/gg1900/coding/waais-website/dev-context/VUE_FRONTEND_HANDOFF.md` before continuing implementation.

- [x] Audit existing React codebase
- [x] Define stack, domain, architecture, design direction
- [x] Create developer context file and handover templates
- [x] Build interactive mockup: Home, Events, Startups, About, Forum (all 5 pages, clickable tabs)
- [x] George reviewed mockup — colors approved, layout direction approved
- [x] Isolate old React/Vite website under `/legacy/old-react-site/` as reference-only material
- [x] Create interactive app/auth/admin mockup: Sign In, Pending Approval, member dashboard pages, admin dashboard pages
- [x] Create visual design system mockup: colors, surfaces, typography, buttons, cards, tables, forms, tags
- [x] Create public-site mockup: Home, Events, Startups, About, Partners, Membership, Contact, Legal
- [x] Correct membership UX: Become a Member landing, existing-member sign-in, new-member application form, non-member actions
- [x] Add gated directory treatment for startup directory preview
- [x] Expand admin mockup for public content management: events, startups, partners, homepage cards
- [x] Add homepage/public-site scroll motion: reveal-on-scroll, left/right converging sections, stat count-ups, floating topic cluster, subtle hero parallax, reduced-motion fallback
- [x] Add local homepage hero video asset from George's licensed YouTube upload
- [x] Resolve navbar color: selected steel water blue `#256F8F`
- [x] Finalise first-pass design system: confirm colors, typography direction, spacing, components, dark/light surface rules
- [x] Extend mockup to cover Dashboard (member), Dashboard (admin), Sign In, Membership, and application/pending states
- [ ] George supplies brand/logo asset — drop into mockup
- [x] Deploy current mockups to GitHub Pages from `main`
- [x] Confirm George's design review items before Vue build: membership flow, admin content controls, homepage video/motion, public/dashboard navigation
- [x] Replace simplified membership form with the current Google Forms questionnaire fields
- [x] Replace dead external Forum nav link with an internal forum preview page until Discourse is installed
- [x] Hand mockup to developer as the visual spec for the Vue build
- [x] Create branch `codex/vue-frontend-scaffold`
- [x] Create initial `/frontend/` Vite/Vue scaffold
- [x] Install Vue Router, Pinia, Tailwind, and Tailwind Vite plugin
- [x] Add first-pass public Vue route/component scaffold
- [x] Verify frontend build with `npm run build`
- [ ] Review, clean, stage, and commit `/frontend/`

**Design decisions from mockup review**
- Wharton colors (#011F5B navy, #C41E3A crimson) are confirmed — do not change these
- Navbar uses steel water blue `#256F8F` with white text
- Logo is a placeholder — do not design around it yet
- Forum page (Discourse-style) design approved: category grid + topic list, same dark theme as main site
- Public homepage can use white/off-white scroll sections after the dark hero; app/dashboard/admin/auth should remain dark for consistency and focus

**Important implementation status**
- Current deployed site is **not Vue** and **not PHP/Laravel**. It is static HTML/CSS/JS mockup code.
- Admin screens are **design-only**. They do not persist data, authenticate admins, or publish real content yet.
- Membership application, sign-in, pending approval, and gated directory states are **design-only**. Real logic belongs in the future Vue + Laravel build.
- The GitHub Pages root redirects to `/mockups/public-site.html`; the admin mockup is at `/mockups/app-dashboard-admin-auth.html`.

**Relevant files**
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/pages/` — old page structure to reference
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/data/` — events, startups, partners, team data
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/src/components/` — old components for layout reference
- `/Users/gg1900/coding/waais-website/legacy/old-react-site/package.json` — old React/Vite dependency manifest, reference only
- `/Users/gg1900/coding/waais-website/mockups/design-system.html` — visual design system and component rules
- `/Users/gg1900/coding/waais-website/mockups/public-site.html` — clickable public website prototype; GitHub Pages root redirects here
- `/Users/gg1900/coding/waais-website/mockups/assets/waais-hero-video.mp4` — local homepage hero video asset
- `/Users/gg1900/coding/waais-website/mockups/app-dashboard-admin-auth.html` — interactive mockup for auth, member dashboard, and admin dashboard, including admin public-content management
- `/Users/gg1900/coding/waais-website/dev-context/CURRENT_STATE.md` — concise latest handoff summary
- `/Users/gg1900/coding/waais-website/index.html` — GitHub Pages redirect to the public-site mockup

---

## 3. Remaining Steps

### Design (current phase)
- [x] Navbar water blue color decision
- [x] First-pass visual design system
- [x] Mockups for: member dashboard, admin dashboard, sign-in page
- [x] Mockups for: public pages including Membership page
- [x] Mockups for: admin public content management for events, startups, partners, homepage cards
- [ ] Logo asset from George → place in all mockups
- [x] Final design sign-off before dev starts, especially membership/auth flow and admin content management UX

### Phase 1 — Public site (Vue frontend)
- [x] Scaffold Vue 3 project (Vite + Tailwind + Vue Router + Pinia) locally; not committed yet
- [ ] Convert design-system tokens/components into reusable Vue/Tailwind primitives
- [ ] Homepage: video hero, scroll motion, mission, stats, events preview, startup preview, partner preview, CTA
- [ ] Events page: upcoming and past, filters, clickable event cards, event detail pages, and past-event recap pages
- [ ] Startups directory: public teaser + gated member-only full directory treatment, member startup submissions, admin approval, clickable startup cards, and startup detail pages
- [ ] About / Team
- [ ] Partners with clickable partner cards leading to partner detail pages or external partner websites
- [ ] Membership landing page: existing-member sign-in, new-applicant application, non-member actions
- [ ] Contact
- [ ] Legal pages: Privacy Policy, Cookie Policy, GDPR Request

### Phase 2 — Auth & accounts (Laravel)
- [ ] Google OAuth (Laravel Socialite)
- [ ] User model: name, email, role, status
- [ ] Role/status model must support anonymous visitor, pending, member, student, partner/guest, admin, and super_admin
- [ ] Pending users must not appear in directory or forum
- [ ] Only super_admin users can promote another user to admin or remove admin privileges
- [ ] Limit super_admin users to George plus at most two designated others
- [ ] Partner/guest users can receive full access when explicitly approved/invited
- [ ] Membership application data model should mirror the current Google Form but with updated phone wording: email, first name, last name, optional phone associated with WhatsApp account, alumnus/a yes/no, free-text school affiliation, graduation year, inviter name for non-alumni, primary/secondary location, LinkedIn, experience, expertise, industries to add value to, industries to extend expertise to, availability, optional gender, optional age
- [ ] Phone associated with WhatsApp account is optional; ask only for applicants who also want to join the WhatsApp community
- [ ] Gender and age are optional
- [ ] No file upload / proof of affiliation required for v1; admin review is enough
- [ ] Alumni question must be yes/no only
- [ ] School affiliation must be free text, not a predefined student/faculty/staff dropdown
- [ ] Keep alumni status explicit because non-alumni/current students may need different access rules
- [ ] Allow applicants/members to edit application/profile answers after submission, but do not allow self-deletion
- [ ] Store application revision history for admin review and conflict resolution
- [ ] Lock legal identity fields after verification unless admin-approved
- [ ] Add optional public/member-facing display name or username separate from real identity
- [ ] Support optional anonymous posting/display mode for approved members where enabled, while preserving admin auditability
- [ ] Support rejection plus later reapplication
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
- [ ] Event visibility setting: public, members-only, or mixed
- [ ] External registration link support, including current NationBuilder-style registration flow
- [ ] Capacity limits and waitlist support
- [ ] Cancelled events hidden publicly but visible to admins
- [ ] Past event recap page management
- [ ] Public content management: create/edit/publish/hide/remove cards for events, startups, partners, homepage modules
- [ ] Startup listing management: members can submit listings; admins review/approve/update startup cards and member-only profile visibility
- [ ] Partner listing management: create/edit/publish/hide partner cards
- [ ] Team member self-editing for own profile details, with admin/super-admin oversight
- [ ] Announcements: broadcast to all or segments
- [ ] Application form settings: configure field visibility/requiredness where allowed; critical settings lockable by super_admin
- [ ] Content publishing policy setting: default admins can publish directly; super_admin can change this policy
- [ ] Remove/archive workflow: hide/archive first, hard delete only later by policy
- [ ] Admin audit log: who did what and when for content, form settings, role changes, and application/profile edits
- [ ] Public content statuses: draft, published, hidden, archived
- [ ] Equal admin permissions at launch, with super_admin override privileges
- [ ] Forum moderation shortcuts
- [ ] Basic analytics

### Phase 5 — Discourse (`forum.whartonai.studio`)
- [ ] Provision Azure VM and install Discourse using the official Docker-based install
- [ ] DNS: point `forum.whartonai.studio` to the Discourse VM
- [ ] SSL certificate for `forum.whartonai.studio`
- [ ] Custom dark theme matching site design
- [ ] Discourse Connect (SSO) → Laravel relay
- [ ] Seed initial forum categories with industry-first structure; initial examples include Finance, Fintech, Investments in AI, AI Engineering, AI Theory, AI in Business, Publishing
- [ ] Support user-defined/proposed regions and industries beyond launch categories
- [ ] Topic creation and replies inside approved categories should not require pre-approval by default
- [ ] Admins must be able to remove/moderate inappropriate posts/topics
- [ ] Public discussion workflow: publisher can request public visibility; admin approves whether it becomes public
- [ ] Forum visibility split: public users can see some public forum content; approved members can see all member forums
- [ ] Public site should show latest/selected public forum topics as teasers, curated or approved by admins
- [ ] WhatsApp group member invite / migration flow

### Phase 6 — Deployment & launch
- [ ] Azure App Service, MySQL, Blob Storage setup
- [ ] CI/CD: GitHub Actions → Azure
- [ ] DNS: point `whartonai.studio` to Azure
- [ ] SSL certificates
- [ ] End-to-end smoke test
- [ ] WhatsApp group migration

### Email and notification requirements
- [ ] Application auto-reply: thank applicant by name and confirm WAAIS will respond as soon as possible
- [ ] Admin notification email for each new application
- [ ] Approval email
- [ ] Request-more-information email
- [ ] Optional rejection email, sent only when an admin chooses to send it
- [ ] Event registration confirmation email
- [ ] Event reminder email with admin-configurable timing, default two days before event
- [ ] Announcements sent through both email and dashboard notification

### Open questions
- [ ] PennKey feasibility: can WAAIS verify PennKey without institutional approval, or is Penn/Wharton IT approval required?
- [ ] Exact initial Discourse region categories?
- [ ] Exact initial Discourse industry categories beyond Finance, Fintech, Investments in AI, AI Engineering, AI Theory, AI in Business, Publishing?
- [ ] Who are the other admins besides George?
- [ ] API location: `whartonai.studio/api` or `api.whartonai.studio`?
- [ ] Email provider for transactional mail: decide later; candidates include Azure Communication Services Email and Google for Nonprofits / Google Workspace options

---

## Session Notes

> Newest entry at the top. Update this at the end of every session.

**April 30, 2026 — Vue frontend scaffold started**
- Did: created branch `codex/vue-frontend-scaffold`
- Did: generated `/frontend/` with Vite/Vue, installed dependencies, added Vue Router, Pinia, Tailwind, and first WAAIS public route/component scaffold
- Did: added Vue pages for Home, Events, Startups, About, Partners, Membership, Forum Preview, Contact, and Legal, plus reusable layout/card/hero components and static data files
- Did: verified `npm run build` succeeds inside `/frontend/`
- Left off at: `/frontend/` exists locally but is untracked; implementation paused for handoff before cleanup/commit
- Watch out for: default Vite README/assets may still need cleanup; Vue pages are not pixel-perfect mockup conversions yet; backend/Laravel not started

**April 30, 2026 — Product review decisions finalized**
- Did: reviewed and documented membership/auth, role model, application form, admin content governance, event rules, startup directory rules, Discourse/forum behavior, data ownership, audit trail, and email/notification requirements
- Did: updated `/mockups/public-site.html` membership form to use the Google Forms question set with optional phone associated with WhatsApp account, optional gender, optional age, yes/no alumni status, and free-text school affiliation
- Did: confirmed `forum.whartonai.studio` as the future Discourse subdomain; current mockup uses an internal Forum preview page until Discourse is installed
- Did: verified key context/mockup paths exist; `/frontend/` and `/backend/` are still intentionally missing until implementation begins
- Left off at: ready to scaffold Vue frontend from the static mockups
- Watch out for: current GitHub Pages deployment is still static HTML/CSS/JS, not Vue/PHP

**April 30, 2026 — Session documentation refresh**
- Did: added `/dev-context/CURRENT_STATE.md` as a concise recovery handoff for the current design/prototype state
- Did: updated `/dev-context/DEV_CONTEXT.md` to clarify that current outputs are static mockups, not Vue/PHP implementation, and to list membership/admin CMS flows as designed but not functional
- Did: updated `/mockups/README.md` and `/dev-context/STARTER_PROMPT.md` with direct URLs and instructions to read both context files in future sessions
- Left off at: documentation now captures the latest steps, what is happening now, and the remaining next steps before Vue/Laravel implementation
- Watch out for: the next session should start with design review or Vue scaffolding, not backend/admin logic yet

**April 30, 2026 — GitHub Pages prep**
- Did: added root `/index.html` as a static GitHub Pages landing page linking to the public-site, app/admin/auth, and design-system mockups; added `.nojekyll`
- Did: created public GitHub repo `https://github.com/cool-machine/waais-website`, pushed `main`, and enabled GitHub Pages from `main` root at `https://cool-machine.github.io/waais-website/`
- Did: changed root `/index.html` to redirect directly to `/mockups/public-site.html` so the Pages URL opens the video/motion public homepage instead of the static mockup index
- Left off at: GitHub Pages is enabled; deployment may take a short time to become available after the first push
- Watch out for: `legacy/` remains ignored and should not be pushed

**April 30, 2026 — Membership flow and admin CMS mockups**
- Did: changed the public CTA/nav from generic Get Involved to Become a Member; the membership page now has existing-member Sign In, new-applicant Apply for Membership, and non-member actions for topic proposals, partnerships, and startup listing requests
- Did: added a membership application form state and a gated startup-directory preview explaining that full startup profiles require approved member access
- Did: expanded `/mockups/app-dashboard-admin-auth.html` with a Public Content admin section for editing/publishing/hiding/removing public-site cards for events, startups, partners, and homepage content
- Left off at: these are mockup-only flows; real sign-in, admin permissions, CMS persistence, and public card publishing still need Vue/Laravel implementation

**April 30, 2026 — Public site mockup**
- Did: added `/mockups/public-site.html`, a clickable public website prototype covering Home, Events, Startups, About, Partners, Membership, Contact, and Legal with Privacy/Cookie/GDPR sub-tabs
- Did: added old-site-inspired scroll motion to `/mockups/public-site.html`: reveal-on-scroll sections/cards, left/right converging card motion, staggered delays, count-up metrics, floating topic cluster, hero background parallax, and `prefers-reduced-motion` support
- Did: extended the homepage length with What We Do, featured startups, community voice/testimonials, newsletter, partners, and CTA-style sections
- Did: downloaded George's licensed YouTube video into `/mockups/assets/waais-hero-video.mp4` and wired it into the home hero with a dark overlay, poster fallback, and reduced-motion fallback
- Did: updated public-site top-right CTAs to use `Become a member` linking to the Membership flow plus `Member sign in` for existing users; Events remains in the main nav
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
