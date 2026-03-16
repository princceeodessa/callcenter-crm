#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/callcenter-crm}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

cd "$APP_DIR"

if [[ ! -f .env ]]; then
  echo ".env not found. Copy deploy/ubuntu/.env.production.example to .env first." >&2
  exit 1
fi

"$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [[ ! -d .whisper-venv ]]; then
  "$PYTHON_BIN" -m venv .whisper-venv
fi

"$APP_DIR/.whisper-venv/bin/pip" install --upgrade pip
"$APP_DIR/.whisper-venv/bin/pip" install faster-whisper imageio-ffmpeg

mkdir -p \
  storage/app/whisper-models \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache

if [[ ! -L public/storage ]]; then
  "$PHP_BIN" artisan storage:link
fi

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true

echo "Deployment completed."
