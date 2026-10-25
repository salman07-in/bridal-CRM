import os
import sys
from pathlib import Path
from datetime import date, timedelta

# Add project root directory to sys.path
BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

# Ensure UTF-8 output on Windows consoles
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

from app import create_app
from app.extensions import db
from app.models.user import User
from app.models.customer import Customer
from app.models.setting import Setting
from app.models.audit_log import AuditLog
from sqlalchemy import text


def init_database(seed_sample_data: bool = False):
    """Initializes database schema, WAL mode, admin user, and settings."""
    app = create_app()

    with app.app_context():
        print("[*] Initializing SQLite database schema...")
        db.create_all()

        # Enable SQLite Write-Ahead Logging (WAL) for high concurrency and performance
        try:
            db.session.execute(text("PRAGMA journal_mode=WAL;"))
            db.session.execute(text("PRAGMA synchronous=NORMAL;"))
            db.session.execute(text("PRAGMA foreign_keys=ON;"))
            db.session.commit()
            print("[+] SQLite WAL (Write-Ahead Logging) mode enabled.")
        except Exception as e:
            print(f"[!] Notice on PRAGMA execution: {e}")

        # Seed or Update Default Admin
        admin_username = os.getenv("DEFAULT_ADMIN_USERNAME", "admin")
        admin_email = os.getenv("DEFAULT_ADMIN_EMAIL", "admin@safiyasmakeover.com")
        admin_password = os.getenv("DEFAULT_ADMIN_PASSWORD", "Admin@12345")

        admin_user = User.query.filter((User.username == admin_username) | (User.email == admin_email)).first()
        if not admin_user:
            admin_user = User(
                username=admin_username,
                email=admin_email,
                full_name="Safiya Makeover Admin",
                role="admin",
                is_active=True,
            )
            admin_user.set_password(admin_password)
            db.session.add(admin_user)
            db.session.flush()

            AuditLog.log(
                action="system_init_admin",
                entity_type="user",
                entity_id=admin_user.id,
                details={"username": admin_username, "email": admin_email},
                user_id=admin_user.id,
            )
            print(f"[+] Admin user created: '{admin_username}' ({admin_email})")
        else:
            print(f"[*] Admin user already exists: '{admin_user.username}'")

        # Seed Default Settings
        default_settings = [
            ("salon_name", os.getenv("SALON_NAME", "Safiya's Makeover"), "Salon brand name"),
            ("salon_tagline", os.getenv("SALON_TAGLINE", "Luxury Bridal & Makeover Studio"), "Salon subtitle"),
            ("salon_branch", os.getenv("SALON_BRANCH", "Main Branch"), "Primary branch"),
            ("salon_phone", os.getenv("SALON_PHONE", "+91 98765 43210"), "Studio phone"),
            ("salon_owner_email", os.getenv("SALON_OWNER_EMAIL", "owner@safiyasmakeover.com"), "Owner email for daily digests"),
            ("reminder_advance_days", os.getenv("REMINDER_ADVANCE_DAYS", "7"), "Forecast lead days for upcoming anniversaries"),
            ("send_owner_daily_digest", os.getenv("SEND_OWNER_DAILY_DIGEST", "True"), "Send daily digest to owner"),
            ("smtp_enabled", os.getenv("SMTP_ENABLED", "False"), "SMTP service status"),
            ("smtp_host", os.getenv("SMTP_HOST", "smtp.gmail.com"), "SMTP server host"),
            ("smtp_port", os.getenv("SMTP_PORT", "587"), "SMTP port"),
            ("smtp_use_tls", os.getenv("SMTP_USE_TLS", "True"), "Use STARTTLS"),
            ("smtp_from_email", os.getenv("SMTP_FROM_EMAIL", "notifications@safiyasmakeover.com"), "From email"),
            ("smtp_from_name", os.getenv("SMTP_FROM_NAME", "Safiya's Makeover"), "Sender display name"),
            ("crm_api_key", os.getenv("CRM_API_KEY", "safiya-dev-api-key-test-1234567890"), "Internal API Key for n8n"),
        ]

        for key, val, desc in default_settings:
            existing = Setting.query.filter_by(key=key).first()
            if not existing:
                Setting.set_value(key, val, desc)

        db.session.commit()
        print("[+] Default salon settings seeded.")

        # Optionally Seed Realistic Sample Customers for demo/verification
        if seed_sample_data:
            print("[*] Seeding realistic bridal customer records...")
            today = date.today()
            sample_brides = [
                {
                    "full_name": "Ayesha Farooqi",
                    "mobile_number": "9876500001",
                    "wedding_date": today.replace(year=today.year - 2), # 2nd anniversary today!
                    "makeup_date": today.replace(year=today.year - 2),
                    "package": "Bridal HD - M.A.C",
                    "amount": 12000,
                    "branch": "Main Studio",
                    "notes": "Classic royal red lehenga look with M.A.C finish",
                    "consent": True,
                },
                {
                    "full_name": "Zainab Siddiqui",
                    "mobile_number": "9876500002",
                    "wedding_date": today.replace(year=today.year - 1), # 1st anniversary today!
                    "makeup_date": today.replace(year=today.year - 1),
                    "package": "Bridal Airbrush - Huda Beauty",
                    "amount": 15000,
                    "branch": "City Centre Branch",
                    "notes": "Reception glam look with Huda Beauty airbrush",
                    "consent": True,
                },
                {
                    "full_name": "Fatima Zahra",
                    "mobile_number": "9876500003",
                    "wedding_date": (today + timedelta(days=3)).replace(year=today.year - 3), # In 3 days
                    "makeup_date": (today + timedelta(days=3)).replace(year=today.year - 3),
                    "package": "Bridal HD - Forever 52",
                    "amount": 9500,
                    "branch": "Bridal Lounge",
                    "notes": "Pastel pink look with pearl jewelry setting",
                    "consent": True,
                },
                {
                    "full_name": "Mariam Qureshi",
                    "mobile_number": "9876500004",
                    "wedding_date": None, # Missing wedding date for review!
                    "makeup_date": today - timedelta(days=120),
                    "package": "Bridal HD - Bobby Brown",
                    "amount": 20000,
                    "branch": "Main Studio",
                    "notes": "Handwritten diary record: call bride to confirm wedding date",
                    "consent": False,
                },
                {
                    "full_name": "Sana Parveen",
                    "mobile_number": "9876500005",
                    "wedding_date": date(2020, 2, 29), # Leap year bride!
                    "makeup_date": date(2020, 2, 28),
                    "package": "Bridal Airbrush - Nars",
                    "amount": 20000,
                    "branch": "Main Studio",
                    "notes": "Leap year wedding! Celebrates on Feb 28 in non-leap years.",
                    "consent": True,
                },
            ]

            for s in sample_brides:
                if not Customer.query.filter_by(mobile_number=s["mobile_number"]).first():
                    cust = Customer(
                        customer_code=Customer.generate_next_code(2024),
                        full_name=s["full_name"],
                        mobile_number=s["mobile_number"],
                        wedding_date=s["wedding_date"],
                        makeup_date=s["makeup_date"],
                        service_package=s["package"],
                        amount=s["amount"],
                        salon_branch=s["branch"],
                        notes=s["notes"],
                        whatsapp_consent=s["consent"],
                    )
                    db.session.add(cust)
            db.session.commit()
            print("[+] Seeded sample bridal records.")

        print("[+] Database initialization completed successfully!")


if __name__ == "__main__":
    seed_flag = "--sample" in sys.argv
    init_database(seed_sample_data=seed_flag)
