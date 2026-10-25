#!/usr/bin/env bash
# ==============================================================================
# Safiya's Makeover — Bridal CRM & Anniversary Reminder System
# Production SQLite Hot Backup Script with Integrity Check & Encryption
# ==============================================================================
set -euo pipefail

# Project root and configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Load environment configuration if available
if [[ -f "${APP_DIR}/.env" ]]; then
    # shellcheck disable=SC1091
    source "${APP_DIR}/.env"
fi

# Paths
DB_FILE="${DATABASE_URL:-sqlite:///${APP_DIR}/instance/safiya_crm.sqlite3}"
# Strip sqlite:/// prefix
DB_PATH="${DB_FILE#sqlite:///}"

BACKUP_DIR="${APP_DIR}/backups"
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"
BACKUP_BASENAME="safiya_crm_backup_${TIMESTAMP}.sqlite3"
TEMP_BACKUP="${BACKUP_DIR}/${BACKUP_BASENAME}"
FINAL_ARCHIVE="${BACKUP_DIR}/${BACKUP_BASENAME}.gz"

# Retention settings
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# Optional GPG encryption
GPG_PASSPHRASE="${BACKUP_GPG_PASSPHRASE:-}"
GPG_RECIPIENT="${BACKUP_GPG_RECIPIENT:-}"

# Logging
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S %Z')] $*"
}

log "Starting hot backup for Safiya's Makeover CRM database..."

# Ensure target directories exist
mkdir -p "${BACKUP_DIR}"

if [[ ! -f "${DB_PATH}" ]]; then
    log "ERROR: Database file not found at ${DB_PATH}"
    exit 1
fi

# Step 1: Perform consistent online SQLite backup using the SQLite backup API
log "Executing SQLite online hot backup..."
sqlite3 "${DB_PATH}" ".backup '${TEMP_BACKUP}'"

# Step 2: Verify backup file integrity
log "Verifying backup integrity..."
INTEGRITY_CHECK="$(sqlite3 "${TEMP_BACKUP}" "PRAGMA integrity_check;")"
if [[ "${INTEGRITY_CHECK}" != "ok" ]]; then
    log "ERROR: Backup integrity check failed: ${INTEGRITY_CHECK}"
    rm -f "${TEMP_BACKUP}"
    exit 2
fi
log "Integrity verified: OK"

# Step 3: Compress with high-ratio gzip
log "Compressing backup with gzip..."
gzip -9 "${TEMP_BACKUP}"
log "Created compressed backup: ${FINAL_ARCHIVE} ($(du -h "${FINAL_ARCHIVE}" | cut -f1))"

# Step 4: Optional GPG Encryption for Off-Server Storage
if [[ -n "${GPG_PASSPHRASE}" ]]; then
    log "Encrypting backup with symmetric GPG passphrase..."
    gpg --batch --yes --passphrase "${GPG_PASSPHRASE}" --symmetric --cipher-algo AES256 -o "${FINAL_ARCHIVE}.gpg" "${FINAL_ARCHIVE}"
    rm -f "${FINAL_ARCHIVE}"
    FINAL_ARCHIVE="${FINAL_ARCHIVE}.gpg"
    log "Encrypted file generated: ${FINAL_ARCHIVE}"
elif [[ -n "${GPG_RECIPIENT}" ]]; then
    log "Encrypting backup with GPG public key for ${GPG_RECIPIENT}..."
    gpg --batch --yes --trust-model always --recipient "${GPG_RECIPIENT}" --encrypt -o "${FINAL_ARCHIVE}.gpg" "${FINAL_ARCHIVE}"
    rm -f "${FINAL_ARCHIVE}"
    FINAL_ARCHIVE="${FINAL_ARCHIVE}.gpg"
    log "Encrypted file generated: ${FINAL_ARCHIVE}"
fi

# Step 5: Optional Off-Server Sync (e.g. Oracle Object Storage / rsync / rclone)
if [[ -n "${OFFSITE_BACKUP_DESTINATION:-}" ]]; then
    log "Synchronizing backup off-server to: ${OFFSITE_BACKUP_DESTINATION}..."
    if command -v rsync &> /dev/null; then
        rsync -avz "${FINAL_ARCHIVE}" "${OFFSITE_BACKUP_DESTINATION}"
    elif command -v rclone &> /dev/null; then
        rclone copy "${FINAL_ARCHIVE}" "${OFFSITE_BACKUP_DESTINATION}"
    else
        log "WARNING: Neither rsync nor rclone found; off-server sync skipped."
    fi
fi

# Step 6: Backup Rotation (Clean backups older than RETENTION_DAYS)
log "Pruning backups older than ${RETENTION_DAYS} days in ${BACKUP_DIR}..."
find "${BACKUP_DIR}" -name "safiya_crm_backup_*.sqlite3*" -type f -mtime "+${RETENTION_DAYS}" -exec rm -f {} +
REMAINING_COUNT="$(find "${BACKUP_DIR}" -name "safiya_crm_backup_*.sqlite3*" -type f | wc -l)"
log "Backup complete. Total retained archives: ${REMAINING_COUNT}"
