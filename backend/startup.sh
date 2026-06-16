#!/usr/bin/env bash
# WAAIS Laravel App Service startup
# Runs once when the container boots. Replaces the default nginx site config
# with our Laravel-friendly version so requests are routed through public/.

set -euo pipefail

CFG_SRC="/home/site/wwwroot/nginx-default.conf"
CFG_DST="/etc/nginx/sites-available/default"

if [ -f "$CFG_SRC" ]; then
  cp "$CFG_SRC" "$CFG_DST"
  nginx -t
  service nginx reload
  echo "[waais-startup] nginx reconfigured to serve from public/"
else
  echo "[waais-startup] WARNING: $CFG_SRC missing; leaving nginx default in place"
fi

# Laravel optimizations against the deployed code. Safe to re-run on every boot.
cd /home/site/wwwroot
php artisan migrate --force || true
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true
php artisan storage:link  || true

# Legacy events were a ONE-TIME historical import and are now managed manually
# in the admin dashboard, so the deploy-time auto-import is intentionally
# disabled — this keeps dashboard edits/removals permanent (never re-seeded).
#
# The importer and seed file are kept on purpose. To re-run it by hand if ever
# needed (e.g. to restore a bulk batch):
#   php artisan waais:import-events database/data/legacy_events.json
# Create-only by default (skips existing external_refs); add --update to
# overwrite, or --dry-run to preview without writing.
# php artisan waais:import-events database/data/legacy_events.json || true

# Startup listings were a ONE-TIME seed and are now managed manually in the
# admin dashboard, so the deploy-time auto-import is intentionally disabled —
# keeping dashboard edits/removals permanent (never re-seeded).
# To re-run the one-off import by hand if ever needed:
#   php artisan waais:import-startups database/data/startups.json
# Create-only by default (skips existing website_urls); add --update to
# overwrite, or --dry-run to preview without writing.
# php artisan waais:import-startups database/data/startups.json || true

# Partners were a ONE-TIME seed and are now managed manually in the admin
# dashboard, so the deploy-time auto-import is intentionally disabled — keeping
# dashboard edits/removals permanent (never re-seeded).
# To re-run the one-off import by hand if ever needed:
#   php artisan waais:import-partners database/data/partners.json
# Create-only by default (skips existing website_urls); add --update to
# overwrite, or --dry-run to preview without writing.
# php artisan waais:import-partners database/data/partners.json || true

# Team members were a ONE-TIME seed and are now managed manually in the admin
# dashboard, so the deploy-time auto-import is intentionally disabled — keeping
# dashboard edits/removals permanent (never re-seeded).
# To re-run the one-off import by hand if ever needed:
#   php artisan waais:import-team database/data/team.json
# Create-only by default (matches on name); add --update to overwrite, or
# --dry-run to preview without writing. Supports match_name for renames.
# php artisan waais:import-team database/data/team.json --update || true

# The first board advisors (Didem Ün Ateş, Bruno Occhipinti, Tomás Gazmuri)
# were seeded once via the create-only import below and are now managed in the
# dashboard, so it is disabled again. To re-run by hand if ever needed:
#   php artisan waais:import-team database/data/team.json
# (create-only: skips existing names; add --update to overwrite.)
# php artisan waais:import-team database/data/team.json || true

# Advisor LinkedIn URLs were backfilled once via the --update import below and
# are now managed in the dashboard, so it is disabled again.
# php artisan waais:import-team database/data/team.json --update || true

# Board advisor Beny Rubinstein was seeded once via the create-only import
# below and is now managed in the dashboard, so it is disabled again.
# php artisan waais:import-team database/data/team.json || true

# The full board of advisors was seeded/reordered once via the --update import
# below and is now managed in the dashboard, so it is disabled again.
# php artisan waais:import-team database/data/team.json --update || true

echo "[waais-startup] ready"
