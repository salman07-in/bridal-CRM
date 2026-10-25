#!/usr/bin/env bash
# ==============================================================================
# Safiya's Makeover — Bridal CRM & Anniversary Reminder System
# Database Restore & Verification Script
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Load environment configuration if available
if [[ -f "${APP_DIR}/.env" ]]; then
    # shellcheck disable=SC1091
    source "${APP_DIR}/.env"
fi

DB_FILE="${DATABASE_URL:-sqlite:///${APP_DIR}/instance/safiya_crm.sqlite3}"
DB_PATH="${DB_FILE#sqlite:///}"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S %Z')] $*"
}

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <path-to-backup-file.sqlite3[.gz|.gz.gpg]>"
    echo ""
    echo "Example:"
    echo "  $0 backups/safiya_crm_backup_20261025_120000.sqlite3.gz"
    exit 1
fi

TARGET_BACKUP="$1"

if [[ ! -f "${TARGET_BACKUP}" ]]; then
    log "ERROR: Backup archive '${TARGET_BACKUP}' not found."
    exit 1
fi

log "Preparing restore from: ${TARGET_BACKUP}"

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

EXTRACTED_SQLITE="${WORK_DIR}/restored.sqlite3"

# Handle .gpg decryption if needed
CURRENT_FILE="${TARGET_BACKUP}"
if [[ "${CURRENT_FILE}" == *.gpg ]]; then
    log "Decrypting GPG archive..."
    DECRYPTED_GZ="${WORK_DIR}/backup.gz"
    if [[ -n "${BACKUP_GPG_PASSPHRASE:-}" ]]; then
        gpg --batch --yes --passphrase "${BACKUP_GPG_PASSPHRASE}" --decrypt -o "${DECRYPTED_GZ}" "${CURRENT_FILE}"
    else
        gpg --batch --yes --decrypt -o "${DECRYPTED_GZ}" "${CURRENT_FILE}"
    fi
    CURRENT_FILE="${DECRYPTED_GZ}"
fi

# Handle .gz decompression
if [[ "${CURRENT_FILE}" == *.gz ]]; then
    log "Decompressing gzip archive..."
    gunzip -c "${CURRENT_FILE}" > "${EXTRACTED_SQLITE}"
else
    cp "${CURRENT_FILE}" "${EXTRACTED_SQLITE}"
fi

# Verify Integrity of Extracted Database
log "Validating SQLite integrity check on restored file..."
INTEGRITY="$(sqlite3 "${EXTRACTED_SQLITE}" "PRAGMA integrity_check;")"
if [[ "${INTEGRITY}" != "ok" ]]; then
    log "FATAL: Integrity check failed with '${INTEGRITY}'. Aborting restore."
    exit 2
fi

CUSTOMER_COUNT="$(sqlite3 "${EXTRACTED_SQLITE}" "SELECT COUNT(*) FROM customers;")"
log "Integrity verified OK. Customer records in backup: ${CUSTOMER_COUNT}"

# Safety snapshot of active database before overwrite
if [[ -f "${DB_PATH}" ]]; then
    SAFETY_BACKUP="${DB_PATH}.before_restore_$(date +'%Y%m%d_%H%M%S')"
    log "Creating safety snapshot of current active database at ${SAFETY_BACKUP}..."
    sqlite3 "${DB_PATH}" ".backup '${SAFETY_BACKUP}'"
fi

# Stop service if running in systemd
if command -v systemctl &> /dev/null && systemctl is-active --quiet safiya-crm; then
    log "Temporarily stopping safiya-crm systemd service..."
    sudo systemctl stop safiya-crm || true
fi

# Overwrite active database with verified restored copy
mkdir -p "$(dirname "${DB_PATH}")"
cp "${EXTRACTED_SQLITE}" "${DB_PATH}"

# Ensure WAL mode and proper permissions
sqlite3 "${DB_PATH}" "PRAGMA journal_mode=WAL;"
sqlite3 "${DB_PATH}" "PRAGMA synchronous=NORMAL;"

# Restart service if applicable
if command -v systemctl &> /dev/null; then
    log "Restarting safiya-crm service..."
    sudo systemctl start safiya-crm || true
fi

log "SUCCESS! Database restored and verified successfully."
log "Active database: ${DB_PATH}"
log "Total customer records restored: ${CUSTOMER_COUNT}"
