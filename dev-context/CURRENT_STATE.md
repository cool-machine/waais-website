# WAAIS — Current State Handoff

Last updated: May 1, 2026

This file is the short version of the handoff. Read `DEV_CONTEXT.md` for the full source of truth, then read `FRONTEND_HANDOFF_SUMMARY.md` for the current Vue/deployment snapshot.

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
- Changed GitHub Pages root to serve the Vue frontend preview. Static mockups remain available under `/mockups/`.

## Design Decisions Locked In

- Navbar/top shell uses water blue `#256F8F`.
- Wharton navy `#011F5B` remains a deep brand color for CTA bands, stats, and accents.
- Wharton crimson `#C41E3A` is the primary action/accent color.
- Homepage uses a dark video hero and dynamic scroll motion, followed by white/off-white readable content sections.
- Dashboard, admin, auth, and operational surfaces remain dark.
- Current logo is a placeholder; George will provide a real club brand mark later.
- Forum will use `forum.whartonai.studio`, not `whartonai.studio/forum`.
- Public navigation should include a Forum item. The current mockup opens an internal forum preview page because `forum.whartonai.studio` is not installed yet.

## Membership Flow Now Designed

Backend model decision: do not overload one `role` field. Use separate `approval_status`, `affiliation_type`, and `permission_role` fields as defined in `dev-context/PLATFORM_MODEL.md`.

The public CTA should be `Become a member`, not a generic Get Involved button.

The Membership page should present:

- Existing members: Sign in.
- New applicants: Apply for membership.
- Non-members: Propose a topic, partner with WAAIS, or request a startup listing.

The application form should mirror the current Google Forms questionnaire, with updated phone wording: email, first name, last name, optional phone associated with WhatsApp account, alumnus/a yes/no, free-text school affiliation, graduation year, inviter name for non-alumni, primary/secondary location, LinkedIn, experience, expertise, industries to add value to, industries to extend expertise to, availability, optional gender, and optional age. Phone is only for applicants who also want to join the WhatsApp community. Alumni status must be yes/no only. School affiliation should be free text so users can describe student, faculty, staff, school, program, or other affiliation without a predefined dropdown. No proof upload is needed for v1; admin review is enough. After submission, the user lands in a pending approval state until an admin approves them.

The startup directory is designed as partially gated: public users can see teasers, but full member/startup profiles require approved member access.

Membership/auth review decisions:

- PennKey would be the best long-term affiliation signal if Penn/Wharton allows integration, but do not block the first implementation on it. Use Google OAuth first and keep PennKey as a future institutional integration question.
- Role vocabulary: `anonymous visitor` means a logged-out public visitor, not an approved member hiding their name.
- Approved members may later get an anonymous posting/display option for specific posts/comments where enabled. Admins/super admins should still be able to audit real identity if needed.
- Pending users are not shown in the member directory or in forums, including private forums.
- Students must be tracked distinctly from alumni because access rules may differ.
- Invited non-alumni partners/guests can receive full access when approved/invited.
- Only `super_admin` users can promote a user to admin or remove admin privileges. This group should be George plus at most two designated others.
- Applicants/members should be able to edit submitted application/profile answers, similar to the current Google Forms workflow.
- Applicants/members should not be able to delete their application.
- The system should keep application/profile revision history so admins can see what changed, when, and by whom.
- Users should not be able to freely change legal identity fields such as first name, last name, verified email, or linked Google identity after verification.
- Users may set a display name/username for public/member-facing visibility if they want partial anonymity in community contexts. Admins should still see real identity.
- Rejected applicants can reapply; a new invitation is not necessarily required.
- Admins/super admins should be able to configure which application fields are visible/required where allowed; members and non-admins cannot change form settings.
- Admins can edit, publish, hide, and archive/remove events, startups, partners, homepage cards, and announcements.
- Super admins can override admin changes and can change whether admins are allowed to publish directly.
- Team members should be able to edit their own names/profile details.
- Only admins and super admins can edit public content such as partners, events, startups, homepage cards, and announcements after approval/publishing.
- At launch, all admins have equal permissions. Super admins have higher privileges and can override admin actions.
- Remove should first mean hidden/archived for a retention period; hard deletion can happen later according to policy.
- The system needs an audit trail for who did what and when, covering content edits, publish/hide/archive/remove actions, form-setting changes, application/profile edits, and role changes.
- Public content should support draft, published, hidden, and archived statuses, at least visible to super admins.
- Application submission should send an automatic email thanking the applicant by name and saying WAAIS will get back to them as soon as possible.
- Admins should receive an email for each new application.
- Approval and request-more-information emails should be supported.
- Rejection emails are optional and only sent if an admin chooses to send one.
- Event registration should send confirmation and reminder emails. Reminder timing is admin-configurable and defaults to two days before the event.
- Announcements should go by both email and dashboard notification.
- Email provider can be decided later. Candidates include Azure Communication Services Email and Google for Nonprofits / Google Workspace options.
- Events can be public, members-only, or mixed; each event needs a visibility setting.
- Event registration can stay external for now, including current NationBuilder-style registration links, while WAAIS keeps room for internal RSVP later.
- Events need capacity limits and waitlists.
- Cancelled events should be hidden from public views but visible to admins.
- Past events should have recap pages.
- Public users can see some public forum content; approved members should be able to see all member forums.
- Approved members can submit startup listings.
- Startup listings require admin approval before they are published on the website.
- Every published startup should have a dedicated detail page.

## Admin / Content Management Now Designed

The admin mockup includes design-only screens for:

- Admin overview.
- Approval queue.
- User management.
- Event management.
- Public content management.
- Announcements.

The Public Content section is intended to let admins create, edit, publish, draft, hide, or remove cards shown on the public website, including events, startups, partners, homepage modules, and other small content cards.

Important: this is not implemented as working software yet. It is a static HTML prototype only. Real authentication, permissions, database persistence, and publishing logic still need backend/API implementation.

## Discourse Direction

Discourse should be installed later, not inside the GitHub Pages/static frontend preview. It will run on its own Azure VM using the official Docker-based Discourse install, with DNS pointing `forum.whartonai.studio` to that VM.

The forum should imitate the structure people already understand from the WhatsApp groups, with industry-based organization as the primary structure and region-based organization as a secondary structure:

- Initial industry examples: Finance, Fintech, Investments in AI, AI Engineering, AI Theory, AI in Business, Publishing.
- Regions and industries should not be limited to a fixed launch list; users should be able to define/propose regions and industries not already listed.

The forum UX target is similar to PyTorch forums or fast.ai forums: simple category lists, topic lists, and discussion threads. Topic creation and replies inside approved categories should not require pre-approval by default. Admins must be able to remove or moderate inappropriate posts/topics. If a discussion is public, that public visibility is requested by the publisher and approved by an admin. The public site should show latest/selected public forum topics as teasers, but admins must approve or curate which topics appear.

## Current URLs

- GitHub repo: `https://github.com/cool-machine/waais-website`
- GitHub Pages root: `https://cool-machine.github.io/waais-website/`
- Public mockup: `https://cool-machine.github.io/waais-website/mockups/public-site.html`
- App/admin/auth mockup: `https://cool-machine.github.io/waais-website/mockups/app-dashboard-admin-auth.html`
- Design system: `https://cool-machine.github.io/waais-website/mockups/design-system.html`

## What We Are Doing Now

The Vue frontend scaffold has been merged into `main` and expanded with public detail routes plus frontend-only app/auth/member/admin mockup routes. The GitHub Pages root now serves the built Vue preview from root-level static assets.

Backend work has started and the scaffold is now merged to `main`. `/backend/` contains a Laravel scaffold with WAAIS enums, membership application models, audit-log models, migrations, access-rule tests, Sanctum API auth foundation, Google OAuth pending-user provisioning, and applicant-owned membership application submit/update/reapply endpoints. PHP/Composer were repaired locally, Composer dependencies were installed, and the scaffold now passes test and migration validation. Read `/Users/gg1900/coding/waais-website/dev-context/BACKEND_HANDOFF.md` before continuing backend work.

## Remaining Next Steps

1. Implement admin review, roles, admin permissions, CMS persistence, events, startups, partners, and Discourse SSO.
3. Continue frontend polish only as needed while backend APIs take shape.
4. Replace placeholder logo/brand mark when George provides it.
5. Later deploy the production app/backend to Azure and Discourse to an Azure VM at `forum.whartonai.studio`.

## Watch Outs

- The deployed GitHub Pages preview is a built Vue frontend. It is not PHP/Laravel and does not persist data.
- Root-level `index.html`, `404.html`, `assets/`, `favicon.svg`, and `icons.svg` are generated from `frontend/dist` for GitHub Pages.
- The old static admin mockup remains available at `mockups/app-dashboard-admin-auth.html` for reference.
- Browser cache may show an old GitHub Pages version; hard refresh or open the direct mockup URLs.
- `legacy/` exists locally but is ignored by git.
- `/frontend/` exists and is tracked.
- `/backend/` now exists as a Laravel scaffold with WAAIS enums, membership application models, audit-log models, and access-rule tests. Initial validation passed locally with `composer install`, `php artisan test`, and `php artisan migrate:fresh`. Read `dev-context/BACKEND_HANDOFF.md` before continuing backend work.
