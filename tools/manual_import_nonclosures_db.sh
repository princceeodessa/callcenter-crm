#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${1:-/var/www/callcenter-crm}"
XLSX_PATH="${2:-/tmp/nonclosures.xlsx}"
ACCOUNT_ID="${3:-1}"
USER_ID="${4:-1}"

cd "$PROJECT_DIR"
php tools/manual_import_nonclosures_db.php "$XLSX_PATH" "$ACCOUNT_ID" "$USER_ID" --truncate
