#!/bin/sh
set -eu

# Importa el reporte más reciente generado fuera del dashboard.
# Uso:
#   REDTEAM_SOURCE_DIR=/ruta/a/reportes \
#   REDTEAM_URL=http://192.168.140.34/dashboard/proxy.php?action=redteam_save \
#   ./import_reports.sh
# Cron de ejemplo:
#   0 * * * * REDTEAM_SOURCE_DIR=$HOME/redteam-reports REDTEAM_URL=http://192.168.140.34/dashboard/proxy.php?action=redteam_save /path/to/import_reports.sh >/tmp/redteam-import.log 2>&1
# El dashboard conserva los ultimos 5 reportes JSON en data/redteam-reports/.

SOURCE_DIR="${REDTEAM_SOURCE_DIR:-$HOME/redteam-reports}"
URL="${REDTEAM_URL:-http://192.168.140.34/dashboard/proxy.php?action=redteam_save}"
KEEP="${REDTEAM_KEEP:-5}"

LATEST_FILE="$(ls -1t "$SOURCE_DIR"/*.json 2>/dev/null | head -n 1 || true)"

if [ -z "$LATEST_FILE" ]; then
  echo "No report files found in $SOURCE_DIR" >&2
  exit 1
fi

curl -sS -X POST "$URL" -H 'Content-Type: application/json' --data-binary @"$LATEST_FILE"

# Rotación local: conservar solo los últimos N archivos JSON.
COUNT=0
for f in $(ls -1t "$SOURCE_DIR"/*.json 2>/dev/null || true); do
  COUNT=$((COUNT + 1))
  if [ "$COUNT" -gt "$KEEP" ]; then
    rm -f "$f"
  fi
done

echo "Imported: $LATEST_FILE"
