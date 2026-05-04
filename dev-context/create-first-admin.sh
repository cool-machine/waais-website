#!/usr/bin/env bash
# Pre-create the first Discourse admin so the setup wizard goes away and the
# regular SSO login flow takes over. Idempotent — re-running just re-promotes.
# The user is created without a usable password; sign-in only works through SSO.
set -euo pipefail

EMAIL="${1:-gvishiani@gmail.com}"
USERNAME="${2:-gvishiani}"
NAME="${3:-George Vishiani}"
# external_id MUST match what Laravels DiscourseSsoController sends, which is
# "waais-user-{laravel_user.id}". The first super_admin in production is
# Laravel user_id=1 (gvishiani@gmail.com), so the default below is correct
# unless youre seeding a different user.
EXTERNAL_ID="${4:-waais-user-1}"

sudo docker exec -e EMAIL="$EMAIL" -e USERNAME="$USERNAME" -e NAME="$NAME" -e EXTERNAL_ID="$EXTERNAL_ID" app rails runner '
require "securerandom"
email = ENV["EMAIL"]
username = ENV["USERNAME"]
name = ENV["NAME"]

# In current Discourse the email lives on user_emails, not users.
ue = UserEmail.find_by(email: email)
user = ue&.user
if user.nil?
  user = User.new(
    email: email,
    username: username,
    name: name,
    password: SecureRandom.hex(32),
    active: true,
    approved: true,
    approved_at: Time.now,
    trust_level: TrustLevel[1],
  )
  user.skip_email_validation = true
  user.save!(validate: false)
end

user.email_tokens.update_all(confirmed: true) if user.email_tokens.any?
user.update!(active: true, approved: true, approved_at: Time.now)
user.activate

user.grant_admin!
user.grant_moderation! if user.respond_to?(:grant_moderation!)
user.update!(moderator: true) unless user.moderator

# Pre-link a SingleSignOnRecord so Discourse skips the email-match path on the
# first SSO sign-in. Without this pre-link, Discourse will look up the user by
# email (auth_overrides_email=true) and try to create the SSO record at sign-in
# time -- which 500s on this Discourse 8.x build because some part of the
# create-or-link path collides with the pre-seeded user.
external_id = ENV["EXTERNAL_ID"]
if external_id && !external_id.empty?
  rec = SingleSignOnRecord.find_or_initialize_by(user_id: user.id)
  rec.external_id = external_id
  rec.last_payload ||= ""
  rec.external_email = email
  rec.external_name = name
  rec.external_username = username
  rec.save!
  puts "linked SingleSignOnRecord external_id=#{rec.external_id} -> user_id=#{user.id}"
end

# Bootstrap mode auto-disables once an admin exists; force it off for safety.
if defined?(SiteSetting) && SiteSetting.respond_to?(:bootstrap_mode_enabled=)
  SiteSetting.bootstrap_mode_enabled = false
end

puts "ok: user_id=#{user.id} email=#{user.primary_email&.email || user.email} admin=#{user.admin} moderator=#{user.moderator} active=#{user.active} approved=#{user.approved}"
'
