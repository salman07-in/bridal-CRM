# Safiya’s Makeover — Bridal CRM & Anniversary Reminder System

A high-performance, production-grade Bridal Customer Relationship Management (CRM) and Wedding Anniversary Reminder web application built for **Safiya’s Makeover** bridal studio.

Designed to digitize handwritten bridal registers from 2020 onwards, securely manage client histories, run intelligent anniversary tracking in Indian Standard Time (`Asia/Kolkata`), handle leap years consistently, and automate reminders through SMTP email and an optional n8n / official WhatsApp Cloud API pipeline.

---

## 🌟 Key Features

1. **Quick Customer Entry & Register**:
   - Ultra-fast bride entry with live preview card.
   - Comprehensive customer register with real-time search, multi-criteria filtering (branch, missing wedding date, WhatsApp consent), and pagination.
   - Strict separation between **Wedding Date** (the sole trigger for anniversaries) and **Makeup Date** (operational service date). Unknown wedding dates are flagged, never guessed.
2. **Intelligent Anniversary Engine (`Asia/Kolkata`)**:
   - Timezone-accurate calculations for today's anniversaries and a rolling 7-day forecast.
   - Robust **Leap Year handling**: February 29 weddings are celebrated on Feb 29 in leap years and mapped to Feb 28 in non-leap years.
   - Automatic milestone calculation (1st, 2nd, 5th, etc.).
3. **Interactive Anniversary Calendar**:
   - Full monthly grid view displaying brides celebrating on each date with anniversary milestone badges and direct customer links.
4. **Excel/CSV Import & Export Wizard**:
   - Bulk upload legacy registers (.xlsx, .xls, .csv).
   - Tolerant date parser accepting `DD/MM/YYYY`, `DD-MM-YYYY`, `YYYY-MM-DD`, and Excel numeric serial dates.
   - Indian mobile number validator and cleaner (+91, leading 0, 10 digits).
   - Duplicate detection (both intra-file and against existing database records).
   - Interactive preview and validation error report before committing records.
   - Formatted Excel and CSV export with INR currency and Indian date conventions.
5. **Multi-Channel Reminders & Double-Send Protection**:
   - Strict unique **Idempotency Key**: `CUST_{id}_Y{year}_{channel}_{reminder_type}` guarantees zero double-sends.
   - Built-in SMTP email service sending an automated daily digest to the salon owner.
   - Direct click-to-chat WhatsApp link (`wa.me`) for instant manual greetings.
   - Optional automated dispatch via official Meta WhatsApp Business Cloud API (strictly requiring bride consent).
6. **n8n Automation Ready**:
   - Protected REST API (`/api/v1/reminders/due`, `/api/v1/reminders/mark-sent`) authenticated with `X-API-KEY`.
   - Exported workflow JSON ready to import into n8n.
7. **Production Hardening (1 GB RAM Optimized)**:
   - Tuned for Oracle Linux 9 on an Oracle Cloud Always Free VM (< 1 GB RAM usage).
   - SQLite Write-Ahead Logging (WAL) for high concurrency and zero memory locks.
   - Gunicorn with 2 workers, thread pool, and request recycling.
   - Automated online SQLite hot backup script with gzip, rotation, and GPG encryption.
   - Full audit trail logging sensitive actions.

---

## 🏗️ Architecture & Technology Stack

```
   [ Web Browser / Salon Staff ]
                 │
                 ▼  (HTTPS / 443)
       ┌───────────────────┐
       │   Nginx Proxy     │ (SSL, Security Headers, Rate Limit, Static Cache)
       └─────────┬─────────┘
                 │  (HTTP / 127.0.0.1:5000)
       ┌─────────▼─────────┐
       │  Gunicorn Server  │ (2 Workers, 2 Threads, 1 GB RAM Tuned)
       └─────────┬─────────┘
                 │
       ┌─────────▼─────────┐
       │    Flask Core     │ ──► [ Jinja2 + Bootstrap 5 + Custom Salon CSS ]
       └────┬──────────┬───┘
            │          │
            │          └──► [ SMTP Email Service (Daily Owner Digest) ]
            │
            ▼
┌───────────────────────────┐         ┌───────────────────────────┐
│ SQLite (WAL Mode Engine)  │         │  n8n Automation Engine    │
│  - customers              │ ◄────── │   - Daily Cron 09:00 IST  │
│  - users                  │ (API)   │   - WhatsApp Cloud API    │
│  - reminder_logs          │         └───────────────────────────┘
│  - settings               │
│  - audit_logs             │
└───────────────────────────┘
```

| Layer | Component | Description |
|---|---|---|
| **Backend** | Python 3.11+ / Flask 3 | Lightweight, fast WSGI application |
| **Database** | SQLite 3 (WAL Mode) | Serverless, ultra-low memory, reliable |
| **ORM** | SQLAlchemy 2.0 & Flask-Migrate | Parameterized queries, migrations |
| **Frontend** | Bootstrap 5.3 + Custom CSS | Clean white & soft rose gold theme (`#D4738C`), responsive sidebar |
| **WSGI Server** | Gunicorn 21+ | Pre-fork worker model tuned for low memory |
| **Web Server** | Nginx | Reverse proxy, SSL, rate limiting, file shielding |
| **Target OS** | Oracle Linux 9 (x86_64 or aarch64) | Oracle Cloud Always Free 1 GB RAM VM |

---

## 🚀 Quickstart (Local Development)

### 1. Prerequisites
- Python 3.10+ installed
- Git

### 2. Setup Virtual Environment
```bash
# Clone or navigate to project
cd "d:/bridal CRM Sof"

# Create virtual environment
python -m venv venv

# Activate virtual environment
# Windows:
.\venv\Scripts\activate
# Linux/macOS:
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

### 3. Initialize Database & Seed Admin
```bash
# Copy template environment file
copy .env.example .env   # (or 'cp .env.example .env' on Linux)

# Initialize database schema, settings, and sample demo brides
python scripts/init_db.py --sample
```

Default credentials seeded:
- **Username**: `admin`
- **Password**: `Admin@12345` (Change immediately upon login)

### 4. Run Development Server
```bash
python run.py
```
Open **[http://127.0.0.1:5000](http://127.0.0.1:5000)** in your browser.

### 5. Run Automated Tests
```bash
pytest -v
```
All 23 automated tests covering auth, customer CRUD, leap-year anniversary calculations, idempotency duplicate prevention, Excel import validation, and API authentication will execute.

---

## ☁️ Production Deployment on Oracle Linux 9 (1 GB RAM Target)

This application is specifically tuned to run smoothly on an **Oracle Cloud Always Free VM** (e.g. VM.Standard.E2.1.Micro or VM.Standard.A1.Flex) with **1 GB of RAM**.

### Step 1: Provision Oracle Cloud VM
1. Create a compute instance in Oracle Cloud with **Oracle Linux 9**.
2. Open ports in the Oracle Cloud Security List and OS firewall:
   - Ingress rules for TCP **80** (HTTP) and **443** (HTTPS).

### Step 2: Run Zero-Touch Setup Script
Clone this repository to your server and run the automated deployment script:
```bash
sudo dnf install -y git
git clone <your-repository-url> /tmp/safiya-crm-install
cd /tmp/safiya-crm-install

# Execute automated deployment script as root
sudo bash deploy/setup_oracle_linux.sh
```

What the setup script automates:
- **Swapfile Configuration**: Creates a 2 GB swapfile with `swappiness=20`. This is critical for preventing Linux Out-Of-Memory (OOM) process termination on 1 GB RAM instances.
- **Dedicated System User**: Creates system user `safiya` with restricted privileges.
- **Python Virtualenv**: Installs dependencies and Gunicorn into `/var/www/safiya-crm/venv`.
- **Database Bootstrapping**: Generates database schema with SQLite WAL mode and seeds credentials.
- **Systemd Service**: Registers and starts `safiya-crm.service` with Gunicorn worker recycling.
- **Systemd Timer**: Schedules `safiya-crm-reminder.timer` to execute daily at 09:00 AM IST.
- **Nginx Reverse Proxy**: Installs `/etc/nginx/conf.d/safiya-crm.conf` with rate-limiting and security blocks.
- **Automated Backup Cron**: Installs `/etc/cron.d/safiya-crm-backup` to run hot backups daily at 02:00 AM.

### Step 3: Install Free SSL Certificate (Let's Encrypt)
```bash
sudo certbot --nginx -d bridal.yourstudio.com
```

### Step 4: Verify Services
```bash
# Check Gunicorn application status
sudo systemctl status safiya-crm

# Check Daily Reminder Timer
sudo systemctl list-timers safiya-crm-reminder.timer

# Check Nginx status
sudo systemctl status nginx
```

---

## 📅 Anniversary & Date Rules

1. **Strict Date Separation**:
   - **`wedding_date`**: The sole trigger for calculating marriage milestones and scheduling anniversary reminders.
   - **`makeup_date`**: Operational record of when the makeover service was delivered.
   - Unknown wedding dates remain `NULL` and are flagged on the dashboard as `Missing (Review)`. The system never guesses or infers wedding dates.
2. **February 29 Leap Year Rule**:
   - In leap years (e.g., 2024, 2028), anniversaries are celebrated on **February 29**.
   - In non-leap years (e.g., 2025, 2026, 2027), the anniversary is automatically celebrated on **February 28**.
3. **Timezone**:
   - Calculations use `Asia/Kolkata` (IST Standard Time).

---

## 🔒 Security & Idempotency Architecture

- **Session Security**: Cookies are flagged with `HttpOnly`, `SameSite=Lax`, and `Secure` (when HTTPS is enabled).
- **Rate Limiting**: Login endpoints are protected with `Flask-Limiter` (5 POST attempts per minute) and Nginx burst limiting.
- **CSRF Protection**: All browser POST forms require valid CSRF tokens (`Flask-WTF`). Machine-to-machine API endpoints are protected via `X-API-KEY`.
- **Database Double-Send Guard**:
  Every scheduled reminder creates a record in `reminder_logs` with a unique idempotency key:
  ```
  idempotency_key = CUST_{customer_id}_Y{anniversary_year}_{channel}_{reminder_type}
  ```
  Example: `CUST_14_Y2025_whatsapp_today_greeting`
  Even if the reminder job runs repeatedly or n8n re-fires, duplicate greetings are physically impossible at the database constraint level.

---

## 💾 Backup, Retention & Disaster Recovery

### Automated Backup (`scripts/backup.sh`)
Executes an online hot SQLite backup without locking web requests:
```bash
sqlite3 instance/safiya_crm.sqlite3 ".backup 'backups/safiya_crm_backup_YYYYMMDD_HHMMSS.sqlite3'"
```
1. Validates schema integrity (`PRAGMA integrity_check;`).
2. Compresses with `gzip -9`.
3. Optionally encrypts with GPG (AES-256) if `BACKUP_GPG_PASSPHRASE` is set in `.env`.
4. Prunes backups older than 30 days (`BACKUP_RETENTION_DAYS=30`).

### Restoring from Backup (`scripts/restore.sh`)
```bash
sudo bash scripts/restore.sh backups/safiya_crm_backup_20261025_020000.sqlite3.gz
```
The script:
1. Decompresses and (if encrypted) decrypts the target archive into a temporary sandbox.
2. Runs `PRAGMA integrity_check;` to verify database sanity.
3. Creates a safety snapshot of the existing database.
4. Atomically replaces the active database and restarts the application service.

### Recommended Monthly Restore Drill
On the 1st of every month:
1. Copy the latest backup to a test machine or local staging environment.
2. Run `scripts/restore.sh <backup_file>`.
3. Execute `pytest tests/test_anniversary.py` to confirm data validity.

---

## 🤖 n8n & Automation Integration

The CRM exposes a protected REST API for n8n:
- **`GET /api/v1/reminders/due`**: Returns pending greetings scheduled for today. Requires header `X-API-KEY: <your-key>`.
- **`POST /api/v1/reminders/mark-sent`**: Webhook callback to update status to `sent` and attach dispatch metadata.
- **`POST /api/v1/reminders/trigger-daily`**: Triggers daily evaluation and owner digest.

See [n8n/README_n8n.md](file:///d:/bridal%20CRM%20Sof/n8n/README_n8n.md) for full import and setup instructions.

---

## ⚠️ Remaining Manual Setup Steps & Resource Limitations

1. **Domain & DNS**: Point your domain `bridal.yourstudio.com` to your Oracle Cloud VM Public IP address before running Certbot.
2. **SMTP Credentials**: Configure your SMTP credentials in the Studio Settings page (`/settings/`) or `.env` and click **Send Verification Email**.
3. **Official WhatsApp API**: If opting for automated WhatsApp greetings, register a Meta Business Account and create the approved template `anniversary_greeting_v1`. (Manual click-to-chat WhatsApp works out of the box without any setup!).
4. **1 GB RAM Resource Limitations**:
   - The VM can comfortably handle 50,000+ bridal records and dozens of concurrent studio staff.
   - Do not install heavy background services (e.g. Elasticsearch, local LLMs, or heavy Docker stacks) on the same 1 GB VM.
   - Keep swap memory active as configured by `setup_oracle_linux.sh`.
