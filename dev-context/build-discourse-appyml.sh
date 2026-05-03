#!/usr/bin/env bash
# Render /var/discourse/containers/app.yml from samples/standalone.yml,
# enable the HTTPS + Let's Encrypt templates, set SMTP via ACS, and
# (when invoked with --bootstrap) run ./launcher bootstrap app.
# Reads SMTP creds from /home/waaisops/.discourse-secrets (scp'd separately).
# Idempotent — safe to re-run.
set -euo pipefail

MODE="${1:-render-only}"   # "render-only" or "--bootstrap"

SECRETS_FILE=/home/waaisops/.discourse-secrets
[ -f "$SECRETS_FILE" ] || { echo "FATAL: $SECRETS_FILE not found" >&2; exit 1; }
# shellcheck disable=SC1090
source "$SECRETS_FILE"
: "${ACS_MAIL_USERNAME:?missing in secrets}"
: "${ACS_MAIL_PASSWORD:?missing in secrets}"

HOSTNAME=forum.whartonai.studio
ADMIN_EMAILS="george@whartonai.studio,gvishiani@gmail.com"
NOTIFICATION_EMAIL=noreply@mail.whartonai.studio
SMTP_HOST=smtp.azurecomm.net
SMTP_PORT=587

cd /var/discourse
sudo cp samples/standalone.yml /tmp/app.yml.fresh

# Use Python for the YAML edits — sed is brittle when values contain ~ / $ etc.
sudo /usr/bin/env python3 - "$HOSTNAME" "$ADMIN_EMAILS" "$SMTP_HOST" "$SMTP_PORT" \
  "$ACS_MAIL_USERNAME" "$ACS_MAIL_PASSWORD" "$NOTIFICATION_EMAIL" <<'PYEOF'
import re, sys
hostname, admins, smtp_host, smtp_port, smtp_user, smtp_pass, notify = sys.argv[1:8]
path = "/tmp/app.yml.fresh"
with open(path) as f: txt = f.read()

# 1) Enable HTTPS + Let's Encrypt templates (uncomment).
for tmpl in ("web.ssl.template.yml", "web.letsencrypt.ssl.template.yml"):
    pattern = rf'^( *)#-\s*"templates/{re.escape(tmpl)}"\s*$'
    repl    = rf'\1- "templates/{tmpl}"'
    new_txt, n = re.subn(pattern, repl, txt, count=1, flags=re.MULTILINE)
    if n != 1:
        sys.exit(f"FATAL: couldn't uncomment {tmpl}")
    txt = new_txt

# 2) env-var setter that handles both "KEY: ..." and "#KEY: ...".
def setenv(t, key, val):
    # Always quote with single quotes: YAML treats them as literal.
    pattern = rf"^( *)#?\s*{re.escape(key)}:.*$"
    repl    = rf"\1{key}: '{val}'"
    new_t, n = re.subn(pattern, repl, t, count=1, flags=re.MULTILINE)
    if n != 1:
        sys.exit(f"FATAL: couldn't set {key} (no match found)")
    return new_t

txt = setenv(txt, "DISCOURSE_HOSTNAME", hostname)
txt = setenv(txt, "DISCOURSE_DEVELOPER_EMAILS", admins)
txt = setenv(txt, "DISCOURSE_SMTP_ADDRESS", smtp_host)
txt = setenv(txt, "DISCOURSE_SMTP_PORT", smtp_port)
txt = setenv(txt, "DISCOURSE_SMTP_USER_NAME", smtp_user)
txt = setenv(txt, "DISCOURSE_SMTP_PASSWORD", smtp_pass)
txt = setenv(txt, "DISCOURSE_NOTIFICATION_EMAIL", notify)
txt = setenv(txt, "DISCOURSE_SMTP_AUTHENTICATION", "login")

with open(path, "w") as f: f.write(txt)
print("rendered OK")
PYEOF

# Sanity checks.
sudo grep -q "DISCOURSE_HOSTNAME: 'forum.whartonai.studio'" /tmp/app.yml.fresh \
  || { echo "FATAL: hostname not substituted" >&2; exit 1; }
sudo grep -q '^  - "templates/web.ssl.template.yml"' /tmp/app.yml.fresh \
  || { echo "FATAL: ssl template not enabled" >&2; exit 1; }
sudo grep -q '^  - "templates/web.letsencrypt.ssl.template.yml"' /tmp/app.yml.fresh \
  || { echo "FATAL: letsencrypt template not enabled" >&2; exit 1; }
if sudo grep -q "DISCOURSE_SMTP_PASSWORD: 'pa\\\$\\\$word'" /tmp/app.yml.fresh; then
  echo "FATAL: SMTP password is still placeholder" >&2
  exit 1
fi

sudo install -m 0600 -o root -g root /tmp/app.yml.fresh /var/discourse/containers/app.yml
sudo rm -f /tmp/app.yml.fresh

echo "=== app.yml installed (root-only). Non-secret summary: ==="
sudo grep -E "^( |#)*(DISCOURSE_HOSTNAME|DISCOURSE_DEVELOPER_EMAILS|DISCOURSE_SMTP_ADDRESS|DISCOURSE_SMTP_PORT|DISCOURSE_SMTP_USER_NAME|DISCOURSE_NOTIFICATION_EMAIL|DISCOURSE_SMTP_AUTHENTICATION):" \
  /var/discourse/containers/app.yml

echo "=== Templates list: ==="
sudo grep -E '^  - "templates/' /var/discourse/containers/app.yml

if [ "$MODE" != "--bootstrap" ]; then
  echo "render-only mode; pass --bootstrap to ./build-discourse-appyml.sh to actually run launcher"
  exit 0
fi

echo
echo "=== ./launcher bootstrap app (10-20 min) ==="
cd /var/discourse
sudo ./launcher bootstrap app
echo "=== ./launcher start app ==="
sudo ./launcher start app
echo "=== sudo docker ps ==="
sudo docker ps
