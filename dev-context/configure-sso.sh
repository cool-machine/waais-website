#!/usr/bin/env bash
# Configure DiscourseConnect SSO on the Discourse VM.
# Generates a random 64-char hex secret, sets the relevant Discourse site
# settings via rails runner, writes the secret to a root-only file, and
# echoes ONLY the secret on stdout so the operator can put it in App Service.
# Idempotent: if /home/waaisops/.discourse-sso-secret already exists, reuse it.
set -euo pipefail

SECRET_FILE=/home/waaisops/.discourse-sso-secret

if [ ! -f "$SECRET_FILE" ]; then
  SECRET=$(openssl rand -hex 32)
  echo "$SECRET" | sudo tee "$SECRET_FILE" > /dev/null
  sudo chmod 600 "$SECRET_FILE"
  sudo chown waaisops:waaisops "$SECRET_FILE"
fi
SECRET=$(sudo cat "$SECRET_FILE")
[ ${#SECRET} -eq 64 ] || { echo "FATAL: secret length wrong" >&2; exit 1; }

echo "applying Discourse site settings via rails runner..." >&2
sudo docker exec -e SECRET="$SECRET" app rails runner '
# Order matters: URL + secret must be set BEFORE enabling discourse_connect.
# Disable user-editable email before turning on SSO email override; Discourse
# refuses to enable the override otherwise.
SiteSetting.email_editable = false
SiteSetting.discourse_connect_url = "https://api.whartonai.studio/discourse/sso"
SiteSetting.discourse_connect_secret = ENV["SECRET"]
SiteSetting.enable_discourse_connect = true
# auth_overrides_* are the generic post-rename names; keep Discourse profile in
# sync with Laravel on every login. (username override skipped — usernames stay
# what Laravel sent at first login; users can rename inside Discourse if they
# want a forum-specific handle.)
SiteSetting.auth_overrides_email = true
SiteSetting.auth_overrides_name = true
SiteSetting.discourse_connect_overrides_avatar = true
SiteSetting.discourse_connect_overrides_groups = true
SiteSetting.logout_redirect = "https://whartonai.studio/"
SiteSetting.title = "WAAIS Forum"
SiteSetting.site_description = "Wharton Alumni AI Studio member forum."
SiteSetting.contact_email = "george@whartonai.studio"
SiteSetting.notification_email = "noreply@mail.whartonai.studio"
puts "ok: enable_discourse_connect=#{SiteSetting.enable_discourse_connect} url=#{SiteSetting.discourse_connect_url}"
' >&2

# Last line of stdout is the secret. Caller captures it.
echo "$SECRET"
