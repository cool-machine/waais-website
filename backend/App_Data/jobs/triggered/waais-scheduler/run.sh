#!/usr/bin/env bash
set -euo pipefail

cd /home/site/wwwroot

echo "[waais-scheduler] $(date -u +'%Y-%m-%dT%H:%M:%SZ') running php artisan schedule:run"
php artisan schedule:run --no-interaction
