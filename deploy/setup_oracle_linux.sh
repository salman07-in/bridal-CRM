#!/usr/bin/env bash
# ==============================================================================
# Safiya's Makeover — Bridal CRM & Anniversary Reminder System
# Zero-Touch Deployment Script for Oracle Linux 9 (1 GB RAM Target)
# ==============================================================================
set -euo pipefail

echo "========================================================================"
echo " Starting Safiya's Makeover CRM Deployment on Oracle Linux 9"
echo "========================================================================"

if [[ $EUID -ne 0 ]]; then
   echo "[!] This script must be run as root (use: sudo bash deploy/setup_oracle_linux.sh)"
   exit 1
fi

APP_DIR="/var/www/safiya-crm"
APP_USER="safiya"
APP_GROUP="safiya"
LOG_DIR="/var/log/safiya-crm"

# 1. Low-Memory System Tuning: Create 2 GB Swapfile if missing
# Essential on 1 GB RAM Oracle Cloud Always Free VMs to prevent OOM termination
echo "[*] Checking swap memory..."
if [[ $(swapon --show | wc -l) -le 1 ]]; then
    echo "[+] Creating 2 GB swapfile for memory stability on 1 GB RAM VM..."
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo "vm.swappiness=20" >> /etc/sysctl.d/99-safiya-tuning.conf
    sysctl -p /etc/sysctl.d/99-safiya-tuning.conf
    echo "[+] Swap configured successfully."
else
    echo "[*] Swap already active."
fi

# 2. Install Required System Dependencies
echo "[*] Updating package repositories and installing dependencies..."
dnf install -y epel-release || true
dnf install -y \
    python3 \
    python3-pip \
    python3-devel \
    gcc \
    sqlite \
    nginx \
    gzip \
    gnupg2 \
    git \
    tar \
    certbot \
    python3-certbot-nginx

# 3. Create Dedicated Non-Root Application User & Group
if ! id "${APP_USER}" &>/dev/null; then
    echo "[+] Creating system user '${APP_USER}'..."
    useradd --system --shell /bin/bash --home-dir "${APP_DIR}" "${APP_USER}"
else
    echo "[*] User '${APP_USER}' already exists."
fi

# 4. Prepare Directory Tree and Logs
echo "[*] Setting up application directories..."
mkdir -p "${APP_DIR}"
mkdir -p "${APP_DIR}/instance"
mkdir -p "${APP_DIR}/backups"
mkdir -p "${APP_DIR}/uploads"
mkdir -p "${LOG_DIR}"

# 5. Setup Python Virtual Environment
echo "[*] Setting up Python virtual environment..."
if [[ ! -d "${APP_DIR}/venv" ]]; then
    python3 -m venv "${APP_DIR}/venv"
fi

"${APP_DIR}/venv/bin/pip" install --upgrade pip setuptools wheel
if [[ -f "${APP_DIR}/requirements.txt" ]]; then
    echo "[*] Installing Python packages from requirements.txt..."
    "${APP_DIR}/venv/bin/pip" install -r "${APP_DIR}/requirements.txt"
fi

# 6. Initialize Environment Configuration
if [[ ! -f "${APP_DIR}/.env" ]]; then
    echo "[+] Creating production .env file from template..."
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    RANDOM_SECRET="$(python3 -c 'import secrets; print(secrets.token_hex(32))')"
    sed -i "s/replace-with-a-very-long-random-secret-key-in-production/${RANDOM_SECRET}/" "${APP_DIR}/.env"
    RANDOM_API_KEY="$(python3 -c 'import secrets; print(secrets.token_urlsafe(32))')"
    sed -i "s/replace-with-a-random-secure-api-key-for-n8n/${RANDOM_API_KEY}/" "${APP_DIR}/.env"
    echo "[+] Generated random SECRET_KEY and CRM_API_KEY."
fi

# 7. Initialize SQLite Database Schema & Seed Admin
echo "[*] Initializing SQLite database schema..."
"${APP_DIR}/venv/bin/python" "${APP_DIR}/scripts/init_db.py"

# 8. Set Strict Permissions
echo "[*] Setting security permissions..."
chown -R "${APP_USER}:${APP_GROUP}" "${APP_DIR}"
chown -R "${APP_USER}:${APP_GROUP}" "${LOG_DIR}"
chmod 750 "${APP_DIR}"
chmod 700 "${APP_DIR}/instance"
chmod 600 "${APP_DIR}/.env"

# 9. Configure Systemd Services and Timers
echo "[*] Installing systemd units..."
cp "${APP_DIR}/deploy/safiya-crm.service" /etc/systemd/system/
cp "${APP_DIR}/deploy/safiya-crm-reminder.service" /etc/systemd/system/
cp "${APP_DIR}/deploy/safiya-crm-reminder.timer" /etc/systemd/system/

systemctl daemon-reload
systemctl enable --now safiya-crm.service
systemctl enable --now safiya-crm-reminder.timer

# 10. Configure Nginx Reverse Proxy
echo "[*] Configuring Nginx reverse proxy..."
cp "${APP_DIR}/deploy/nginx.conf" /etc/nginx/conf.d/safiya-crm.conf
systemctl enable --now nginx
nginx -t && systemctl reload nginx

# 11. Configure Automated Daily Backup Cron
echo "[*] Configuring automated daily backup cron job (02:00 AM)..."
CRON_JOB="0 2 * * * ${APP_USER} /usr/bin/bash ${APP_DIR}/scripts/backup.sh >> ${LOG_DIR}/backup.log 2>&1"
echo "${CRON_JOB}" > /etc/cron.d/safiya-crm-backup
chmod 644 /etc/cron.d/safiya-crm-backup

echo "========================================================================"
echo " [SUCCESS] Safiya's Makeover CRM is fully installed and operational!"
echo " Check service:   systemctl status safiya-crm"
echo " Check timer:     systemctl list-timers safiya-crm-reminder.timer"
echo " Access locally:  curl -I http://127.0.0.1:5000/auth/login"
echo "========================================================================"
