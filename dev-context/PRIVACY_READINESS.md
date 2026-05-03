# WAAIS Privacy Readiness Checklist

> Product/legal readiness notes for launch. This is not legal advice; have counsel review final public legal text before launch.

## Current Privacy Posture

WAAIS will store personal data about Wharton/Penn alumni and related community members who may live in Europe, the United States, India, China, and other countries. A single Europe-hosted production database is acceptable as the technical default and avoids making EU-to-US transfer the baseline for primary storage.

The primary compliance task is not separate infrastructure per country. The primary task is clear notice, lawful collection, data minimization, and a working request process.

## Data Stored By The Platform

Likely personal data:

- Account identity: name, email, Google identity link, avatar URL, verification timestamps.
- Membership application: affiliation, graduation year, location, LinkedIn URL, experience/expertise summaries, industries, availability, optional demographic fields.
- Access/admin data: approval status, permission role, audit logs, review notes.
- Startup listings: founder names, submitter/owner relationship, review metadata.
- Events/announcements: admin-authored content and notification delivery records.
- Forum data later: Discourse account mapping, groups, posts/topics depending on Discourse configuration.

Optional demographic fields such as gender and age should stay optional and should not be required for membership.

## Required Public Legal Surfaces

Before public launch, replace the current legal placeholders with:

- Privacy Policy. Initial launch-readiness copy is live in `/legal`; counsel review remains recommended.
- Cookie Policy. Initial launch-readiness copy is live in `/legal`; counsel review remains recommended.
- GDPR/data rights request instructions. Initial launch-readiness copy is live in `/legal`; counsel review remains recommended.
- Contact path for privacy requests. `/legal` names `privacy@whartonai.studio` as the recommended mailbox until confirmed.

Recommended privacy contact:

```text
privacy@whartonai.studio
```

or another organization-controlled mailbox.

## Membership Form Notice

The membership application now requires a clear acknowledgement before first submit/reapply and stores `privacy_acknowledged_at` plus a version string. Current copy:

```text
By submitting this application, you agree that Wharton Alumni AI Studio and Research Center may process your information to review membership, operate the community, provide member services, send WAAIS-related communications, and maintain platform security and moderation records.
```

Also link to the Privacy Policy from the form.

## Privacy Policy Must Cover

The final policy should state:

- Controller/operator: Wharton Alumni AI Studio and Research Center.
- Domain: `whartonai.studio`.
- What data is collected.
- Why it is collected: membership review, account access, events, announcements, community operation, moderation, security, auditability.
- Where primary data is hosted: Azure Europe geography. The Laravel app and frontend are in West Europe; PostgreSQL is in North Europe because the subscription currently blocks Flexible Server provisioning in West Europe.
- Processors/vendors: Microsoft Azure, Azure Communication Services Email, Google OAuth, GitHub/GitHub Pages if still used, and Discourse once launched.
- Who can access data: approved admins/super-admins for operations and review.
- Retention approach: retain membership/application/audit records while account/community relationship exists unless deletion or legal retention rules apply.
- User rights/request process: access, correction, deletion, objection/withdrawal where applicable.
- International access: users and admins may access the platform from outside Europe; vendors may process data under their own terms and safeguards.

## International Users

Users in the United States, India, China, and other countries can use a Europe-hosted app. Do not create country-specific databases for v1.

For launch, do:

- Host primary app/database data in the Azure Europe geography. Current production is West Europe for app/frontend and North Europe for PostgreSQL.
- Give clear notice and request handling.
- Avoid importing unsolicited alumni lists.
- Let users apply/sign in themselves.

If WAAIS later imports bulk alumni/contact lists, review lawful basis and email consent rules first.

## Email And Consent Notes

Transactional/product emails currently include:

- Email sign-in links.
- Application submission/review notifications.
- Startup listing review notifications.
- Event reminders.
- Announcement emails for `email_dashboard` announcements.

Before launch:

- Make sure membership application notice covers WAAIS-related communications.
- Avoid marketing-style bulk emails to people who did not apply or sign up.
- Keep rejection emails opt-in for admins as currently implemented.

## Data Minimization Rules

- Keep age and gender optional.
- Do not require proof uploads in v1.
- Do not expose internal review notes or audit fields in public APIs.
- Keep public API projections allowlisted.
- Keep pending/rejected users out of member/forum surfaces.

## Operational Checklist

- Create/confirm privacy mailbox.
- Frontend legal placeholders replaced with launch-readiness copy; counsel review still recommended.
- Membership application notice/checkbox implemented for first submit/reapply.
- Confirm Azure region choice is documented as West Europe for app/frontend and North Europe for PostgreSQL.
- Confirm all production secrets are in Azure settings, not git.
- Confirm Google OAuth production client is owned by the organization account. Status: done May 3, 2026.
- Confirm ACS Email sender/domain is organization controlled. Status: Azure-managed sender is live; custom-domain sender remains a deliverability/brand follow-up.
- Decide retention policy for rejected applications and old audit logs.

## References

- [European Commission: rules on international data transfers](https://commission.europa.eu/law/law-topic/data-protection/international-dimension-data-protection/rules-international-data-transfers_en).
- [European Data Protection Board: international data transfers](https://www.edpb.europa.eu/sme-data-protection-guide/international-data-transfers_en).
- [Microsoft Learn: Azure regions and geographies](https://learn.microsoft.com/en-us/azure/reliability/regions-overview).
