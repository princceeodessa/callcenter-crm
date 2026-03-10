#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${1:-/var/www/callcenter-crm}"
CRON_LINE="* * * * * cd ${PROJECT_DIR} && /usr/bin/php artisan integrations:avito-poll --limit=100 >> /var/log/avito-poll.log 2>&1"
(
  crontab -l 2>/dev/null | grep -Fv 'php artisan integrations:avito-poll --limit=100' || true
  echo "$CRON_LINE"
) | crontab -

echo "Installed cron: $CRON_LINE"
crontab -l
