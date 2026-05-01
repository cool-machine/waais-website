# WAAIS Platform Model Contract

Last updated: May 1, 2026

This file pins down the vocabulary Laravel should use for accounts, access, applications, and publishable content. It resolves the earlier ambiguity where `pending` was sometimes treated like a role.

## Core Rule

Do not model access with one overloaded `role` field. Use separate fields:

- `approval_status`: where the account/application is in review.
- `affiliation_type`: what kind of community relationship the person has.
- `permission_role`: what the user is allowed to do in the product.

## Account Fields

| Field | Values | Purpose |
|---|---|---|
| `approval_status` | `none`, `draft`, `submitted`, `needs_more_info`, `approved`, `rejected`, `suspended` | Membership/application workflow state. |
| `affiliation_type` | `alumni`, `student`, `faculty_staff`, `partner_guest`, `other` | Wharton/Penn/community relationship. |
| `permission_role` | `public`, `pending_user`, `member`, `admin`, `super_admin` | Product permission level. |

## Access Interpretation

- Logged-out visitors are not database users; treat them as `permission_role = public` only in frontend/API policy checks.
- Submitted but unapproved applicants use `approval_status = submitted` and `permission_role = pending_user`.
- Approved alumni, students, and partner guests can receive `permission_role = member` if admins approve full access.
- `admin` includes member features plus approvals, user management, content management, announcements, and moderation.
- `super_admin` can do everything admins can do and is the only role that can promote users to admin or remove admin privileges.
- Limit `super_admin` to George plus at most two designated others.
- Suspended users should keep identity/application records but lose member/admin access until reinstated.

## Application/Profile Rules

- The membership form stores application answers separately from immutable verified identity.
- Applicants and members can edit submitted application/profile answers.
- Applicants and members cannot delete their application.
- Legal identity fields need admin review after verification: first name, last name, verified email, linked Google identity.
- Store application/profile revision history: changed fields, old value, new value, actor, timestamp.
- Rejected applicants can reapply without necessarily needing a new invitation.

## Publishable Content

Use one shared status vocabulary for events, startups, partners, homepage cards, announcements, team profiles, and public forum teasers where possible.

| Field | Values |
|---|---|
| `content_status` | `draft`, `pending_review`, `published`, `hidden`, `archived` |
| `visibility` | `public`, `members_only`, `mixed` |

Rules:

- Regular members cannot directly publish public content.
- Approved members can submit startup listings; admins review before publication.
- Admins can create, edit, publish, hide, and archive/remove public content at launch.
- Super admins can override admin changes and can later change whether admins may publish directly.
- Remove means hidden/archived first. Hard deletion is a later policy decision.
- Audit content changes, publish/hide/archive/remove actions, role changes, form-setting changes, and application/profile edits.

## Event-Specific Fields

Events should support:

- `visibility`: `public`, `members_only`, or `mixed`
- external registration URL, including NationBuilder-style links
- capacity limit
- waitlist state
- cancellation state
- reminder timing, default two days before event
- recap content for past events

Cancelled events should be hidden from public views but remain visible to admins.

## Forum/Discourse Rules

- Discourse lives at `forum.whartonai.studio`.
- Laravel should provide the Discourse SSO relay.
- Pending users do not get forum access.
- Approved members can see member forums.
- Some forum content may be public, but public visibility must be requested and admin-approved.
- Public site forum teasers must be curated or admin-approved.
