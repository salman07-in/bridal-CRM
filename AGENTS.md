# 🛡️ Safiya's Makeover CRM — Project Rules & Protection Guidelines

> **CRITICAL AGENT INSTRUCTION:**
> Whenever working on, updating, refactoring, or deploying this project (`Safiya's Makeover Bridal CRM`), you **MUST STRICTLY COMPLY** with the following non-negotiable safety rules.

---

## 🚫 1. STRICTLY FORBIDDEN (DO NOT MODIFY / DO NOT OVERWRITE)

### A. Database Credentials & Connection Settings
* **NEVER overwrite or reset database connection variables in `config/db.php`:**
  * `$DB_HOST`
  * `$DB_NAME` (e.g., `u791573478_mmcrm`)
  * `$DB_USER` (e.g., `u791573478_Salmancrm`)
  * `$DB_PASS` (the user's live Hostinger password)
  * `$DB_PORT`
* **NEVER overwrite or wipe `.env` files** on local or on Hostinger. Existing environment variables, secrets, and tokens must always be preserved.

### B. Client Data & Database Records (ZERO DATA LOSS)
* **NEVER run destructive SQL commands:**
  * `DROP TABLE`, `DROP DATABASE`, `TRUNCATE` are strictly forbidden.
  * `DELETE FROM customers` without explicit, narrow user confirmation is forbidden.
* **NEVER wipe or reset customer data:**
  * All bridal customer profiles, customer codes (`SAF-YYYY-XXXX`), mobile numbers, wedding dates, makeup dates, packages, and payment histories in the `customers` table MUST NEVER BE DELETED.
  * Audit logs (`audit_logs`) and dispatched greeting history (`reminder_logs`) must remain intact.
* **`database/schema.sql` must NEVER drop tables:**
  * Tables must only use `CREATE TABLE IF NOT EXISTS`.

### C. Live Integrations & Services
* **NEVER break or wipe WhatsApp Gateway configuration:**
  * `WHATSAPP_API_URL` (Render service endpoint) and `WHATSAPP_API_TOKEN` must be preserved.

---

## ✅ 2. ALLOWED & EXPECTED UPDATES (WHAT TO UPDATE)

When the user asks for updates or new features, only touch the following:

1. **User Interface & Design (UI/UX):**
   * Theme styling (`assets/css/`), Bootstrap layouts, color schemes, responsive mobile view.
   * Button designs, badges, icons, cards, and modal dialogs.
2. **Feature Additions & Enhancements:**
   * New reports, filters in Customer Register, advanced calendar views.
   * Export / Import formats, search enhancements, statistics widgets.
   * New reminder templates and WhatsApp message customization.
3. **Frontend JavaScript:**
   * AJAX flows (`assets/js/whatsapp_send.js`, `app.js`), form validation, auto-calculations.
4. **Backend Application Logic & Bug Fixes:**
   * Enhancing PHP helper functions in `includes/functions.php`.
   * Adding new endpoints under `api/` or new pages under `customers/`, `settings/`, `calendar/`.
5. **Non-Destructive Database Changes:**
   * If a new feature requires an extra column, use non-destructive schema migration:
     `ALTER TABLE customers ADD COLUMN IF NOT EXISTS ...`
   * Never alter or delete existing columns containing data.

---

## 🔒 3. VERIFICATION CHECKLIST BEFORE COMMITTING / PUSHING

Before pushing any update to GitHub or Hostinger:
- [ ] Confirm `config/db.php` still contains the user's live Hostinger credentials (`u791573478_mmcrm`, `u791573478_Salmancrm`).
- [ ] Confirm no existing database table is dropped or emptied.
- [ ] Ensure all PHP files have the opening `<?php` tag on line 1.
- [ ] Verify balanced syntax across all modified PHP and JS files.
- [ ] Ensure customer records remain 100% safe.
