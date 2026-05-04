#!/usr/bin/env bash
# Pre-create the first Discourse admin so the setup wizard goes away and the
# regular SSO login flow takes over. Idempotent — re-running just re-promotes.
# The user is created without a usable password; sign-in only works through SSO.
set -euo pipefail

EMAIL="${1:-gvishiani@gmail.com}"
USERNAME="${2:-gvishiani}"
NAME="${3:-George Vishiani}"

sudo docker exec -e EMAIL="$EMAIL" -e USERNAME="$USERNAME" -e NAME="$NAME" app rails runner '
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

# Bootstrap mode auto-disables once an admin exists; force it off for safety.
if defined?(SiteSetting) && SiteSetting.respond_to?(:bootstrap_mode_enabled=)
  SiteSetting.bootstrap_mode_enabled = false
end

puts "ok: user_id=#{user.id} email=#{user.primary_email&.email || user.email} admin=#{user.admin} moderator=#{user.moderator} active=#{user.active} approved=#{user.approved}"
'
