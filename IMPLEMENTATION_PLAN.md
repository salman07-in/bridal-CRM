# Safiya’s Makeover — Bridal CRM & Anniversary Reminder System
## Implementation Plan & Architecture Specification

### 1. Overview & Business Objectives
Safiya’s Makeover is a premier bridal makeover studio with handwritten customer records dating back to 2020. This system digitizes, secures, and automates bridal customer management:
- **Permanent, fast customer entry**: Captures brides, phone numbers, makeup packages, wedding dates, makeup dates, notes, and WhatsApp consent.
- **Strict date distinction**: Separates wedding date (anniversary trigger) from makeup/service date (operational trigger). Unknown wedding dates are flagged, never guessed.
- **Automated Anniversary Engine**: Computes exact anniversaries in `Asia/Kolkata` timezone, handling leap year Feb 29 dates cleanly, surfacing today's anniversaries and the upcoming 7-day window.
- **Automated Reminders & Multi-Channel Delivery**: Built-in SMTP email service (daily digests to salon owner and greetings to brides) + protected REST API for n8n automation and official WhatsApp Cloud API integration.
- **Strict Idempotency**: Unique constraint on reminder logs `(customer_id, year, channel, reminder_type)` prevents duplicate greetings.
- **Excel/CSV Wizard**: Bulk import legacy records with preview, date format tolerance (`DD-MM-YYYY`, `DD/MM/YYYY`, `YYYY-MM-DD`), duplicate detection, and detailed validation error reporting; clean export of filtered datasets.
- **Audit & Security**: Role-based access, password hashing, CSRF protection, login rate-limiting, session cookies hardening, and audit logging.
- **Production Oracle Cloud Deployment**: Low memory footprint (< 1 GB RAM), Gunicorn + Nginx reverse proxy, systemd service & timers, encrypted SQLite hot-backup scripts with rotation.

---

### 2. Database Schema Design (SQLite + SQLAlchemy)

#### Tables
1. **`users`**:
   - `id` (INTEGER, Primary Key)
   - `username` (VARCHAR(50), Unique, Indexed)
   - `email` (VARCHAR(120), Unique)
   - `password_hash` (VARCHAR(255))
   - `full_name` (VARCHAR(100))
   - `role` (VARCHAR(20): `admin`, `staff`)
   - `is_active` (BOOLEAN, default True)
   - `created_at` (DATETIME)
   - `last_login` (DATETIME, Nullable)

2. **`customers`**:
   - `id` (INTEGER, Primary Key)
   - `customer_code` (VARCHAR(20), Unique, Indexed, e.g. `SAF-2024-0001`)
   - `full_name` (VARCHAR(150), Indexed, Not Null)
   - `mobile_number` (VARCHAR(20), Indexed, Not Null)
   - `wedding_date` (DATE, Nullable, Indexed)
   - `makeup_date` (DATE, Nullable, Indexed)
   - `service_package` (VARCHAR(100), Nullable)
   - `amount` (DECIMAL(10, 2), Nullable)
   - `salon_branch` (VARCHAR(80), Nullable, Indexed)
   - `notes` (TEXT, Nullable)
   - `whatsapp_consent` (BOOLEAN, default False, Indexed)
   - `created_at` (DATETIME, default utcnow, Indexed)
   - `updated_at` (DATETIME, default utcnow, onupdate utcnow)

3. **`reminder_logs`**:
   - `id` (INTEGER, Primary Key)
   - `idempotency_key` (VARCHAR(100), Unique, Indexed) -> `CUST_{id}_Y{year}_{channel}_{reminder_type}`
   - `customer_id` (INTEGER, Foreign Key to `customers.id`, Indexed)
   - `anniversary_year` (INTEGER, Indexed)
   - `channel` (VARCHAR(20): `email`, `whatsapp`, `owner_digest`)
   - `reminder_type` (VARCHAR(30): `today_greeting`, `advance_7day_notice`, `owner_digest`)
   - `status` (VARCHAR(20): `pending`, `sent`, `failed`, `cancelled`, Indexed)
   - `scheduled_for` (DATE, Indexed)
   - `sent_at` (DATETIME, Nullable)
   - `retry_count` (INTEGER, default 0)
   - `error_message` (TEXT, Nullable)
   - `metadata_json` (TEXT, Nullable)
   - `created_at` (DATETIME)

4. **`settings`**:
   - `id` (INTEGER, Primary Key)
   - `key` (VARCHAR(50), Unique, Indexed)
   - `value` (TEXT, Nullable)
   - `description` (VARCHAR(255))
   - `updated_at` (DATETIME)

5. **`audit_logs`**:
   - `id` (INTEGER, Primary Key)
   - `user_id` (INTEGER, Nullable, Foreign Key to `users.id`)
   - `action` (VARCHAR(50), Indexed: `create_customer`, `update_customer`, `delete_customer`, `import_customers`, `export_customers`, `login`, `send_reminder`)
   - `entity_type` (VARCHAR(50))
   - `entity_id` (VARCHAR(50), Nullable)
   - `details` (TEXT, JSON string of diff/context)
   - `ip_address` (VARCHAR(45), Nullable)
   - `created_at` (DATETIME, default utcnow, Indexed)

---

### 3. Key Components & Implementation Steps

#### Phase 1: Environment & Foundational Setup
- Configure virtual environment and dependencies (`Flask`, `Flask-SQLAlchemy`, `Flask-Migrate`, `Flask-Login`, `Flask-WTF`, `Flask-Limiter`, `openpyxl`, `python-dotenv`, `pytest`).
- Establish application factory (`create_app`) with environment config profiles (`DevelopmentConfig`, `ProductionConfig`, `TestingConfig`).
- Initialize CSRF protection, login manager, rate limiters, error handlers, and Jinja filters for Indian currency (`₹ 25,000`) and dates (`DD/MM/YYYY`).

#### Phase 2: Core Models & Migrations
- Implement `User`, `Customer`, `ReminderLog`, `Setting`, `AuditLog`.
- Implement safe generation of unique `customer_code` and `idempotency_key`.
- Database initialization and seed script for admin user and default settings.

#### Phase 3: Forms & Authentication
- Secure login form with rate limiting (5 attempts/min), CSRF protection, and session security.
- Customer Add/Edit form with clean client & server-side validation (phone regex matching 10-digit Indian numbers, optional +91 prefix normalization, separate wedding and makeup dates).
- Settings form for Salon details, SMTP configuration, reminder lead times, and API authentication keys.

#### Phase 4: Customer Management & Anniversary Engine
- Customer CRUD: register with real-time search, filters (branch, date range, missing wedding date, WhatsApp consent), sorting, and pagination.
- Customer detail page: rich overview, wedding vs makeup countdown, anniversary history, audit trail, direct WhatsApp Web link (for manual click-to-chat).
- Anniversary calculation service (`app/services/anniversary.py`):
  - Strictly `Asia/Kolkata` time.
  - Leap year Feb 29 calculation (maps to Feb 28 in non-leap years).
  - Queries for today's anniversaries and next 7 days.
  - Flagging missing dates for salon staff review.

#### Phase 5: Excel/CSV Import & Export Wizard
- Flexible file parsing: supports `.xlsx`, `.xls`, `.csv`.
- Normalizes date strings: handles `DD/MM/YYYY`, `DD-MM-YYYY`, `YYYY-MM-DD`, Excel numeric serial dates.
- Normalizes phone numbers: cleans whitespace, dashes, leading 0 or +91.
- Duplicate detection: checks existing mobile numbers in database and within the batch.
- Detailed validation error report: allows user to inspect valid vs invalid rows before final commit.
- Export: filtered or full customer export to Excel/CSV with formatted values.

#### Phase 6: Anniversary Reminders, Email Service & n8n Integration
- SMTP Email Service: sends test emails, daily salon owner digests (with summary cards of today's and upcoming brides), and customer greetings.
- Idempotency protection: ensures no double sending.
- Protected REST API endpoints (`/api/v1/reminders/due`, `/api/v1/reminders/mark-sent`, `/api/v1/customers/stats`) authenticated via `X-API-KEY`.
- n8n workflow export (`n8n/n8n_bridal_anniversary_workflow.json`) for automated daily polling, webhook callbacks, and WhatsApp Cloud API dispatch.

#### Phase 7: UI/UX & Interactive Views
- Polished, luxurious aesthetic for a premier bridal makeover salon:
  - Clean white background (`#FFFFFF`, `#F8F9FA`), soft rose gold / blush pink highlights (`#D4738C`, `#F7D6D0`, `#FAF0F2`), deep slate text (`#1A202C`).
  - Responsive desktop sidebar and mobile offcanvas navigation.
  - Interactive Anniversary Calendar view with month/week browsing and quick customer drawer.
  - Toast notifications and modal confirmations.

#### Phase 8: Deployment, Backup & Hardening
- Systemd service definition with low-memory Gunicorn configuration (2 workers, threads, memory limits for 1 GB RAM VM).
- Nginx reverse proxy configuration with SSL termination, HTTP/2, client max body size, security headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options), rate limiting, and protection against `.sqlite3`, `.env`, and hidden files.
- Automated SQLite online backup script (`scripts/backup.sh`) with vacuum/backup API, gzip compression, retention cleanup, and optional GPG encryption.
- Verification and full automated test suite with pytest covering auth, customer lifecycle, anniversary calculations, import/export, and idempotency.
