if ! bash -c 'set -euo pipefail
SQ=$(printf "\x27")
BACKUP_ID="restore_$(date +%s%N)"
STAGING_DB="_vito_restore_$(date +%s%N)"
PREVIOUS_DB="_vito_restore_previous_$(date +%s%N)"
ARCHIVE="/var/lib/clickhouse/backups/${BACKUP_ID}.tar.gz"
PREVIOUS_DB_RENAMED=0
RESTORE_PROMOTED=0

cleanup() {
    if [ "$PREVIOUS_DB_RENAMED" -eq 1 ] && [ "$RESTORE_PROMOTED" -eq 0 ]; then
        PREVIOUS_DB_EXISTS=$(sudo clickhouse-client -q "EXISTS DATABASE \`${PREVIOUS_DB}\`" 2>/dev/null || true)
        TARGET_DB_EXISTS=$(sudo clickhouse-client -q "EXISTS DATABASE \`${DB_NAME}\`" 2>/dev/null || true)

        if [ "$PREVIOUS_DB_EXISTS" = "1" ] && [ "$TARGET_DB_EXISTS" != "1" ]; then
            sudo clickhouse-client -q "RENAME DATABASE \`${PREVIOUS_DB}\` TO \`${DB_NAME}\`;" || echo "Warning: previous database is retained as ${PREVIOUS_DB}"
        fi
    fi

    if [ "$RESTORE_PROMOTED" -eq 1 ]; then
        if [ "$PREVIOUS_DB_RENAMED" -eq 1 ]; then
            sudo clickhouse-client -q "DROP DATABASE IF EXISTS \`${PREVIOUS_DB}\` SYNC;" || echo "Warning: previous database is retained as ${PREVIOUS_DB}"
        fi
    else
        sudo clickhouse-client -q "DROP DATABASE IF EXISTS \`${STAGING_DB}\` SYNC;" 2>/dev/null || true
    fi

    sudo rm -f "$ARCHIVE" || true
}
trap cleanup EXIT

sudo mkdir -p /var/lib/clickhouse/backups
sudo cp "$2" "$ARCHIVE"
sudo chown clickhouse:clickhouse "$ARCHIVE"

DB_NAME="${1//\\/\\\\}"
DB_NAME="${DB_NAME//\`/\`\`}"

sudo clickhouse-client -q "RESTORE DATABASE \`${DB_NAME}\` AS \`${STAGING_DB}\` FROM Disk(${SQ}backups${SQ}, ${SQ}${BACKUP_ID}.tar.gz${SQ});"
if [ "$(sudo clickhouse-client -q "EXISTS DATABASE \`${DB_NAME}\`")" = "1" ]; then
    PREVIOUS_DB_RENAMED=1
sudo clickhouse-client -q "RENAME DATABASE \`${DB_NAME}\` TO \`${PREVIOUS_DB}\`;"
fi
sudo clickhouse-client -q "RENAME DATABASE \`${STAGING_DB}\` TO \`${DB_NAME}\`;"
RESTORE_PROMOTED=1' _ {!! escapeshellarg($database) !!} {!! escapeshellarg($path) !!}; then
    echo 'VITO_SSH_ERROR' && exit 1
fi

rm -f {!! escapeshellarg($path) !!}
