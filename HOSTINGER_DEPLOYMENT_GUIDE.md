# 🚀 Safiya’s Makeover — Hostinger Business Web Hosting Deployment Guide

Aapka ye Bridal CRM & Anniversary Reminder system ab **PHP 8 + MySQL** me convert ho chuka hai aur aapki **Hostinger Business Web Hosting** par 100% chalne ke liye ready hai.

---

## 📋 Step-by-Step Deployment (Via GitHub & Hostinger hPanel)

### Step 1: Hostinger hPanel me MySQL Database Banayein

1. [Hostinger hPanel](https://hpanel.hostinger.com) me login karein.
2. Apni hosting select karein aur Left Menu me **Databases ➔ Management** par jayein.
3. Ek naya MySQL Database banayein:
   - **Database Name**: e.g. `u123456_bridal`
   - **Database Username**: e.g. `u123456_admin`
   - **Password**: Apna secure password enter karein
4. **Create** button par click karein.
5. In teeno cheezon (DB Name, Username, Password) ko note kar lein.

---

### Step 2: Database Schema Import Karein (1-Click)

1. Wahi Databases page par aapko **Enter phpMyAdmin** ka button dikhega.
2. phpMyAdmin open hone ke baad, top menu me **Import** par click karein.
3. **Choose File** par click karein aur project ki file **`database/schema.sql`** select karein.
4. Neeche **Import** (ya **Go**) button par click karein.
   - ✅ Saare tables (`customers`, `users`, `reminder_logs`, `settings`, `audit_logs`) ban jayenge.
   - ✅ Default admin user (`admin` / `Admin@12345`) create ho jayega.

---

### Step 3: Hostinger me GitHub se Deploy Karein

1. Hostinger hPanel me Left Menu me **Advanced ➔ Git** par click karein.
2. **Create a New Repository**:
   - **Repository URL**: `https://github.com/salman07-in/bridal-CRM.git`
   - **Branch**: `main`
   - **Install Directory**: `public_html` (ya agar subdomain hai to uska folder)
3. **Create** par click karein.
4. Ab **Auto Deployment** webhook activate kar sakte hain ya **Deploy** button daba dein. Files foran `public_html` me aa jayengi!

---

### Step 4: Database Password Configure Karein

1. Hostinger hPanel me **Files ➔ File Manager** kholein.
2. `public_html/config/db.php` file ko edit karein aur Step 1 wali details update kar dein:
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'u123456_bridal';    // Apna DB Name yahan likhein
   $DB_USER = 'u123456_admin';     // Apna DB Username yahan likhein
   $DB_PASS = 'Aapka_Password';    // Apna DB Password yahan likhein
   ```
3. Save kar dein.

---

### Step 5: Automated Daily Anniversary Check Schedule Karein (Cron Job)

1. Hostinger hPanel me **Advanced ➔ Cron Jobs** par click karein.
2. **Type**: Custom select karein.
3. **Command to run**:
   ```bash
   php /home/uXXXXXXX/public_html/cron/run_reminders.php
   ```
   *(Note: `/home/uXXXXXXX/` aapka Hostinger home path hai jo hPanel ke main dashboard par dikhta hai).*
4. **Time**: Roz subah 09:00 AM IST set karein:
   - Minute: `0`
   - Hour: `9`
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
5. Click **Save**.
   - Ab roz subah 9 baje system automatically daily anniversaries check karega aur studio owner ko daily email digest bhejega!

---

### Step 6: Website Open Karein! 🎉

Apna domain browser me open karein:
```text
https://yourdomain.com/
```

Default Login:
- **Username**: `admin`
- **Password**: `Admin@12345`

Login hote hi **Settings** me ja kar apna password change kar lein aur salon details update kar dein!
