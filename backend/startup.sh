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
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true
php artisan storage:link  || true

echo "[waais-startup] ready"
