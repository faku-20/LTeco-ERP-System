#!/usr/bin/env bash
set -euo pipefail

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

BACKUP_DIR="/opt/backups/ltecobike"
RCLONE_CONFIG="/home/facu/.config/rclone/rclone.conf"
REMOTE="lteco_backup_crypt:"
STATUS_FILE="$BACKUP_DIR/offsite-status.json"

fail() {
    local msg="$1"
    cat > "${STATUS_FILE}.tmp" <<JSON
{
  "ok": false,
  "error": "$msg",
  "recorded_at": "$(date --iso-8601=seconds)"
}
JSON
    mv "${STATUS_FILE}.tmp" "$STATUS_FILE"
    chmod 640 "$STATUS_FILE"
    echo "ERROR: $msg" >&2
    exit 1
}

[ -r "$RCLONE_CONFIG" ] \
    || fail "rclone config no disponible"

LATEST="$(ls -1t "$BACKUP_DIR"/lteco_db_*.sql.gz 2>/dev/null | head -n 1 || true)"

[ -n "$LATEST" ] \
    || fail "no se encontró backup local"

CHECKSUM="${LATEST}.sha256"

[ -f "$CHECKSUM" ] \
    || fail "falta checksum del backup"

gzip -t "$LATEST" \
    || fail "gzip local inválido"

(
    cd "$BACKUP_DIR"
    sha256sum -c "$(basename "$CHECKSUM")"
) >/dev/null \
    || fail "checksum local inválido"

FILE="$(basename "$LATEST")"

rclone \
    --config "$RCLONE_CONFIG" \
    copyto \
    "$LATEST" \
    "${REMOTE}${FILE}" \
    || fail "falló subida del backup"

rclone \
    --config "$RCLONE_CONFIG" \
    copyto \
    "$CHECKSUM" \
    "${REMOTE}${FILE}.sha256" \
    || fail "falló subida del checksum"

rclone \
    --config "$RCLONE_CONFIG" \
    lsf "$REMOTE" --files-only \
    | grep -Fxq "$FILE" \
    || fail "backup no aparece en remoto"

rclone \
    --config "$RCLONE_CONFIG" \
    lsf "$REMOTE" --files-only \
    | grep -Fxq "${FILE}.sha256" \
    || fail "checksum no aparece en remoto"

cat > "${STATUS_FILE}.tmp" <<JSON
{
  "ok": true,
  "remote": "google-drive-encrypted",
  "file": "$FILE",
  "recorded_at": "$(date --iso-8601=seconds)"
}
JSON

mv "${STATUS_FILE}.tmp" "$STATUS_FILE"
chmod 640 "$STATUS_FILE"

echo "Backup off-site OK: $FILE"
