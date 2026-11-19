# 📋 Product Requirement Document (PRD) & Safe Update Policy
## Safiya’s Makeover — Bridal CRM & Anniversary Engine

**Document Status:** ACTIVE & PERMANENT  
**Scope:** Applies to all future modifications, updates, feature releases, and deployments.

---

### 1. Objective & Purpose
The purpose of this document is to establish **strict, non-negotiable safety guardrails** for developers and AI agents working on the Safiya's Makeover Bridal CRM codebase. It guarantees that **client data and live server configurations are 100% protected** while allowing the user interface, reporting, and operational features to continuously improve.

---

### 2. The "DO NOT TOUCH" Protected List (Non-Negotiable)

| Category | Protected Item | Strict Rule |
| :--- | :--- | :--- |
| 🗄️ **Database Credentials** | `config/db.php` (`$DB_NAME`, `$DB_USER`, `$DB_PASS`, `$DB_HOST`) | **NEVER overwrite or reset**. Must always preserve `u791573478_mmcrm` and `u791573478_Salmancrm` and the live user password. |
| 🔐 **Environment Secrets** | `.env` file | **NEVER wipe or overwrite**. Existing API keys, WhatsApp tokens, and database settings must remain persistent. |
| 👥 **Customer Data** | `customers` table | **ZERO DATA LOSS**. Never execute `DROP TABLE`, `TRUNCATE`, or indiscriminate `DELETE`. Client names, phone numbers, wedding dates, and payment history must NEVER be removed. |
| 📜 **Audit & Logs** | `reminder_logs`, `audit_logs` | Dispatched anniversary greetings history and activity trails must never be cleared. |
| 📱 **WhatsApp Live Session** | WhatsApp Web Gateway (`WHATSAPP_API_URL`, `WHATSAPP_API_TOKEN`) | Never break the Render microservice connection endpoint. |

---

### 3. Allowed Updates & Safe Evolution Area

When updates are requested, work must be strictly confined to:

1. **Frontend Styling & Visual Polish:**
   * Bootstrap 5.3 UI improvements, rose gold theme aesthetics, typography, mobile responsiveness.
   * New badges, search bars, customer view cards, and navigation enhancements.
2. **Business Logic & Analytics:**
   * Adding new filter tabs (e.g. Filter by Branch, Filter by Package, Pending Balance alerts).
   * Enhancing anniversary milestone forecasting (7-day, 30-day, eve alerts).
   * New automated message templates and customized greetings.
3. **Import & Export Capabilities:**
   * Smarter Excel / CSV parsing, column auto-mapping, error reports.
4. **Non-Destructive Schema Additions:**
   * If a new feature needs extra fields (e.g. `instagram_handle`, `alternate_number`), use:
     ```sql
     ALTER TABLE customers ADD COLUMN IF NOT EXISTS instagram_handle VARCHAR(100) NULL;
     ```
   * Never drop or rename columns that hold data.

---

### 4. Developer & AI Agent Mandatory Checklist Before Any Git Push

Before running `git commit` and `git push`:
- [x] Check that `config/db.php` has NOT been reverted to default dummy values.
- [x] Check that no database table creation script contains `DROP TABLE IF EXISTS`.
- [x] Ensure all `.php` files begin with an opening `<?php` tag on line 1.
- [x] Test that bracket and parenthesis syntax is completely balanced.
- [x] Test that CSV import defaults to updating or appending without deleting existing rows.
