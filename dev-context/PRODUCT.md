# WAAIS — Product Description

> What this project is. Stable reference. Update only when the product itself changes, not on every code slice.

## Project Identity

WAAIS is the Wharton Alumni AI Studio platform — a community platform for Wharton alumni working in or interested in AI. WAAIS is an AI-focused affinity group within the **Wharton Alumni Club United Kingdom** chapter; the chapter has sanctioned WAAIS using the chapter's official co-brand mark on its public web property. WAAIS includes a public website, an approved-member dashboard, an admin dashboard, and a future Discourse forum.

- Domain: `whartonai.studio`
- Forum subdomain: `forum.whartonai.studio` (Discourse, hosted on its own Azure VM)
- API: `api.whartonai.studio`
- Repository: `https://github.com/cool-machine/waais-website`
- GitHub Pages preview: `https://cool-machine.github.io/waais-website/`
- Owner: George (cool.lstm@gmail.com)

## Tech Stack (locked)

- Frontend: Vue 3 (Composition API) + Vite + Tailwind v4 + Vue Router + Pinia
- Backend: Laravel (PHP) — REST API, auth, events, Discourse SSO relay
- Forum: Discourse (self-hosted on Azure VM)
- Auth: Google OAuth via Laravel Socialite plus email-link sign-in for non-Google applicants. New accounts start as pending and require admin approval. PennKey is a future possibility, not blocking v1.
- Sessions: Laravel Sanctum
- Database: SQLite for local dev/test; Azure Database for PostgreSQL Flexible Server for staging/production. Migrations should stay portable; avoid Postgres-only or MySQL-only SQL.
- Hosting: Microsoft Azure (non-profit grant, ~$2,000/year, ~$167/month cap)

## Audience

- Wharton alumni working in or interested in AI (primary)
- Current Wharton/Penn students
- Wharton/Penn faculty and staff
- Invited non-alumni partner-guests
- Anonymous public visitors (read-only access to public content)

## Page Inventory (~20 pages total)

| Section | Pages |
|---|---|
| Public site | Home, Events, Startups, About / Team, Partners, Membership, Contact |
| Legal | Privacy, Cookie, GDPR Request |
| Auth | Sign In, Membership Application, Pending Approval |
| Member dashboard | Overview, Profile, My Events, Forum Feed |
| Admin dashboard | Approvals Queue, User Management, Event Management, Public Content, Announcements |
| Forum | `forum.whartonai.studio` — Discourse, not a Vue page |

## Design Language

- Dark mode first; near-black navy background `#050E20`, high contrast.
- Modern and prestigious — closer to OpenAI/Linear than a typical university site.
- Water blue `#256F8F` — navbar / top app shell, with white text.
- Wharton navy `#011F5B` — deep brand surfaces, stats, CTA bands, accents.
- Wharton crimson `#C41E3A` — primary action / accent color: buttons, tags, eyebrow labels, category dots.
- Card surface `#0A1833` with border `rgba(100,130,200,0.14)`.
- Body text white / muted `#94A3B8`.
- Homepage uses a dark video hero, then white/off-white scroll sections for readability.
- Dashboard, admin, auth surfaces remain dark.
- Logo: the temporary site mark is the official **Wharton Alumni Club United Kingdom** co-brand lockup (white-on-transparent variants), used under the chapter's sanction since WAAIS is an affinity group within that chapter. Files live at `frontend/public/brand/wac-uk-h2-white.png` (horizontal, navbar) and `frontend/public/brand/wac-uk-v-white.png` (vertical, app sidebar). A WAAIS-specific brand mark from Penn/Wharton's brand team — if/when produced — would supersede this. The earlier billboard render is shelved until separately approved.
- Subtle animations only: reveal-on-scroll, stat count-ups, hero parallax with `prefers-reduced-motion` fallback.

The static visual references live in `/mockups/`:
- `mockups/public-site.html`
- `mockups/app-dashboard-admin-auth.html`
- `mockups/design-system.html`

## Membership Model

The platform separates three concerns rather than overloading one role field. The canonical schema and access rules live in `PLATFORM_MODEL.md`:

- `approval_status` — where the account/application sits in review.
- `affiliation_type` — Wharton/Penn/community relationship.
- `permission_role` — product permission level.

Pending users do not appear in the directory or in any forum, including private forums. Rejected applicants can reapply. Only `super_admin` users (George plus at most two designated others) can promote users to admin or remove admin privileges.

Public CTA is **Become a member**. The Membership page presents three actions:

- Existing members → Sign in
- New applicants → Apply for membership
- Non-members → Propose a topic, partner with WAAIS, or request a startup listing

## Membership Application Form (v1)

Fields mirror the current Google Forms questionnaire:

- email, first name, last name
- optional phone tied to WhatsApp community participation
- alumnus/a yes/no
- free-text school affiliation (no predefined dropdown — covers student, faculty, staff, school, program, or other)
- graduation year
- inviter name for non-alumni
- primary and secondary location
- LinkedIn URL
- experience summary
- expertise summary
- industries to add value to (multi-entry)
- industries to extend expertise to (multi-entry)
- availability
- optional gender
- optional age

No proof-of-affiliation upload in v1; admin review is sufficient. Applicants/members can edit submitted answers but cannot delete their application. The system stores a revision history of changed fields with actor and timestamp.

Identity fields (first/last name, verified email, linked Google identity) cannot be self-edited after verification. Members may set a separate display name/username for community visibility while admins retain visibility into real identity. Approved members may also choose anonymous display for individual posts/comments where enabled, with admin auditability preserved.

## Submission & Admin Review Pattern

A reusable pattern across the platform: a submitter (member or visitor) creates content; an admin reviews; the result is approved, rejected, or marked needs-more-info; every transition is audit-logged. The same `ApprovalStatus` enum, `submitted_at` / `reviewed_at` / `reviewed_by` / `review_notes` columns, `AuditLog` entries, and `admin.access` middleware are reused across:

- Membership applications (canonical first implementation)
- Startup listings (approved members propose; admins approve before publication)
- Forum public-discussion requests (publisher requests public visibility; admin approves)
- Topic proposals from non-members on the Membership page
- Future partner-listing requests if/when members can suggest partners

Implementation order: build the membership review slice first as the canonical version, mirror it for startup listings, then extend to other surfaces. Keep the schema and audit-log shape uniform so the admin UI can be consistent.

## Content Governance

Public content (events, startups, partners, homepage cards, announcements) supports `draft`, `pending_review`, `published`, `hidden`, `archived` statuses. Visibility is `public`, `members_only`, or `mixed`. Regular members cannot directly publish public content. Admins create, edit, publish, hide, and archive at launch. Super admins can override admin actions and can later change whether admins may publish directly. "Remove" means hidden/archived first; hard deletion is a later policy decision. The system keeps an audit trail covering content edits, publish/hide/archive/remove actions, form-setting changes, application/profile edits, and role changes.

Admins (and only admins) can configure which application form fields are visible/required where allowed. Super admins can lock critical settings.

## Events

Each event has:

- `visibility`: `public`, `members_only`, or `mixed`
- external registration URL (e.g., NationBuilder-style links) supported now; internal RSVP is a later option
- capacity limit and waitlist
- cancellation state — cancelled events are hidden from public views but visible to admins
- recap content for past events
- reminder timing — admin-configurable, default two days before the event

## Startup Directory

- Approved members can submit startup listings.
- Admin approval is required before listings appear publicly.
- Every published startup has its own detail page.
- Public visitors see teasers; full member/startup profiles require approved member access.

## Forum (Discourse)

- Lives at `forum.whartonai.studio` once provisioned (own Azure VM via official Docker install).
- Laravel provides the SSO relay. Discourse SSO is automatic — no separate forum account needed.
- Categories organized industry-first, with regional categories as a secondary axis.
- Launch industry examples: Finance, Fintech, Investments in AI, AI Engineering, AI Theory, AI in Business, Publishing.
- Users can propose new industries/regions beyond the launch list.
- Topic creation and replies inside approved categories do not require pre-approval; admins moderate after the fact.
- Public discussions are admin-approved before they go public. The public site shows curated public-forum teasers.
- Pending users have no forum access. Approved members see all member forums.
- UX target: PyTorch / fast.ai forum feel — category list, topic list, threaded discussion.

## Email & Notifications

- Application submitted: automatic thank-you to the applicant by name, confirming WAAIS will respond.
- Admin notification email on every new application.
- Approval and request-more-info emails.
- Optional rejection email (admin chooses whether to send).
- Event registration: confirmation and reminder emails. Reminder timing admin-configurable, default 2 days before the event.
- Announcements: both email and dashboard notification.
- Production email target: Azure Communication Services Email over SMTP. Local development uses Laravel's log mailer.

## Azure Cost Estimate

| Service | Estimate |
|---|---|
| App Service (B2) | ~$15–20/mo |
| Discourse VM (B2s) | ~$15–20/mo |
| PostgreSQL Flexible Server (burstable/small) | ~$10–20/mo |
| Blob Storage | ~$2–5/mo |
| **Total** | **~$42–60/month** (well within the $167/month grant cap) |

## Open Questions

- PennKey feasibility — can WAAIS verify PennKey without institutional approval, or is Penn/Wharton IT approval required?
- Final initial Discourse industry/region categories beyond the launch list above.
- Other admins/super admins besides George.
- Production email domain/Sender verification in Azure Communication Services. — Resolved: custom-domain sender `noreply@mail.whartonai.studio` is shipped under WAAIS's own ACS resource.
- Whether to commission a WAAIS-specific brand variant from Penn/Wharton's brand team to supersede the current WAC UK chapter mark.
